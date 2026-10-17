---
title: Dependency injection
description: "Inject services with #[Inject], register services that need constructor arguments, and keep per-request state out of shared services."
order: 50
---

A controller declares what it needs as properties with `#[Inject]`. Waypoint creates each of those classes once and sets the property before the controller runs. The bakery's weather service, shortened from `src/Services/Weather.php`:

```php
<?php

namespace Bakery\Services;

use Bakery\Models\WeatherDay;
use RuntimeException;
use Waypoint\Attributes\Inject;
use Waypoint\Environment;
use Waypoint\Logger;

final class Weather
{
    #[Inject]
    private Environment $env;

    #[Inject]
    private Logger $logger;

    /** @return array<string, WeatherDay>|null Y-m-d => the forecast; null when unavailable */
    public function week(): ?array
    {
        $url = rtrim($this->env->string('WEATHER_URL', 'https://api.open-meteo.com'), '/') . '/v1/forecast?…';
        try {
            $response = \Tenma::fetch($url, timeout: $this->env->float('API_TIMEOUT', 2.5));
            // … one WeatherDay per day of the answer
        } catch (RuntimeException $e) {
            $this->logger->warning('weather: ' . $e->getMessage());
            return null;
        }
    }
}
```

The shop's controller injects it next to the repositories it reads (from `src/Controllers/ShopController.php`):

```php
<?php

namespace Bakery\Controllers;

use Bakery\Repositories\{OpeningHours, Products};
use Bakery\Services\Weather;
use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Http\View;

#[Controller]
final class ShopController
{
    #[Inject]
    private Products $products;

    #[Inject]
    private OpeningHours $hours;

    #[Inject]
    private Weather $weather;

    #[Get('/')]
    public function catalog(): View
    {
        return new View('Shop/Catalog', $this->products->all());
    }
}
```

There is no service attribute and no service list: a class becomes a service by being reachable through `#[Inject]` from an attached controller, directly or through other services. `attach()` follows those properties, creates every class it finds and sets their `#[Inject]` properties. Two services may inject each other.

## What can be injected

- **Services**: any class whose constructor needs no arguments. It is created on first use and then shared: every controller and service that injects it gets the same instance.
- **Framework services**: [`Environment`](../reference/Waypoint/Environment.md), [`Logger`](../reference/Waypoint/Logger.md), [`Csrf`](../reference/Waypoint/Csrf.md), and every Options class.
- **Per request**: `Request`, `Response` and [`Router`](../reference/Waypoint/Router.md), in controllers and `#[Middleware]` classes only. A service never gets them; pass the request to it as an argument.
- **Registered instances**: objects you created yourself and registered in the container, see [below](#services-with-constructor-arguments).

`#[Inject]` works on properties of controllers, services, `#[Middleware]` classes, middleware instances passed to `$app->use()`, CLI task managers and views. The property's type names what is injected: a class, or an interface bound with [`Container::bind()`](../reference/Waypoint/Container.md#bind), which is how plugins provide services to each other. A property typed with an interface nobody bound stays unset.

`#[Inject]` on a constructor parameter makes its class known to the container, but the constructor is still called without arguments. Use properties.

## Services with constructor arguments

A service that needs arguments, such as a database connection or an API key, is created in the front controller and registered before `attach()`. The bakery opens one pool of Postgres connections in `app.php` and registers it:

```php
$app->configure(fn (EnvironmentOptions $env) => $env->load(__DIR__));
$env = $app->get(Environment::class);

// One pool of Postgres connections per worker, shared by every service that injects Postgres
$app->getContainer()->set(Postgres::connect($env->string('DATABASE_URL', 'postgres://localhost/bakery')));

$app->attach([ShopController::class, SessionController::class, BackofficeController::class, ApiController::class]);
```

[`Container::set()`](../reference/Waypoint/Container.md#set) registers the instance under its class name; every `#[Inject] private Postgres $db` in the repositories then receives it. A service that is only ever reached through `$app->get()` or the container, not through `#[Inject]`, is created on first use as well.

## Controllers per request, services per process

Controllers and `#[Middleware]` classes are created anew for every request; a controller registered in the container is cloned per request. A property on a controller therefore belongs to one request.

Services are the opposite: one instance per process. Under PHP-FPM a process handles one request, so this goes unnoticed. Under [Tenma](tenma.md), one process serves request after request and runs several of them interleaved while they wait for I/O. Whatever a service keeps in a property is visible to every other request. **A service must not keep per-request state in its properties:**

```php
final class Cart
{
    private ?string $cartId = null;

    public function open(Request $req): void { $this->cartId = $req->cookies['cart'] ?? null; }   // wrong: shared by every request
    public function lines(): array { return $this->carts->load($this->cartId); }                // may show another customer's cart
}
```

The same goes for memoising per request (`$this->current ??= …`), switching shared state per request (`$this->locale = …`, `$this->shop = …`), collecting per request (messages, a query counter) and objects built up across several calls.

Fine in a service: configuration and dependencies that don't change after boot, caches whose content holds for everyone (compiled templates, exchange rates; nothing user-specific unless the user is part of the key), and helpers that get everything they need as arguments.

Instead of per-request state in a service:

- pass it as an argument: `$this->carts->load($req->cookies['cart'])` rather than `$this->cart->current()`;
- keep it on the request, the way `useJwt()` puts the user into `$req->jwt` and the bakery's `CartCookie` middleware puts the cart id into `$req->cookies['cart']`;
- or, for a value that really has to be implicitly "current", hold it in a [`FiberLocal`](../reference/Waypoint/Support/FiberLocal.md): one value per request, also when requests run interleaved.

```php
<?php

namespace Bakery\Services;

use Waypoint\Support\FiberLocal;

/** The shop a request is for, when the bakery has a second one in another part of town. */
final class CurrentShop
{
    /** @var FiberLocal<string> */
    private FiberLocal $shop;

    public function __construct()
    {
        $this->shop = new FiberLocal();
    }

    public function set(?string $shop): void
    {
        $this->shop->set($shop);
    }

    public function get(): string
    {
        return $this->shop->get() ?? 'altstadt';
    }
}
```

Set the value at the start of every request, in a middleware, rather than resetting it afterwards: a WebSocket or stream handler runs after the request's middlewares have finished. Waypoint's own request id and CSRF token are kept this way, and under Tenma a `FiberLocal` value also follows the request into the tasks it starts (`Tenma::async()`).

```php
<?php

namespace Bakery\Services;

use Waypoint\Attributes\Inject;
use Waypoint\Http\{MiddlewareBase, Request, Response};

/** wiehre.sonnenkorn.test is the shop in Wiehre, every other host the one in the old town. */
final class ShopFromHost extends MiddlewareBase
{
    #[Inject]
    private CurrentShop $shop;

    protected function before(Request $req, Response $res): bool
    {
        $host = $req->headers['Host'] ?? '';
        $this->shop->set(str_ends_with($host, '.sonnenkorn.test') ? strstr($host, '.', true) : null);
        return true;
    }
}
```

```php
$app->use(new ShopFromHost());
```

Waypoint can't enforce this rule. Test an app that is meant to run under Tenma under Tenma, with parallel requests.

## PHPStan

The container writes `#[Inject]` properties, so PHPStan would report each as only read. Waypoint ships a PHPStan extension that counts them as written and initialized: with [phpstan/extension-installer](https://github.com/phpstan/extension-installer) it is loaded on its own, otherwise add it to `phpstan.neon`:

```neon
includes:
    - vendor/systonia/waypoint/extension.neon
```

The same extension takes `#[Returns]` on a method and `#[ListOf]` on a property or constructor parameter as the array's element type: PHPStan reports no missing iterable value type there, so no docblock repeats the attribute. It also reads the attribute's type and checks what goes in: see [Types and PHPStan](types.md).

## Outside controllers

[`$app->get()`](../reference/Waypoint/App.md#get) returns the container's instance of a class from the front controller or a script (and throws when it can't be built; [`$app->find()`](../reference/Waypoint/App.md#find) returns `null` then); [`$app->getContainer()`](../reference/Waypoint/App.md#getcontainer) is the [`Container`](../reference/Waypoint/Container.md) itself, a PSR-11 container. Prefer `#[Inject]` everywhere else.

See also: [`Inject`](../reference/Waypoint/Attributes/Inject.md), [`Container`](../reference/Waypoint/Container.md), [`FiberLocal`](../reference/Waypoint/Support/FiberLocal.md), [Tenma](tenma.md).
