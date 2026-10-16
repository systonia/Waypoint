---
title: OpenAPI
description: Serve an OpenAPI 3.1 document and Swagger UI generated from the routes, DTOs and documentation attributes.
order: 120
---

Waypoint generates an OpenAPI 3.1.0 document from what the code already says: routes, parameter types, `#[Body]` DTOs and their properties. Turn the endpoint on in [`OpenAPIOptions`](../reference/Waypoint/Options/OpenAPIOptions.md); there is nothing to attach:

```php
use Waypoint\Environment;
use Waypoint\Options\OpenAPIOptions;

$app->configure(function (OpenAPIOptions $api, Environment $env) {
    $api->enabled = $env->isDev();
    $api->title = 'Shop API';
    $api->version = '2.3.0';
    $api->description = 'Products, orders and customers.';
    $api->servers = [['url' => 'https://api.shop.example.com']];
    $api->securitySchemes = ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT']];
    $api->security = [['bearer' => []]];
});
```

| URL | Serves |
|---|---|
| `GET /openapi/spec.json` | the document: every unversioned route plus the newest version of each versioned one |
| `GET /openapi/spec.v1.json` | the routes of one `#[Version]`; `404` for an unknown version |
| `GET /openapi/swagger.html` | Swagger UI for the document |

`OpenAPIOptions::$path` moves the prefix (`/openapi` by default). The endpoint answers before routing and without route checks: anyone who can reach the app can read the document. Enable it only where that is fine, for example in development as above, or for a public API.

The document is built from the compiled attribute cache, not by reflecting the controllers on each request.

## What is documented

For each route:

- **operation**: `summary` from `#[Summary]` (otherwise `Class->method`), `operationId` as `Class_method`, `tags` from `#[Tags]` on the class and the method together, `deprecated` from PHP's `#[\Deprecated]`;
- **parameters**: `#[Param]` and unattributed scalars named like a placeholder of the route as path parameters, `#[Query]` and the other unattributed scalars as query parameters, with their PHP types; a query parameter is required when it has neither a default nor a nullable type;
- **request body**: a `#[Body]` DTO as `application/json` with a `$ref` to its schema, `#[Body]` with `of:` as an array of them;
- **responses**: `200`, plus one problem response (`application/problem+json`, the shared `ProblemDetails` schema) for each `#[Throws]`.

Each DTO becomes a component schema named after its short class name: its public properties with their types, nested DTOs as `$ref` (also self-referencing ones), non-nullable properties as `required`. `errors` and `isValid` from `FromArray` are left out.

```php
<?php

namespace App\Controllers;

use App\Dto\NewOrder;
use Waypoint\Attributes\{Body, Controller, Get, Ignore, Param, Post, Summary, Tags, Throws, Version};
use Waypoint\Exceptions\{NotFoundException, ValidationException};

#[Controller('/orders')]
#[Version('v1')]
#[Tags(['Orders'])]
final class OrdersApiController
{
    #[Get('/{id}')]
    #[Summary('Fetch one order')]
    #[Throws(
        exception: NotFoundException::class,
        status: 404,
        description: 'No order with this id'
    )]
    public function show(#[Param] int $id): array
    {
        return ['id' => $id, 'status' => 'open'];
    }

    #[Post('')]
    #[Summary('Place an order')]
    #[Throws(
        exception: ValidationException::class,
        status: 422
    )]
    public function create(#[Body] NewOrder $order): array
    {
        return ['id' => 1, 'sku' => $order->sku, 'quantity' => $order->quantity];
    }

    #[Get('/internal/stats')]
    #[Ignore]
    public function stats(): array
    {
        return ['orders' => 1];
    }
}
```

```php
<?php

namespace App\Dto;

use Waypoint\Attributes\{NotBlank, Property, Schema};
use Waypoint\FromArray;

#[Schema('OrderRequest')]
final class NewOrder
{
    use FromArray;

    #[NotBlank]
    #[Property(
        description: 'Stock keeping unit',
        example: 'LAMP-1'
    )]
    public string $sku = '';

    #[Property(
        description: 'Number of items',
        format: 'int32',
        example: 2
    )]
    public int $quantity = 1;

    public ?string $note = null;
}
```

| Attribute | On | Effect |
|---|---|---|
| `#[Summary('…')]` | method | the operation's summary |
| `#[Tags(['…'])]` | class, method | tags, merged from both |
| `#[Throws]` with `exception`, `status` and an optional `description` | method, repeatable | an error response with the problem schema |
| `#[Ignore]` | class, method | leaves the routes out of the document |
| `#[Schema('Name')]` | DTO class | the component name instead of the short class name |
| `#[Property(…)]` | DTO property | `description`, `format`, `example`, `deprecated` |

These attributes only describe; they don't change how a request is handled. Validation attributes are not translated into schema constraints.

Parameters and return types a plugin binds or renders are described by the plugin's `OpenApiExtension` hook (see [Plugins](plugins.md)); without one they appear as plain strings and objects.

See also: [`OpenAPIOptions`](../reference/Waypoint/Options/OpenAPIOptions.md), [`OpenAPIGenerator`](../reference/Waypoint/OpenAPI/OpenAPIGenerator.md), [`Summary`](../reference/Waypoint/Attributes/Summary.md), [`Tags`](../reference/Waypoint/Attributes/Tags.md), [`Throws`](../reference/Waypoint/Attributes/Throws.md), [`Property`](../reference/Waypoint/Attributes/Property.md), [`Schema`](../reference/Waypoint/Attributes/Schema.md).
