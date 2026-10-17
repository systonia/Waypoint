---
title: Guides
description: One part of Waypoint per page, with complete examples.
order: 10
---

Each guide covers one part of Waypoint and shows the way to use it, with examples taken from working code: most come from the [Sonnenkorn bakery](https://github.com/systonia/Waypoint-Bakery), a made-up bakery with an online shop, an order board, a back office and a JSON API. The [reference](../reference/index.md) lists every class, attribute and method in detail.

Building apps:

- [Application setup](application.md): `Waypoint::create()`, Options classes, `.env` and `APP_ENV`, `attach()`, `serve()`
- [Routing](routing.md): controllers, route attributes, placeholders, base controllers, versions, routes that write the response themselves
- [Requests and responses](requests-responses.md): request fields, uploads, headers, cookies, JSON, redirects, files, XML, compression, caching headers
- [Binding and validation](binding.md): `#[Param]`, `#[Query]`, `#[Body]`, DTOs with `FromArray`, validation attributes
- [Errors](errors.md): HTTP exceptions, problem responses, exception handlers
- [Dependency injection](dependency-injection.md): `#[Inject]`, services with constructor arguments, per-request state
- [Middleware](middleware.md): `MiddlewareBase`, app-wide and route middleware, security headers, CORS
- [Authentication](authentication.md): JWTs, cookie sign-in, CSRF, `#[Authenticated]`, `#[Role]`, `#[Permissions]`
- [Proxies](proxies.md): trusted proxies, client address and HTTPS
- [Views](views.md): templates and their syntax, layouts, sections, `@model`, the check, scoped CSS and JS, forms, `waypoint.js`, static files
- [Logging](logging.md): PSR-3 loggers, Monolog, request ids, redaction
- [OpenAPI](openapi.md): the generated document and Swagger UI
- [CLI tasks](cli-tasks.md): `#[Manager]` and `#[Task]`
- [Plugins](plugins.md): using plugins and writing one
- [Types and PHPStan](types.md): one place per type, the PHPStan extension, `Row` for database rows and JSON

Running:

- [Caching](caching.md): the route cache, validate and trust mode, OPcache
- [Running under Tenma](tenma.md): one long-running process, async I/O, streams and WebSockets
- [Runtimes](runtimes.md): PHP-FPM, `php -S`, PSR-15, `App::handle()`
- [Testing](testing.md): `Waypoint\Testing`
- [Security](security.md): what Waypoint does, what the app does, a checklist
- [Deployment](deployment.md): Tenma, PHP-FPM, scheduled tasks, releases
