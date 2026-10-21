---
title: Running under Tenma
description: Serve the app from one long-running process with Tenma, wait for databases without blocking, and hand requests over to streams and WebSockets.
order: 150
---

[Tenma](https://github.com/systonia/Tenma) runs PHP as a long-running process with an event loop. The front controller runs once; the app it built (routes, container, services, configuration) stays in memory and answers every request, each in a fiber of its own. While one request waits for a database or an HTTP call, the process serves others.

The front controller needs no changes. The Bakery runs its `index.php` like this:

```bash
tenma index.php                 # port 8080, or PORT from the environment
tenma --watch index.php         # restarts the PHP side whenever a loaded file changes
tenma --workers 2 index.php     # two workers; --workers auto starts one per CPU core
```

Under Tenma, `serve()` starts the server on `serve($port)`, else on `PORT` from the [environment](application.md#environment-and-env-files), else on 8080. [`FileSystemOptions::$publicDirectory`](../reference/Waypoint/Options/FileSystemOptions.md) is handed to Tenma, which serves those files itself, without PHP, with `ETag` and range requests. `serve(8080, ['/assets' => __DIR__ . '/assets'])` passes Tenma's own static mounts instead.

`serve()` is the same as `new Http\Server($port, $app, $static)->start()`: an [`App`](../reference/Waypoint/App.md) is a Tenma request handler, translated by [`Waypoint\Tenma\Bridge`](../reference/Waypoint/Tenma/Bridge.md).

Each worker runs the front controller on its own and has its own app. Requests of one worker share its services.

## Shared services, separate requests

Everything in [Dependency injection](dependency-injection.md#controllers-per-request-services-per-process) about per-request state applies here in full: controllers and route middlewares are created per request, services live as long as the worker, and a value that belongs to one request lives on the request, is passed as an argument, or sits in a [`FiberLocal`](../reference/Waypoint/Support/FiberLocal.md). Waypoint's own per-request values (the request id on log lines, the CSRF token) are kept that way, and under Tenma they follow the request into the tasks it starts with `Threading\Task::run()`.

## Waiting without blocking

A controller or service can use Tenma's asynchronous APIs directly: `Data\Postgres`, `Http\Client`, `Threading\Task::all()`. Each call suspends only the current request. A database connection is a service like any other: the Bakery creates the pool in `app.php` and registers it, and every class that injects `Postgres` gets it:

```php
use Data\Postgres;

// One pool of Postgres connections per worker, shared by every service that injects Postgres
$app->getContainer()->set(Postgres::connect($env->string('DATABASE_URL', 'postgres://localhost/bakery')));
```

```php
<?php

namespace Bakery\Repositories;

use Bakery\Models\Product;
use Data\Postgres;
use Waypoint\Attributes\Inject;
use Waypoint\Support\Row;

/** The range from Postgres. Every call waits only for its own query: other requests of the worker go on meanwhile. */
final class Products
{
    public const COLUMNS = 'p.id, p.slug, p.name, p.description, p.category, p.price_cents AS "priceCents", p.stock, p.picture';

    #[Inject]
    private Postgres $db;

    public function bySlug(string $slug): ?Product
    {
        $row = $this->db->first('SELECT ' . self::COLUMNS . ' FROM products p WHERE p.slug = $1', [$slug]);
        return $row === null ? null : Product::of(new Row($row));
    }
}
```

`Postgres::connect()` doesn't wait; the first query opens the connection, and Tenma keeps a pool per worker. Database errors surface as exceptions and end the request with a `500` like any other.

Gather everything a page needs in the controller before returning a `View`; a template that waits would hold the request in the middle of rendering. Independent sources can be asked at once with `Threading\Task::all()`. The Bakery's product page needs the product, the opening hours, the public holidays and the weather (shortened from `ShopController::product()`):

```php
#[Get('/products/{slug}')]
public function product(string $slug): View
{
    $product = $holidays = $weather = null;
    $hours = [];
    Threading\Task::all(
        function () use (&$product, $slug): void { $product = $this->products->bySlug($slug); },
        function () use (&$hours): void { $hours = $this->hours->week(); },
        function () use (&$holidays): void { $holidays = $this->holidays->upcoming(); },
        function () use (&$weather): void { $weather = $this->weather->week(); },
    );
    if ($product === null) {
        throw new NotFoundException("No product $slug");
    }
    // …
    return new View('Shop/Product', new ProductPage(/* … */));
}
```

The page then takes as long as the slowest of the four. What the front controller sets up stays in memory, so slow sources can also be fetched in the background: `index.php` refreshes the holidays and the weather with timers, and the page reads the last good answers without waiting:

```php
$lastKnown = $app->get(LastKnown::class);
Threading\Task::run($lastKnown->refreshWeather(...));
Threading\Timer::every(900, $lastKnown->refreshWeather(...));

$app->serve();
```

Startup work that waits runs in a task like this, with `Threading\Task::run()`. Each worker runs its own timers.

## Streams and WebSockets

A route can hand its request over to Tenma with [`$res->upgrade()`](../reference/Waypoint/Http/Response.md#upgrade): a streamed body, server-sent events or a WebSocket. Everything Waypoint does before the controller still applies: authentication, roles, CSRF, guards and middlewares. Tenma's response then replaces Waypoint's.

```php
<?php

namespace Bakery\Controllers;

use Bakery\Repositories\{Orders, Products};
use Http\{EventStream, Response as TenmaResponse, Stream, WebSocket};
use Waypoint\Attributes\{Authenticated, Controller, Get, Inject};
use Waypoint\Exceptions\ForbiddenException;
use Waypoint\Http\{Request, Response};

#[Controller('/backoffice/stream')]
#[Authenticated]
final class StreamController
{
    #[Inject]
    private Orders $orders;

    #[Inject]
    private Products $products;

    /** The year's orders as CSV, written while they are read. */
    #[Get('/orders.csv')]
    public function orders(Response $res): void
    {
        $res->upgrade(TenmaResponse::stream(function (Stream $out) {
            $out->write("code,pickup,total\n");
            foreach ($this->orders->recent(365) as $order) {
                $out->write("$order->code,$order->pickupDate," . $order->totalCents() / 100 . "\n");
            }
        }, headers: ['content-type' => 'text/csv']));
    }

    /** What is left of each product, every ten seconds. */
    #[Get('/stock')]
    public function stock(Response $res): void
    {
        $res->upgrade(TenmaResponse::events(function (EventStream $events) {
            while (true) {   // ends when the tab closes: send() then throws ConnectionClosed
                $left = array_map(fn ($p) => ['slug' => $p->slug, 'stock' => $p->stock], $this->products->all());
                $events->send(json_encode($left, JSON_THROW_ON_ERROR), event: 'stock');
                \Threading\Task::delay(10);
            }
        }));
    }

    /** A chat between the counter and the oven. */
    #[Get('/chat')]
    public function chat(Request $req, Response $res): void
    {
        if (($req->headers['Origin'] ?? '') !== 'https://shop.sonnenkorn.test') {
            throw new ForbiddenException('Unknown origin.');
        }
        $name = $req->jwt['name'];
        $res->upgrade(TenmaResponse::websocket(function (WebSocket $socket) use ($name) {
            while (($message = $socket->receive()) !== null) {
                $socket->send("$name: $message");
            }
        }));
    }
}
```

The Bakery itself has no such routes: its order board and order page are live components, which keep their WebSocket through Waypoint Live.

- A stream keeps the headers and cookies set on the Waypoint response (CORS, security headers, `X-Request-ID`); its own headers and status win, and `Content-Type`, `Content-Length` and `Content-Encoding` come from the stream.
- A WebSocket answers with its handshake only.
- The handler runs after the request has passed through Waypoint, with the request's context restored: the request id on log lines, the CSRF token and every `FiberLocal`. Values from the request itself, such as the JWT payload, go in through `use`.
- If the controller or a middleware throws after `upgrade()`, the error response goes out and the upgrade is dropped.
- A WebSocket handshake carries no CSRF header. A WebSocket route that acts for a signed-in user checks the `Origin` header itself, as above.

Under PHP-FPM and `php -S` an upgrade is ignored. Tenma's guides on [streams](https://github.com/systonia/Tenma/blob/main/docs/guides/streams.md) and [WebSockets](https://github.com/systonia/Tenma/blob/main/docs/guides/websockets.md) cover the handlers themselves.

## Uploads

Uploaded files arrive as [`UploadedFile`](../reference/Waypoint/Http/UploadedFile.md), as under FPM. `moveTo()` writes through Tenma without blocking the worker. The body size limit is Tenma's `--max-body` (10 MB by default).

See also: [`Bridge`](../reference/Waypoint/Tenma/Bridge.md), [`FiberLocal`](../reference/Waypoint/Support/FiberLocal.md), [Runtimes](runtimes.md), [Deployment](deployment.md).
