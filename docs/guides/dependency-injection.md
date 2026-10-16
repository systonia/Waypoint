---
title: Dependency injection
description: Inject services with #[Inject], register services that need constructor arguments, and keep per-request state out of shared services.
order: 50
---

A controller declares what it needs as properties with `#[Inject]`. Waypoint creates each of those classes once and sets the property before the controller runs:

```php
<?php

namespace App\Services;

use Waypoint\Attributes\Inject;
use Waypoint\Environment;
use Waypoint\Logger;

final class Newsletter
{
    #[Inject]
    private Environment $env;

    #[Inject]
    private Logger $logger;

    public function subscribe(string $email): string
    {
        $list = $this->env->get('NEWSLETTER_LIST', 'main');
        $this->logger->info('Newsletter subscription', ['list' => $list]);
        return $list;
    }
}
```

```php
<?php

namespace App\Controllers;

use App\Services\Newsletter;
use Waypoint\Attributes\{Controller, Inject, Post};
use Waypoint\Http\Request;

#[Controller('/newsletter')]
final class NewsletterController
{
    #[Inject]
    private Newsletter $newsletter;

    #[Post('/subscribe')]
    public function subscribe(Request $req): array
    {
        $list = $this->newsletter->subscribe((string) $req->body('email', ''));
        return ['list' => $list];
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

A service that needs arguments, such as a database connection or an API key, is created in the front controller and registered before `attach()`:

```php
<?php

namespace App\Services;

final class PaymentGateway
{
    public function __construct(private string $apiKey, private string $endpoint)
    {
    }

    public function charge(int $cents): string
    {
        return "charged $cents at {$this->endpoint}";
    }
}
```

```php
$app->configure(fn (EnvironmentOptions $env) => $env->load(dirname(__DIR__)));

$env = $app->get(Environment::class);
$app->getContainer()->set(new PaymentGateway(
    apiKey: $env->get('PAYMENT_API_KEY'),
    endpoint: $env->get('PAYMENT_ENDPOINT', 'https://payments.example.com'),
));

$app->attach([CheckoutController::class]);
```

[`Container::set()`](../reference/Waypoint/Container.md#set) registers the instance under its class name; every `#[Inject] private PaymentGateway $payments` then receives it. A service that is only ever reached through `$app->get()` or the container, not through `#[Inject]`, is created on first use as well.

## Controllers per request, services per process

Controllers and `#[Middleware]` classes are created anew for every request; a controller registered in the container is cloned per request. A property on a controller therefore belongs to one request.

Services are the opposite: one instance per process. Under PHP-FPM a process handles one request, so this goes unnoticed. Under [Tenma](tenma.md), one process serves request after request and runs several of them interleaved while they wait for I/O. Whatever a service keeps in a property is visible to every other request. **A service must not keep per-request state in its properties:**

```php
final class CurrentUser
{
    private ?array $user = null;

    public function signIn(Request $req): void { $this->user = $req->jwt; }   // wrong: shared by every request
    public function get(): ?array { return $this->user; }                     // may return another request's user
}
```

The same goes for memoising per request (`$this->current ??= …`), switching shared state per request (`$this->locale = …`, `$this->tenant = …`), collecting per request (messages, a query counter) and objects built up across several calls.

Fine in a service: configuration and dependencies that don't change after boot, caches whose content holds for everyone (compiled templates, exchange rates; nothing user-specific unless the user is part of the key), and helpers that get everything they need as arguments.

Instead of per-request state in a service:

- pass it as an argument: `$this->orders->forUser($req->jwt['sub'])` rather than `$this->orders->forCurrentUser()`;
- keep it on the request, the way `useJwt()` puts the user into `$req->jwt`;
- or, for a value that really has to be implicitly "current", hold it in a [`FiberLocal`](../reference/Waypoint/Support/FiberLocal.md): one value per request, also when requests run interleaved.

```php
<?php

namespace App\Services;

use Waypoint\Support\FiberLocal;

final class CurrentTenant
{
    /** @var FiberLocal<string> */
    private FiberLocal $tenant;

    public function __construct()
    {
        $this->tenant = new FiberLocal();
    }

    public function set(?string $tenant): void
    {
        $this->tenant->set($tenant);
    }

    public function get(): string
    {
        return $this->tenant->get() ?? 'default';
    }
}
```

Set the value at the start of every request, in a middleware, rather than resetting it afterwards: a WebSocket or stream handler runs after the request's middlewares have finished. Waypoint's own request id and CSRF token are kept this way, and under Tenma a `FiberLocal` value also follows the request into the tasks it starts (`Tenma::async()`).

```php
<?php

namespace App\Middleware;

use App\Services\CurrentTenant;
use Waypoint\Attributes\Inject;
use Waypoint\Http\{MiddlewareBase, Request, Response};

final class ResolveTenant extends MiddlewareBase
{
    #[Inject]
    private CurrentTenant $tenant;

    protected function before(Request $req, Response $res): bool
    {
        $host = $req->headers['Host'] ?? '';
        $this->tenant->set(str_ends_with($host, '.example.com') ? substr($host, 0, -strlen('.example.com')) : null);
        return true;
    }
}
```

```php
$app->use(new ResolveTenant());
```

Waypoint can't enforce this rule. Test an app that is meant to run under Tenma under Tenma, with parallel requests.

## Outside controllers

[`$app->get()`](../reference/Waypoint/App.md#get) returns the container's instance of a class from the front controller or a script; [`$app->getContainer()`](../reference/Waypoint/App.md#getcontainer) is the [`Container`](../reference/Waypoint/Container.md) itself, a PSR-11 container. Prefer `#[Inject]` everywhere else.

See also: [`Inject`](../reference/Waypoint/Attributes/Inject.md), [`Container`](../reference/Waypoint/Container.md), [`FiberLocal`](../reference/Waypoint/Support/FiberLocal.md), [Tenma](tenma.md).
