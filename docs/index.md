---
title: Waypoint
description: A small PHP 8.5 framework for JSON APIs and server-rendered pages, driven by attributes on your own classes.
order: 1
---

Waypoint builds a web application out of plain PHP classes and attributes. A controller method with `#[Get('/customers/{id}')]` is a route; a parameter with `#[Body]` receives the request body as a validated object; a property with `#[Inject]` receives a service. There is no route file, no service configuration and no generated code to keep in sync: what the attributes say is what the app does.

The same app answers JSON and HTML. A method that returns an array answers JSON; one that returns a `View` renders a PHP template inside a layout; one that returns a `Redirect` redirects. Errors become [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) problem responses, CSRF protection, security headers, JWT sign-in and an OpenAPI document are a few lines of configuration away.

Waypoint runs wherever PHP runs: under PHP-FPM or `php -S`, where every request starts the script anew, and under [Tenma](https://github.com/systonia/Tenma), where one process keeps the app in memory and serves every request in a fiber of its own. Through `Waypoint\Psr\Handler` it is also a PSR-15 request handler for any PSR-7 server.

## Install

Waypoint needs PHP 8.5 and Composer:

```bash
composer require systonia/waypoint
```

Its only runtime dependencies are the PSR interfaces for containers, HTTP messages and logging. Monolog, PHPUnit and Tenma are optional.

## A first app

A project with one controller looks like this:

```text
my-app/
├── composer.json        "autoload": {"psr-4": {"App\\": "src/"}}
├── public/
│   └── index.php        the front controller
└── src/
    └── Controllers/
        └── GreetingController.php
```

The controller answers `GET /hello/{name}`:

```php
<?php

namespace App\Controllers;

use Waypoint\Attributes\{Controller, Get};

#[Controller('/hello')]
final class GreetingController
{
    #[Get('/{name}')]
    public function greet(string $name, bool $shout = false): array
    {
        $greeting = "Hello, $name!";
        return ['greeting' => $shout ? strtoupper($greeting) : $greeting];
    }
}
```

`#[Controller('/hello')]` puts every route of the class under `/hello`; `#[Get('/{name}')]` adds the method's own path. `$name` comes from the `{name}` placeholder and `$shout` from the query string (`?shout=yes`). The returned array goes out as JSON.

The front controller creates the app, configures it, attaches the controllers and serves:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\GreetingController;
use Waypoint\Options\FileSystemOptions;
use Waypoint\Waypoint;

$app = Waypoint::create();

$app->configure(function (FileSystemOptions $fs) {
    $fs->publicDirectory = __DIR__;
});

$app->attach([GreetingController::class]);
$app->serve();
```

`configure()` hands the closure the app's instance of every Options class its parameters name; here it serves the files in `public/` (images, a `robots.txt`) next to the routes. `attach()` reads the attributes of the controllers and of every service they inject. `serve()` is the last line for every runtime.

Run it with PHP's built-in server or with Tenma:

```bash
php -S localhost:8080 public/index.php
tenma public/index.php          # listens on port 8080, or on PORT from the environment
```

```text
$ curl localhost:8080/hello/Ada
{"greeting":"Hello, Ada!"}
$ curl 'localhost:8080/hello/Ada?shout=yes'
{"greeting":"HELLO, ADA!"}
$ curl 'localhost:8080/hello/Ada?shout=maybe'
{"type":"about:blank","title":"Validation failed","status":422,"detail":"Validation failed","errors":{"shout":"Expected a value of type bool."}}
```

`maybe` is no boolean, so the request is answered with `422` and the field in `errors`, before the controller runs. A `POST /hello/Ada` answers `405 Method Not Allowed` with `Allow: GET, HEAD`, and an unknown path `404`.

## How these docs are organised

- **[Guides](guides/index.md)** explain one part of Waypoint each, with complete examples: setup and configuration, routing, requests and responses, binding and validation, dependency injection, middleware, authentication, views, logging, caching, OpenAPI, plugins, the runtimes, testing, security and deployment.
- **[Reference](reference/index.md)** has one page per class, interface, trait and enum, generated from the code with `tenma docs`: every attribute, Options class and method with its signature and description.

Start with [Application setup](guides/application.md), [Routing](guides/routing.md) and [Binding and validation](guides/binding.md); together they cover what every Waypoint app uses.
