---
title: "Waypoint"
description: "The classes, interfaces, traits, enums and functions in Waypoint."
order: 200
generated: true
---

## Namespaces

- [`Waypoint\Attributes`](Attributes/index.md)
- [`Waypoint\Enums`](Enums/index.md)
- [`Waypoint\Exceptions`](Exceptions/index.md)
- [`Waypoint\Http`](Http/index.md)
- [`Waypoint\OpenAPI`](OpenAPI/index.md)
- [`Waypoint\Options`](Options/index.md)
- [`Waypoint\Plugin`](Plugin/index.md)
- [`Waypoint\Psr`](Psr/index.md)
- [`Waypoint\Routing`](Routing/index.md)
- [`Waypoint\Support`](Support/index.md)
- [`Waypoint\Tenma`](Tenma/index.md)
- [`Waypoint\Testing`](Testing/index.md)
- [`Waypoint\Validation`](Validation/index.md)
- [`Waypoint\View`](View/index.md)

## Types

- [`App`](App.md) · class: The application: owns the container, the app-level middleware pipe, the exception handlers, and (after attach()) the Router.
- [`Container`](Container.md) · class: A class-name-keyed singleton registry; a class with no required constructor arguments is constructed on first get().
- [`Csrf`](Csrf.md) · class: Stateless double-submit-cookie CSRF protection.
- [`Environment`](Environment.md) · class: #[Inject]-able facade over EnvironmentOptions (resolved live from the container, so a `new Environment()` always sees the configured one).
- [`FileSystem`](FileSystem.md) · final class: The on-disk compilation cache under FileSystemOptions::$cacheDirectory: routes.php (plans + service list), meta.php (source mtimes that produced it), attributes.php (RouteCompiler::exportAllAttributes() for OpenAPI), and assets/ (content-hashed view CSS/JS, kept out of routes.php so the `require`d file stays small).
- [`FromArray`](FromArray.md) · trait: The array constructor #[Body] DTOs need (Router constructs them as `new $class($req->body())`): each key that names a publicly writable instance property is assigned, unknown keys are ignored, and a value that doesn't fit the property's type is left out and recorded as a failure (a 422, not a TypeError 500, and never cast: "false" is no true).
- [`Html`](Html.md) · readonly class: Markup a view prints as it is: `<?= $this->section('head') ?>` where section() returns Html.
- [`JWT`](JWT.md) · class: Minimal HS256 JWT: header.payload.signature, each segment base64url, signed against JWTOptions::$secret.
- [`Logger`](Logger.md) · class: PSR-3 facade that fans out to every logger registered via App::configure(function (LoggerOptions $opts) { $opts->add(...); }).
- [`PermissionProvider`](PermissionProvider.md) · interface: App-provided permission lookup for #[Permissions(...)], configured via Waypoint\Options\PermissionOptions::$provider (App::configure()), the same "Waypoint owns the mechanism, the app owns the data" split LoggerOptions uses for PSR-3 loggers.
- [`RequestContext`](RequestContext.md) · class: The current request's correlation id (Request::$id), held as a container singleton for the duration of one request so Logger can stamp it onto every line without call sites passing it around.
- [`RouteCompiler`](RouteCompiler.md) · final class: Reflects #[Controller]/#[Manager] classes once into the plain-array route/ task plans Router dispatches against (and FileSystem caches).
- [`Router`](Router.md) · class: Matches a request against the compiled route plans (RouteCompiler, cached by FileSystem) and runs the matched controller method: access checks, per-route middleware, argument binding, and rendering of the result.
- [`Validator`](Validator.md) · class: Validates a DTO's public properties against #[NotBlank]/#[Email]/#[Length]/ #[Regex], #[OneOf], #[SameAs].
- [`ViewAssets`](ViewAssets.md) · final class: Discovers each view's sibling .css/.js (Home.php -> Home.css/Home.js, any depth under the views directory), scopes the CSS to that view with a `[data-view="Name"]` nesting wrapper, and content-hashes the result into cache-busted filenames.
- [`Waypoint`](Waypoint.md) · class: Process-wide access to the single App: create()/getInstance()/reset(), plus getConfig() for any Options class.

## Functions

### classes()

```php
function classes(array|string ...$parts): string
```

CSS classes from strings and conditions, for a `class` attribute: a string is always added, and so is a value
under an integer key of an array; a string key is added when its value is truthy. A string or key may hold
several classes. Each class comes once, in order, joined by one space. Every view and template can call it
without an import:

    <li class="{{ classes('row', ['late' => $order->late, 'bg-green-100 font-bold' => $order->isReady()]) }}">

**Parameters**

- `...$parts` `string|array<array-key, mixed>`

**Returns** `string`
