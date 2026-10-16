---
title: "Waypoint\\Options"
description: "The classes, interfaces, traits and enums in Waypoint\\Options."
order: 200
generated: true
---

## Types

- [`CompressionOptions`](CompressionOptions.md) · class: gzip response compression (Response::send()).
- [`CorsOptions`](CorsOptions.md) · class: The Access-Control-* headers useCors() attaches to every response.
- [`CsrfOptions`](CsrfOptions.md) · class: Stateless double-submit-cookie CSRF protection (see Waypoint\Csrf).
- [`EnvironmentOptions`](EnvironmentOptions.md) · class: The env key/values Waypoint\Environment reads, each layer overriding the one before: the committed defaults config.json and config.$APP_ENV.json, then .env and .env.$APP_ENV, then the real process environment (system env, $_ENV, $_SERVER), then $localConfigFile, then set().
- [`FileSystemOptions`](FileSystemOptions.md) · class: Where the compiled route/DI cache lives and where public static files are served from.
- [`JWTOptions`](JWTOptions.md) · class: HS256 JWT signing/verification (Waypoint\JWT, useJwt()).
- [`LoggerOptions`](LoggerOptions.md) · class: The PSR-3 loggers Waypoint\Logger fans out to, each with the levels it accepts: `$opts->add($logger, [LogLevel::ERROR])`.
- [`OpenAPIOptions`](OpenAPIOptions.md) · class: Turns the OpenAPI endpoint on and configures the document-level parts of the spec: info, servers, tags, security.
- [`PermissionOptions`](PermissionOptions.md) · class: The app's PermissionProvider for #[Permissions(...)].
- [`ProxyOptions`](ProxyOptions.md) · class: The reverse proxies in front of the app (nginx, Caddy, a load balancer), by address or CIDR range: `['127.0.0.1', '10.0.0.0/8', '::1']`.
- [`RendererOptions`](RendererOptions.md) · class: Where view templates live and which layout wraps them.
- [`SecurityHeaderOptions`](SecurityHeaderOptions.md) · class: The headers Waypoint\Http\SecurityHeadersMiddleware fills in, each with an on/off flag and a value.
