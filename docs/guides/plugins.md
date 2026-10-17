---
title: Plugins
description: Add features as packages through Waypoint's hooks, with their own Options, attributes, guards, argument binders, renderers, view helpers and assets.
order: 140
---

Features beyond the core (translations, flash messages, charts, exports) are plugins: Composer packages that extend Waypoint through a fixed set of hooks. An app registers each plugin with one line before `attach()`. The Bakery's `app.php` uses five of them:

```php
use Waypoint\Charts\ChartsPlugin;
use Waypoint\Export\ExportPlugin;
use Waypoint\Flash\FlashPlugin;
use Waypoint\I18n\{I18nOptions, I18nPlugin};
use Waypoint\Live\LivePlugin;

$app->plugin(new LivePlugin([CartBadge::class, AddToCart::class, Checkout::class, OrderBoard::class]));
$app->plugin(new I18nPlugin());
$app->plugin(new FlashPlugin());
$app->plugin(new ChartsPlugin());
$app->plugin(new ExportPlugin());

$app->configure(function (I18nOptions $i18n) {
    $i18n->directory = __DIR__ . '/lang';
    $i18n->default = 'en';
    $i18n->supported = ['en', 'de'];
});

$app->attach([ShopController::class, SessionController::class, BackofficeController::class, ApiController::class]);
```

| Package | Adds |
|---|---|
| `systonia/waypoint-i18n` | `$this->t('Source text')` in views, JSON catalogs, a locale per request, translated validation messages |
| `systonia/waypoint-flash` | flash messages across a redirect, shown once |
| `systonia/waypoint-charts` | bar and line charts as server-rendered SVG |
| `systonia/waypoint-export` | downloads as CSV, JSON Lines, HTML, XML, XLSX or ODS |
| `systonia/waypoint-export-fixed` | fixed-width record files, as an extension of waypoint-export |
| `systonia/waypoint-live` | live components over a WebSocket, under Tenma |

The rest of this guide is about writing one.

## The plugin class

A plugin is one class implementing [`Plugin`](../reference/Waypoint/Plugin/Plugin.md); extending [`PluginBase`](../reference/Waypoint/Plugin/PluginBase.md) gives every part a default that does nothing. It declares what it adds and nothing else:

| Method | Declares |
|---|---|
| `name()` | a short unique name; it keys the plugin's data in the compiled routes |
| `requires()` | plugin classes that must be registered before this one |
| `classes()` | controllers and task managers attached with the app's own |
| `middlewares()` | app-wide middlewares, added at `attach()` after the app's |
| `hooks()` | objects implementing the hook interfaces below |
| `cacheInputs()` | `[path => mtime]` of further files whose change must rebuild the [route cache](caching.md); the plugin's class and its hook classes are watched already |
| `boot(…)` | optional; runs once at `attach()`, before the routes are compiled |

`boot()` receives what its parameters' types name: the [`Container`](../reference/Waypoint/Container.md), the plugin's own Options class, any service. It reads its options there and binds services:

```php
public function boot(Container $container, TillKeyOptions $options): void
{
    // read $options, create the hook objects, register or bind services in $container
}
```

Registering the same plugin twice, two plugins with the same name, or a plugin before one it requires is an error at `plugin()`.

## Hooks

| Hook (`Waypoint\Plugin\…`) | Runs | Typical use |
|---|---|---|
| [`Endpoint`](../reference/Waypoint/Plugin/Endpoint.md) | before routing, after public files; without route checks | health checks, metrics, an SSO callback |
| [`Fallback`](../reference/Waypoint/Plugin/Fallback.md) | when no route matched, before the `404`; without route checks | CMS pages, short links |
| [`RouteAttributeCompiler`](../reference/Waypoint/Plugin/RouteAttributeCompiler.md) | once per route when the routes are compiled; the result is cached | reading the plugin's own attribute |
| [`Guard`](../reference/Waypoint/Plugin/Guard.md) | per request, after authentication and CSRF, before middlewares and the controller | enforcing that attribute |
| [`ResponseHook`](../reference/Waypoint/Plugin/ResponseHook.md) | after the result was rendered; not when a guard or the controller threw | audit trails, rewriting a page |
| [`ArgumentBinder`](../reference/Waypoint/Plugin/ArgumentBinder.md) | binds controller parameters with the plugin's own attribute or type, after the core attributes | `#[File]`, a table request |
| [`Renderer`](../reference/Waypoint/Plugin/Renderer.md) | renders the plugin's own return types, after `View` and `Redirect` | exports, PDFs, charts |
| [`OpenApiExtension`](../reference/Waypoint/Plugin/OpenApiExtension.md) | describes the plugin's parameters and return types in the [OpenAPI](openapi.md) document | `text/csv` for an export |
| [`ViewHelper`](../reference/Waypoint/Plugin/ViewHelper.md) | functions templates call as `$this->name()`; one that returns markup returns a `Waypoint\Html`, or `{{ }}` escapes it | `$this->t('key')` |
| [`ClientAsset`](../reference/Waypoint/Plugin/ClientAsset.md) | CSS and JS files, served content-hashed; rendered by `$this->pluginAssetTags()` | a component's script |

An `Endpoint` or `Fallback` that changes state or shows private data checks the request itself: `#[Authenticated]` and CSRF don't apply there. A plugin script loaded through `ClientAsset` runs after `waypoint.js` and can add its own `wp-*` attributes with `Waypoint.directive()`. Two plugins offering a view helper of the same name are a boot error.

## A complete plugin

The Bakery's till asks for the day's orders. A small plugin, `Bakery\TillKeys`, lets it in with a key of its own instead of a staff sign-in: a route declares the scope it needs with an attribute, the guard checks the key, and a controller parameter can receive the till the key belongs to:

```php
<?php

namespace Bakery\TillKeys;

use Attribute;

/** The till key scope a route needs, on a controller or a route method. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class RequireTillKey
{
    public function __construct(public string $scope)
    {
    }
}
```

```php
<?php

namespace Bakery\TillKeys;

use Attribute;

/** Binds the name of the till whose key the request carries. */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Till
{
}
```

```php
<?php

namespace Bakery\TillKeys;

/** The plugin's settings, configured by the app with $app->configure(). */
final class TillKeyOptions
{
    /** @var array<string, array{till: string, scopes: list<string>}> key => its till and scopes */
    public array $keys = [];

    public string $header = 'X-Till-Key';
}
```

```php
<?php

namespace Bakery\TillKeys;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Waypoint\Exceptions\{ForbiddenException, UnauthorizedException};
use Waypoint\Http\{Request, Response};
use Waypoint\Plugin\{ArgumentBinder, Guard, RouteAttributeCompiler};

/** Reads #[RequireTillKey] at compile time, checks the key per request, and binds #[Till]. */
final class KeyCheck implements RouteAttributeCompiler, Guard, ArgumentBinder
{
    public function __construct(private TillKeyOptions $options)
    {
    }

    public function compile(ReflectionClass $controller, ReflectionMethod $method): array
    {
        $attribute = $method->getAttributes(RequireTillKey::class)[0] ?? $controller->getAttributes(RequireTillKey::class)[0] ?? null;
        return $attribute === null ? [] : ['scope' => $attribute->newInstance()->scope];
    }

    public function check(array $plan, Request $req, Response $res): void
    {
        if (!isset($plan['scope'])) {
            return;
        }
        $key = $this->keyOf($req) ?? throw new UnauthorizedException('Missing or unknown till key.');
        if (!in_array($plan['scope'], $key['scopes'], true)) {
            throw new ForbiddenException("This till key lacks the scope {$plan['scope']}.");
        }
    }

    public function plan(ReflectionParameter $parameter): ?array
    {
        return $parameter->getAttributes(Till::class) !== [] ? ['till' => true] : null;
    }

    public function resolve(array $plan, Request $req, Response $res, array $routeParams): mixed
    {
        return $this->keyOf($req)['till'] ?? null;
    }

    /** @return array{till: string, scopes: list<string>}|null */
    private function keyOf(Request $req): ?array
    {
        foreach ($req->headers as $name => $value) {
            if (strcasecmp($name, $this->options->header) === 0) {
                return $this->options->keys[$value] ?? null;
            }
        }
        return null;
    }
}
```

```php
<?php

namespace Bakery\TillKeys;

use Waypoint\Plugin\PluginBase;

/** Till keys with scopes for routes marked #[RequireTillKey]. */
final class TillKeysPlugin extends PluginBase
{
    private KeyCheck $check;

    public function name(): string
    {
        return 'till-keys';
    }

    public function boot(TillKeyOptions $options): void
    {
        $this->check = new KeyCheck($options);
    }

    public function hooks(): array
    {
        return [$this->check];
    }
}
```

The app registers and configures it:

```php
use Bakery\TillKeys\{TillKeyOptions, TillKeysPlugin};
use Waypoint\Environment;

$app->plugin(new TillKeysPlugin());
$app->configure(function (TillKeyOptions $tills, Environment $env) {
    $tills->keys = [
        $env->string('COUNTER_TILL_KEY') => ['till' => 'counter', 'scopes' => ['orders.read']],
    ];
});
$app->attach([TillController::class]);
```

and uses it in a controller:

```php
<?php

namespace Bakery\Controllers;

use Bakery\Repositories\Orders;
use Bakery\TillKeys\{RequireTillKey, Till};
use Waypoint\Attributes\{Controller, Get, Inject, SkipCsrf};

#[Controller('/till')]
#[SkipCsrf]
final class TillController
{
    #[Inject]
    private Orders $orders;

    #[Get('/orders')]
    #[RequireTillKey('orders.read')]
    public function orders(#[Till] string $till): array
    {
        return ['till' => $till, 'orders' => $this->orders->open()];
    }
}
```

A request without a key answers `401`, one with a key lacking the scope `403`, and a valid one reaches the controller with `$till === 'counter'`. Because the compiler's result is part of the compiled routes, the attribute is read once, not on every request.

## Rules for plugins

- **Only through hooks.** A plugin extends Waypoint through the interfaces above, `Container::bind()` and the core's extension interfaces. It never reaches into the core's internals or another plugin's.
- **Its own Options.** A plugin has at most one Options class, which the app configures. The plugin reads it in `boot()` and never sets the core's or another plugin's options.
- **Offer implementations with `bind()`.** A plugin that implements a core interface binds it: the i18n plugin binds [`Waypoint\Validation\Messages`](../reference/Waypoint/Validation/Messages.md) to translate validation messages; a plugin that knows permissions binds [`PermissionProvider`](../reference/Waypoint/PermissionProvider.md), used unless the app set one in `PermissionOptions`.

  ```php
  public function boot(Container $container, I18nOptions $options): void
  {
      $translator = new Translator($options);
      $container->set($translator);
      $container->bind(Messages::class, $translator);
  }
  ```

- **Extending another plugin** goes through a registry service that plugin offers, never by changing its options or classes. `waypoint-export-fixed` adds its file format to `waypoint-export` this way, and names it in `requires()`.
- **Per-request state in a `FiberLocal`.** A hook object exists once per process. A locale, a user or queued messages belong in a [`FiberLocal`](../reference/Waypoint/Support/FiberLocal.md), set at the start of every request (see [Dependency injection](dependency-injection.md#controllers-per-request-services-per-process)). The handler of a WebSocket or stream only sees what is in a `FiberLocal`.

## Testing a plugin

The [testing kit](testing.md) drives requests through an app with the plugin registered; Waypoint's own plugins are tested this way:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->app()->plugin(new TillKeysPlugin());
    $this->app()->configure(fn (TillKeyOptions $tills) => $tills->keys = ['k1' => ['till' => 'counter', 'scopes' => ['orders.read']]]);
    $this->app()->attach([TillController::class]);
}

public function testTheKeyDecides(): void
{
    $this->get('/till/orders')->assertStatus(401);
    $this->get('/till/orders', ['X-Till-Key' => 'k1'])->assertJsonPath('till', 'counter');
}
```

See also: [`Plugin`](../reference/Waypoint/Plugin/Plugin.md), [`PluginBase`](../reference/Waypoint/Plugin/PluginBase.md), the [`Waypoint\Plugin` namespace](../reference/Waypoint/Plugin/index.md).
