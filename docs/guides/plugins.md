---
title: Plugins
description: Add features as packages through Waypoint's hooks, with their own Options, attributes, guards, argument binders, renderers, view helpers and assets.
order: 140
---

Features beyond the core (translations, flash messages, charts, exports) are plugins: Composer packages that extend Waypoint through a fixed set of hooks. An app registers each plugin with one line before `attach()`:

```php
$app->plugin(new \Waypoint\I18n\I18nPlugin());
$app->configure(fn (\Waypoint\I18n\I18nOptions $i18n) => $i18n->default = 'de');
$app->attach([PagesController::class]);
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
public function boot(Container $container, ApiKeyOptions $options): void
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

`acme/api-keys` protects routes with API keys. A route declares the scope it needs with an attribute, the guard checks the key, and a controller parameter can receive the client the key belongs to:

```php
<?php

namespace Acme\ApiKeys;

use Attribute;

/** The API key scope a route needs, on a controller or a route method. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final class RequireApiKey
{
    public function __construct(public string $scope)
    {
    }
}
```

```php
<?php

namespace Acme\ApiKeys;

use Attribute;

/** Binds the name of the client whose API key the request carries. */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class ApiClient
{
}
```

```php
<?php

namespace Acme\ApiKeys;

/** The plugin's settings, configured by the app with $app->configure(). */
final class ApiKeyOptions
{
    /** @var array<string, array{client: string, scopes: list<string>}> key => its client and scopes */
    public array $keys = [];

    public string $header = 'X-Api-Key';
}
```

```php
<?php

namespace Acme\ApiKeys;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Waypoint\Exceptions\{ForbiddenException, UnauthorizedException};
use Waypoint\Http\{Request, Response};
use Waypoint\Plugin\{ArgumentBinder, Guard, RouteAttributeCompiler};

/** Reads #[RequireApiKey] at compile time, checks the key per request, and binds #[ApiClient]. */
final class KeyCheck implements RouteAttributeCompiler, Guard, ArgumentBinder
{
    public function __construct(private ApiKeyOptions $options)
    {
    }

    public function compile(ReflectionClass $controller, ReflectionMethod $method): array
    {
        $attribute = $method->getAttributes(RequireApiKey::class)[0] ?? $controller->getAttributes(RequireApiKey::class)[0] ?? null;
        return $attribute === null ? [] : ['scope' => $attribute->newInstance()->scope];
    }

    public function check(array $plan, Request $req, Response $res): void
    {
        if (!isset($plan['scope'])) {
            return;
        }
        $key = $this->keyOf($req) ?? throw new UnauthorizedException('Missing or unknown API key.');
        if (!in_array($plan['scope'], $key['scopes'], true)) {
            throw new ForbiddenException("This API key lacks the scope {$plan['scope']}.");
        }
    }

    public function plan(ReflectionParameter $parameter): ?array
    {
        return $parameter->getAttributes(ApiClient::class) !== [] ? ['client' => true] : null;
    }

    public function resolve(array $plan, Request $req, Response $res, array $routeParams): mixed
    {
        return $this->keyOf($req)['client'] ?? null;
    }

    /** @return array{client: string, scopes: list<string>}|null */
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

namespace Acme\ApiKeys;

use Waypoint\Plugin\PluginBase;

/** API keys with scopes for routes marked #[RequireApiKey]. */
final class ApiKeysPlugin extends PluginBase
{
    private KeyCheck $check;

    public function name(): string
    {
        return 'api-keys';
    }

    public function boot(ApiKeyOptions $options): void
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
use Acme\ApiKeys\{ApiKeyOptions, ApiKeysPlugin};

$app->plugin(new ApiKeysPlugin());
$app->configure(function (ApiKeyOptions $apiKeys, Environment $env) {
    $apiKeys->keys = [
        $env->get('PARTNER_API_KEY') => ['client' => 'partner', 'scopes' => ['orders.read']],
    ];
});
$app->attach([PartnerController::class]);
```

and uses it in a controller:

```php
<?php

namespace App\Controllers;

use Acme\ApiKeys\{ApiClient, RequireApiKey};
use Waypoint\Attributes\{Controller, Get, SkipCsrf};

#[Controller('/partner')]
#[SkipCsrf]
final class PartnerController
{
    #[Get('/orders')]
    #[RequireApiKey('orders.read')]
    public function orders(#[ApiClient] string $client): array
    {
        return ['client' => $client, 'orders' => []];
    }
}
```

A request without a key answers `401`, one with a key lacking the scope `403`, and a valid one reaches the controller with `$client === 'partner'`. Because the compiler's result is part of the compiled routes, the attribute is read once, not on every request.

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
    $this->app()->plugin(new ApiKeysPlugin());
    $this->app()->configure(fn (ApiKeyOptions $keys) => $keys->keys = ['k1' => ['client' => 'partner', 'scopes' => ['orders.read']]]);
    $this->app()->attach([PartnerController::class]);
}

public function testTheKeyDecides(): void
{
    $this->get('/partner/orders')->assertStatus(401);
    $this->get('/partner/orders', ['X-Api-Key' => 'k1'])->assertJsonPath('client', 'partner');
}
```

See also: [`Plugin`](../reference/Waypoint/Plugin/Plugin.md), [`PluginBase`](../reference/Waypoint/Plugin/PluginBase.md), the [`Waypoint\Plugin` namespace](../reference/Waypoint/Plugin/index.md).
