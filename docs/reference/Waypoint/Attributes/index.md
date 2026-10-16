---
title: "Waypoint\\Attributes"
description: "The classes, interfaces, traits and enums in Waypoint\\Attributes."
order: 200
generated: true
---

## Types

- [`Authenticated`](Authenticated.md) · class: Requires a valid, decoded JWT (see useJwt()) for this route, on the class (every route on it, also those of subclasses) or a single method.
- [`Body`](Body.md) · class: Binds the request body to a method parameter: a DTO class (hydrated via its array constructor, see FromArray, then validated), or, for an `array` parameter with `of: SomeDto::class`, a list of them, one per element.
- [`Claims`](Claims.md) · class: Binds the signed-in user's JWT claims (`$req->jwt`) to a parameter typed with a FromArray DTO: `#[Claims] UserClaims $user`.
- [`Controller`](Controller.md) · class: Marks a class as an HTTP controller, with an optional path prefix for every route on it (e.g.
- [`Delete`](Delete.md) · class: Routes a controller method for HTTP DELETE.
- [`Email`](Email.md) · class: Validation: a non-null property must be a syntactically valid email address.
- [`FileFormatter`](FileFormatter.md) · class: Sends the route's return value as a raw body instead of JSON: return an SplFileInfo to send a file from disk, a string to send that text.
- [`Get`](Get.md) · class: Routes a controller method for HTTP GET.
- [`HttpRoute`](HttpRoute.md) · abstract class: Shared body of #[Get]/#[Post]/#[Put]/#[Patch]/#[Delete]: a path pattern such as '/users/{id}'.
- [`Ignore`](Ignore.md) · class: Excludes a controller (or one route method) from the generated OpenAPI spec.
- [`Inject`](Inject.md) · class: Marks a property (or constructor parameter, for discovery) to be resolved from the container: Request/Response/Router per request, everything else as a shared service.
- [`JSONFormatter`](JSONFormatter.md) · class: Sends the route's return value as JSON.
- [`Length`](Length.md) · class: Validation: a string property's length (mb_strlen) must be within [$min, $max].
- [`Manager`](Manager.md) · class: Marks a class as a CLI task manager, with an optional name prefix ("prefix:task") for every #[Task] on it.
- [`Middleware`](Middleware.md) · class: Attaches a MiddlewareBase subclass to a controller class (runs for every route on it, before any method-level middleware) or one route method: `#[Middleware(RequireAdmin::class)]`.
- [`NoGzip`](NoGzip.md) · class: Opts a controller class, or one specific route method, out of gzip response compression (see Response::send()/CompressionOptions), e.g.
- [`NotBlank`](NotBlank.md) · class: Validation: the property must not be null, an empty/whitespace-only string, or an empty array.
- [`OneOf`](OneOf.md) · class: Validation: a non-null property value must be one of $values (strict comparison).
- [`Param`](Param.md) · class: Binds a route placeholder to a controller method parameter, e.g.
- [`Patch`](Patch.md) · class: Routes a controller method for HTTP PATCH.
- [`Permissions`](Permissions.md) · class: Requires every permission in $permissions to be held by the current request's subject, checked via whatever PermissionProvider the app configured (see Waypoint\Options\PermissionOptions).
- [`PII`](PII.md) · class: Marks a DTO property as personally identifiable information (GDPR/DSGVO Art.
- [`Post`](Post.md) · class: Routes a controller method for HTTP POST.
- [`Property`](Property.md) · class: Documents a DTO property in generated OpenAPI schemas.
- [`Put`](Put.md) · class: Routes a controller method for HTTP PUT.
- [`Query`](Query.md) · class: Binds a query-string value to a method parameter; $name overrides the key when it differs from the parameter name.
- [`Regex`](Regex.md) · class: Validation: the value must match $pattern (a full PCRE pattern including delimiters).
- [`Role`](Role.md) · class: Requires `$req->jwt['role']` to equal $role.
- [`RouteAttribute`](RouteAttribute.md) · interface: Contract of the HTTP-method route attributes (Get, Post, Put, Patch, Delete).
- [`SameAs`](SameAs.md) · class: Validation: the property must equal the DTO's $property (e.g.
- [`Schema`](Schema.md) · class: Overrides the OpenAPI component schema name for a DTO class (default: its short class name).
- [`Sensitive`](Sensitive.md) · class: Marks a DTO property as sensitive: a credential, secret, or token whose real value must never end up in a log line, error report, or any other place `Waypoint::redact()` is used to build a safe-to-display copy of an object.
- [`SimpleXmlFormatter`](SimpleXmlFormatter.md) · class: Sends the route's return value as an XML document under `<root>`: nested arrays and objects become nested elements.
- [`SkipCsrf`](SkipCsrf.md) · class: Opts a controller class, or one specific route method, out of CSRF verification (see Waypoint\Csrf/Router::dispatch()), e.g.
- [`Summary`](Summary.md) · class: OpenAPI operation summary for a route method.
- [`Sunset`](Sunset.md) · class: Schedules a route for removal: $date ('YYYY-MM-DD') becomes the RFC 8594 `Sunset` response header, computed once at compile time.
- [`Tags`](Tags.md) · class: OpenAPI tags for a controller (every route on it) or one route method; both levels are merged.
- [`Task`](Task.md) · class: Marks a #[Manager] method as a CLI task, invoked as `php index.php [prefix:]name`.
- [`Throws`](Throws.md) · class: Documents an error response (OpenAPI only): which exception, its HTTP status, and an optional description.
- [`Version`](Version.md) · class: URI-prefixes a route with a version segment: #[Version('v1')] on #[Get('/users')] routes it at /v1/users.
