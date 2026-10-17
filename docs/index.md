---
title: Waypoint
description: A small PHP 8.5 framework for JSON APIs and server-rendered pages, driven by attributes on your own classes.
order: 1
---

Waypoint builds a web application out of plain PHP classes and attributes. A controller method with `#[Get('/products/{slug}')]` is a route; a parameter with `#[Body]` receives the request body as a validated object; a property with `#[Inject]` receives a service. There is no route file, no service configuration and no generated code to keep in sync: what the attributes say is what the app does.

The same app answers JSON and HTML. A method that returns an array answers JSON; one that returns a `View` renders a PHP template inside a layout; one that returns a `Redirect` redirects. Errors become [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) problem responses, CSRF protection, security headers, JWT sign-in and an OpenAPI document are a few lines of configuration away.

Waypoint runs wherever PHP runs: under PHP-FPM or `php -S`, where every request starts the script anew, and under [Tenma](https://github.com/systonia/Tenma), where one process keeps the app in memory and serves every request in a fiber of its own. Through `Waypoint\Psr\Handler` it is also a PSR-15 request handler for any PSR-7 server.

## Install

Waypoint needs PHP 8.5 and Composer:

```bash
composer require systonia/waypoint
```

Its only runtime dependencies are the PSR interfaces for containers, HTTP messages and logging. Monolog, PHPUnit and Tenma are optional.

## A first app

A bakery that answers what its products cost, with one controller:

```text
bakery/
├── composer.json        "autoload": {"psr-4": {"Bakery\\": "src/"}}
├── public/
│   └── index.php        the front controller
└── src/
    └── Controllers/
        └── ProductController.php
```

The controller answers `GET /products/{slug}`:

```php
<?php

namespace Bakery\Controllers;

use Waypoint\Attributes\{Controller, Get};
use Waypoint\Exceptions\NotFoundException;

#[Controller('/products')]
final class ProductController
{
    private const PRICES = ['croissant' => 180, 'pretzel' => 120, 'sourdough-loaf' => 520];

    #[Get('/{slug}')]
    public function price(string $slug, int $quantity = 1): array
    {
        $cents = self::PRICES[$slug] ?? throw new NotFoundException("No product $slug");
        return ['product' => $slug, 'quantity' => $quantity, 'totalCents' => $cents * $quantity];
    }
}
```

`#[Controller('/products')]` puts every route of the class under `/products`; `#[Get('/{slug}')]` adds the method's own path. `$slug` comes from the `{slug}` placeholder and `$quantity` from the query string (`?quantity=6`). The returned array goes out as JSON; the `NotFoundException` answers `404`.

The front controller creates the app, configures it, attaches the controllers and serves:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

use Bakery\Controllers\ProductController;
use Waypoint\Options\FileSystemOptions;
use Waypoint\Waypoint;

$app = Waypoint::create();

$app->configure(function (FileSystemOptions $fs) {
    $fs->publicDirectory = __DIR__;
});

$app->attach([ProductController::class]);
$app->serve();
```

`configure()` hands the closure the app's instance of every Options class its parameters name; here it serves the files in `public/` (product pictures, a `robots.txt`) next to the routes. `attach()` reads the attributes of the controllers and of every service they inject. `serve()` is the last line for every runtime.

Run it with PHP's built-in server or with Tenma:

```bash
php -S localhost:8080 public/index.php
tenma public/index.php          # listens on port 8080, or on PORT from the environment
```

```text
$ curl localhost:8080/products/croissant
{"product":"croissant","quantity":1,"totalCents":180}
$ curl 'localhost:8080/products/croissant?quantity=6'
{"product":"croissant","quantity":6,"totalCents":1080}
$ curl 'localhost:8080/products/croissant?quantity=lots'
{"type":"about:blank","title":"Validation failed","status":422,"detail":"Validation failed","errors":{"quantity":"Expected a value of type int."}}
$ curl localhost:8080/products/baguette
{"type":"about:blank","title":"Not Found","status":404,"detail":"No product baguette"}
```

`lots` is no integer, so the request is answered with `422` and the field in `errors`, before the controller runs. A `POST /products/croissant` answers `405 Method Not Allowed` with `Allow: GET, HEAD`, and an unknown path `404`.

The [Sonnenkorn bakery](https://github.com/systonia/Waypoint-Bakery), the example app these docs take their examples from, grows this into a whole shop: products from Postgres, a cart and a checkout, an order board for the people in the bakery, a back office with sign-in, sales charts and a JSON API with bearer tokens and an OpenAPI document.

## How these docs are organised

- **[Guides](guides/index.md)** explain one part of Waypoint each, with complete examples: setup and configuration, routing, requests and responses, binding and validation, dependency injection, middleware, authentication, views, logging, caching, OpenAPI, plugins, the runtimes, testing, security and deployment.
- **[Reference](reference/index.md)** has one page per class, interface, trait and enum, generated from the code with `tenma docs`: every attribute, Options class and method with its signature and description.

Start with [Application setup](guides/application.md), [Routing](guides/routing.md) and [Binding and validation](guides/binding.md); together they cover what every Waypoint app uses.
