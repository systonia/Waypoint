---
title: "OpenAPIEndpoint"
description: "Serves the OpenAPI document and Swagger UI under OpenAPIOptions::$path once OpenAPIOptions::$enabled is set -- no controller to attach:"
order: 100
generated: true
---

`Waypoint\OpenAPI\OpenAPIEndpoint` · final class · namespace [`Waypoint\OpenAPI`](index.md)

```php
final class OpenAPIEndpoint implements \Waypoint\Plugin\Endpoint
```

Implements [`Endpoint`](../Plugin/Endpoint.md)

Serves the OpenAPI document and Swagger UI under OpenAPIOptions::$path
once OpenAPIOptions::$enabled is set -- no controller to attach:

  GET {path}/spec.json           the combined document
  GET {path}/spec.{version}.json one #[Version]'s document (404 for an unknown version)
  GET {path}/swagger.html        bundled Swagger UI

The first Plugin\Endpoint: Router consults it before route matching.

## Methods

### __construct()

```php
public function __construct(
    private \Waypoint\Router $router,
    private \Waypoint\Container $container,
    private ?string $assetDir = null,
)
```

**Parameters**

- `$router` [`Router`](../Router.md)
- `$container` [`Container`](../Container.md)
- `$assetDir` `string|null`: Overrides this class's directory as the location of swagger.html (tests).

### serve()

```php
public function serve(
    string $method,
    string $path,
    \Waypoint\Http\Request $req,
    \Waypoint\Http\Response $res,
): bool
```

True if this endpoint handled the request and wrote the response.

**Parameters**

- `$method` `string`
- `$path` `string`
- `$req` [`Request`](../Http/Request.md)
- `$res` [`Response`](../Http/Response.md)

**Returns** `bool`

### swaggerHtml()

```php
public function swaggerHtml(): string
```

**Returns** `string`
