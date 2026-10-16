---
title: Running under Tenma
description: Serve the app from one long-running process with Tenma, wait for databases without blocking, and hand requests over to streams and WebSockets.
order: 150
---

[Tenma](https://github.com/systonia/Tenma) runs PHP as a long-running process with an event loop. The front controller runs once; the app it built (routes, container, services, configuration) stays in memory and answers every request, each in a fiber of its own. While one request waits for a database or an HTTP call, the process serves others.

The front controller needs no changes:

```bash
tenma public/index.php                 # port 8080, or PORT from the environment
tenma --watch public/index.php         # restarts the PHP side whenever a loaded file changes
tenma --workers auto public/index.php  # one worker per CPU core
```

Under Tenma, `serve()` starts the server on `serve($port)`, else on `PORT` from the [environment](application.md#environment-and-env-files), else on 8080. [`FileSystemOptions::$publicDirectory`](../reference/Waypoint/Options/FileSystemOptions.md) is handed to Tenma, which serves those files itself, without PHP, with `ETag` and range requests. `serve(8080, ['/assets' => __DIR__ . '/assets'])` passes Tenma's own static mounts instead.

`serve()` is the same as `Tenma::serve($port, $app, $static)`: an [`App`](../reference/Waypoint/App.md) is a Tenma request handler, translated by [`Waypoint\Tenma\Bridge`](../reference/Waypoint/Tenma/Bridge.md).

Each worker runs the front controller on its own and has its own app. Requests of one worker share its services.

## Shared services, separate requests

Everything in [Dependency injection](dependency-injection.md#controllers-per-request-services-per-process) about per-request state applies here in full: controllers and route middlewares are created per request, services live as long as the worker, and a value that belongs to one request lives on the request, is passed as an argument, or sits in a [`FiberLocal`](../reference/Waypoint/Support/FiberLocal.md). Waypoint's own per-request values (the request id on log lines, the CSRF token) are kept that way, and under Tenma they follow the request into the tasks it starts with `Tenma::async()`.

## Waiting without blocking

A controller or service can use Tenma's asynchronous APIs directly: `Tenma\Db\Postgres`, `Tenma::fetch()`, `Tenma::all()`. Each call suspends only the current request. A database connection is a service like any other; create it in the front controller and register it:

```php
<?php

namespace App\Services;

use Tenma\Db\Postgres;

final class Notes
{
    public function __construct(private Postgres $db)
    {
    }

    public function latest(int $limit): array
    {
        return $this->db->query('SELECT id, text FROM notes ORDER BY id DESC LIMIT $1', [$limit]);
    }

    public function add(string $text): array
    {
        return $this->db->first('INSERT INTO notes (text) VALUES ($1) RETURNING id, text', [$text]);
    }
}
```

```php
<?php

namespace App\Controllers;

use App\Services\Notes;
use Waypoint\Attributes\{Controller, Get, Inject, Post};
use Waypoint\Http\{Request, Response};

#[Controller('/notes')]
final class NotesController
{
    #[Inject]
    private Notes $notes;

    #[Get('')]
    public function index(int $limit = 20): array
    {
        return $this->notes->latest($limit);
    }

    #[Post('')]
    public function create(Request $req, Response $res): array
    {
        $res->status(201);
        return $this->notes->add((string) $req->body('text', ''));
    }
}
```

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\NotesController;
use App\Services\Notes;
use Tenma\Db\Postgres;
use Waypoint\Environment;
use Waypoint\Options\EnvironmentOptions;
use Waypoint\Waypoint;

$app = Waypoint::create();
$app->configure(fn (EnvironmentOptions $env) => $env->load(dirname(__DIR__)));

$db = Postgres::connect($app->get(Environment::class)->get('DATABASE_URL'));
Tenma::async(fn () => $db->script('CREATE TABLE IF NOT EXISTS notes (id serial PRIMARY KEY, text text NOT NULL)'));
$app->getContainer()->set(new Notes($db));

$app->attach([NotesController::class]);
$app->serve();
```

`Postgres::connect()` doesn't wait; the first query opens the connection, and Tenma keeps a pool per worker. Startup work that waits runs in a task, as the table creation above. Database errors surface as exceptions and end the request with a `500` like any other.

Gather everything a page needs in the controller before returning a `View`; a template that waits would hold the request in the middle of rendering. Two independent queries can run at once with `Tenma::all()`.

## Streams and WebSockets

A route can hand its request over to Tenma with [`$res->upgrade()`](../reference/Waypoint/Http/Response.md#upgrade): a streamed body, server-sent events or a WebSocket. Everything Waypoint does before the controller still applies: authentication, roles, CSRF, guards and middlewares. Tenma's response then replaces Waypoint's.

```php
<?php

namespace App\Controllers;

use Tenma\Http\{EventStream, Response as TenmaResponse, Stream, WebSocket};
use Waypoint\Attributes\{Authenticated, Controller, Get};
use Waypoint\Exceptions\ForbiddenException;
use Waypoint\Http\{Request, Response};

#[Controller('/live')]
final class LiveController
{
    #[Get('/export.csv')]
    public function export(Response $res): void
    {
        $res->upgrade(TenmaResponse::stream(function (Stream $out) {
            $out->write("id,total\n");
            foreach (range(1, 3) as $id) {
                \Tenma::sleep(0.1);                     // stands for fetching the next page of rows
                $out->write("$id," . ($id * 10) . "\n");
            }
        }, headers: ['content-type' => 'text/csv']));
    }

    #[Get('/clock')]
    public function clock(Response $res): void
    {
        $res->upgrade(TenmaResponse::events(function (EventStream $events) {
            foreach (range(1, 3) as $tick) {
                $events->send(json_encode(['time' => date(DATE_ATOM)]), event: 'tick');
                \Tenma::sleep(1);
            }
        }));
    }

    #[Get('/chat')]
    #[Authenticated]
    public function chat(Request $req, Response $res): void
    {
        if (($req->headers['Origin'] ?? '') !== 'https://shop.example.com') {
            throw new ForbiddenException('Unknown origin.');
        }
        $user = $req->jwt['sub'];
        $res->upgrade(TenmaResponse::websocket(function (WebSocket $socket) use ($user) {
            while (($message = $socket->receive()) !== null) {
                $socket->send("$user: $message");
            }
        }));
    }
}
```

- A stream keeps the headers and cookies set on the Waypoint response (CORS, security headers, `X-Request-ID`); its own headers and status win, and `Content-Type`, `Content-Length` and `Content-Encoding` come from the stream.
- A WebSocket answers with its handshake only.
- The handler runs after the request has passed through Waypoint, with the request's context restored: the request id on log lines, the CSRF token and every `FiberLocal`. Values from the request itself, such as the JWT payload, go in through `use`.
- If the controller or a middleware throws after `upgrade()`, the error response goes out and the upgrade is dropped.
- A WebSocket handshake carries no CSRF header. A WebSocket route that acts for a signed-in user checks the `Origin` header itself, as above.

Under PHP-FPM and `php -S` an upgrade is ignored. Tenma's guides on [streams](https://github.com/systonia/Tenma/blob/main/docs/guides/streams.md) and [WebSockets](https://github.com/systonia/Tenma/blob/main/docs/guides/websockets.md) cover the handlers themselves.

## Uploads

Uploaded files arrive as [`UploadedFile`](../reference/Waypoint/Http/UploadedFile.md), as under FPM. `moveTo()` writes through Tenma without blocking the worker. The body size limit is Tenma's `--max-body` (10 MB by default).

See also: [`Bridge`](../reference/Waypoint/Tenma/Bridge.md), [`FiberLocal`](../reference/Waypoint/Support/FiberLocal.md), [Runtimes](runtimes.md), [Deployment](deployment.md).
