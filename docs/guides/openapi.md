---
title: OpenAPI
description: Serve an OpenAPI 3.1 document and Swagger UI generated from the routes, DTOs and documentation attributes.
order: 120
---

Waypoint generates an OpenAPI 3.1.0 document from what the code already says: routes, parameter types, `#[Body]` DTOs and their properties. Turn the endpoint on in [`OpenAPIOptions`](../reference/Waypoint/Options/OpenAPIOptions.md); there is nothing to attach. The Bakery documents its JSON API for the till and the delivery app (from `app.php`):

```php
use Waypoint\Environment;
use Waypoint\Options\OpenAPIOptions;

$app->configure(function (OpenAPIOptions $api, Environment $env) {
    $api->enabled = $env->bool('OPENAPI', false);
    $api->title = 'Sonnenkorn bakery API';
    $api->version = '1.0.0';
    $api->description = 'The range, and the open orders for the people in the bakery.';
    $api->servers = [['url' => 'https://shop.sonnenkorn.test']];
    $api->securitySchemes = ['bearer' => ['type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT']];
});
```

| URL | Serves |
|---|---|
| `GET /openapi/spec.json` | the document: every unversioned route plus the newest version of each versioned one |
| `GET /openapi/spec.v1.json` | the routes of one `#[Version]`; `404` for an unknown version |
| `GET /openapi/swagger.html` | Swagger UI for the document |

`OpenAPIOptions::$path` moves the prefix (`/openapi` by default). The endpoint answers before routing and without route checks: anyone who can reach the app can read the document. Enable it only where that is fine: in development, or for a public API. The Bakery turns it on with `OPENAPI=true`.

The document is built from the compiled attribute cache, not by reflecting the controllers on each request.

## What is documented

For each route that answers with data. A route whose return type is a `View` or a `Redirect` (also in a union such as `View|Redirect`) serves pages, not the API, and is left out, like one with `#[Ignore]`.

For each route:

- **operation**: `summary` from `#[Summary]` (otherwise `Class->method`), `operationId` as `ShortClass_method` (the full class name with dots, `Bakery.Controllers.ApiController_order`, where two controllers share a short name), `tags` from `#[Tags]` on the class and the method together, `deprecated` from PHP's `#[\Deprecated]`;
- **parameters**: `#[Param]` and unattributed scalars named like a placeholder of the route as path parameters, `#[Query]` and the other unattributed scalars as query parameters, with their PHP types; a query parameter is required when it has neither a default nor a nullable type;
- **request body**: a `#[Body]` DTO as `application/json` with a `$ref` to its schema, `#[Body]` with `of:` as an array of them;
- **responses**: `200` with a schema from the return type (an object by its public properties as a component schema, an enum by its values, a nullable type allowing `null`; a plain `array` stays an object) or from `#[Returns]` (see below), plus one problem response (`application/problem+json`, the shared `ProblemDetails` schema) for each `#[Throws]`, and on their own: `401` for a route with `#[Authenticated]`, `#[Role]` or `#[Permissions]`, `403` naming the role or permissions, `422` for a `#[Body]` DTO or a number, boolean or enum parameter;
- **security**: a guarded route requires the schemes in `OpenAPIOptions::$securitySchemes` (a JWT bearer scheme `bearerAuth` when none is configured). With a document-wide `OpenAPIOptions::$security`, guarded routes inherit it and public routes get `security: []`.

`#[Returns]` names what a route answers with, and wins over the return type. That is how an `array` gets its elements, and how a response other than `200 Successful response` is documented:

```php
#[Get('/orders')]
#[Authenticated]
#[Returns(
    type: OrderSummary::class,
    list: true
)]
public function openOrders(): array
{
    return array_map(OrderSummary::of(...), $this->orders->open());
}

#[Post('/tokens')]
#[Returns(
    type: BearerToken::class,
    description: 'A token for the Authorization header',
    status: 201
)]
public function token(#[Body] Credentials $credentials): BearerToken { /* ... */ }
```

The first is `ApiController::openOrders()` of the Bakery; the Bakery's own `token()` answers `200` and has no `#[Returns]`, the status here only shows the argument.

`type` is a class, an enum or a scalar keyword (`int`, `string`, `float`, `bool`); `list: true` documents a list of it, `map: true` an object of it by string key (`additionalProperties`). A nullable return type (`?array`) still allows `null`. A `type` that names no class, enum or scalar is logged as a warning and the schema falls back to the return type.

Each DTO becomes a component schema named after its short class name (a second class of the same short name gets its full name with dots, `Bakery.Models.OrderLine`; `#[Schema('Name')]` picks a better one): its public properties with their types, nested DTOs as `$ref` (also self-referencing ones). In detail:

- A backed enum is `{"type": "string", "enum": ["pending", "received", "baking", "ready", "picked_up", "cancelled"]}` (or `integer`), for properties and for path and query parameters alike.
- A nullable property allows `null`: `"type": ["string", "null"]`, or `anyOf` with `{"type": "null"}` next to a `$ref`.
- A property is `required` when it has neither a default nor a nullable type, the same rule `FromArray` applies.
- An `array` property with `#[ListOf(Item::class)]` gets `items`, one with `#[ListOf(type: Item::class, map: true)]` gets `additionalProperties`. Without it the array has no `items`.
- PHPStan, with Waypoint's `extension.neon`, takes `#[Returns]` and `#[ListOf]` as the element type, asks for no docblock next to them and checks what is returned or assigned ([Types and PHPStan](types.md)).
- A property with `#[Ignore]` is left out, as it is from the JSON the route answers with.
- A property the request cannot set (`readonly`, `protected(set)`, `private(set)`) is `readOnly`.
- `errors` and `isValid` from `FromArray` are left out.

The Bakery's API at `/v1`, shortened from `src/Controllers/ApiController.php`, with a second `#[Throws]` for the `422`:

```php
<?php

namespace Bakery\Controllers;

use Bakery\Models\Order;
use Bakery\Repositories\Orders;
use Bakery\Requests\StatusChange;
use Bakery\Responses\OrderSummary;
use Waypoint\Attributes\{Authenticated, Body, Controller, Inject, Patch, SkipCsrf, Summary, Tags, Throws, Version};
use Waypoint\Exceptions\{NotFoundException, ValidationException};

#[Controller]
#[Version('v1')]
#[SkipCsrf]
final class ApiController
{
    #[Inject]
    private Orders $orders;

    /** Moves the order one step on; the customer's page and every order board show it at once. */
    #[Patch('/orders/{code}')]
    #[Authenticated]
    #[Tags(['Orders'])]
    #[Summary('Move an order to its next step')]
    #[Throws(NotFoundException::class)]
    #[Throws(
        exception: ValidationException::class,
        description: 'The status is not the next step of the order'
    )]
    public function advance(string $code, #[Body] StatusChange $change): OrderSummary
    {
        $order = $this->find($code);
        if ($order->status->next() !== $change->status || !$this->orders->advance($order->id, $order->status)) {
            throw new ValidationException(errors: ['status' => 'That is not the next step of the order.']);
        }
        return OrderSummary::of($this->find($code));
    }

    private function find(string $code): Order
    {
        return $this->orders->byCode($code) ?? throw new NotFoundException("No order $code");
    }
}
```

The body and the answer are a DTO and a readonly class (`src/Requests/StatusChange.php`, `src/Responses/OrderSummary.php`):

```php
<?php

namespace Bakery\Requests;

use Bakery\Models\OrderStatus;
use Waypoint\Attributes\Property;
use Waypoint\FromArray;

final class StatusChange
{
    use FromArray;

    #[Property(
        description: 'The next step of the order: received → baking → ready → picked_up',
        example: 'baking'
    )]
    public ?OrderStatus $status = null;
}
```

```php
<?php

namespace Bakery\Responses;

use Bakery\Models\{Order, OrderLine, OrderStatus};
use Waypoint\Attributes\ListOf;

/** An order as the API shows it: without the customer's email and the internal id. */
final readonly class OrderSummary
{
    public function __construct(
        public string $code,
        public string $customer,
        public string $pickupDate,
        public OrderStatus $status,
        public int $totalCents,
        #[ListOf(OrderLine::class)]
        public array $lines,
    ) {
    }
}
```

The document then has `PATCH /v1/orders/{code}` with the summary, the tag, `code` as a path parameter, a request body with a `$ref` to `StatusChange` (its `status` an enum of the six steps plus `null`), a `200` with a `$ref` to `OrderSummary` (its `lines` a `readOnly` array of `OrderLine`), and the problem responses `401`, `404` and `422`. The back office's pages return a `View` and stay out of it; `BackofficeController` also carries `#[Ignore]`, so its CSV and XLSX exports stay out as well. `Product`, which `GET /v1/products` answers with, marks its `id` and `picture` with `#[Ignore]`: its schema and its JSON show neither. The Bakery's `ApiControllerTest` reads the document back from `/openapi/spec.json` and checks these parts.

| Attribute | On | Effect |
|---|---|---|
| `#[Summary('…')]` | method | the operation's summary |
| `#[Tags(['…'])]` | class, method | tags, merged from both |
| `#[Throws]` with `exception`, an optional `status` and `description` | method, repeatable | an error response with the problem schema; without a status, the exception's own (404 for `NotFoundException`, 500 for one that is no `HttpException`), without a description, the status's reason phrase |
| `#[Ignore]` | class, method | leaves the routes out of the document |
| `#[Ignore]` | DTO property | leaves the property out of the schema (and out of JSON results and binding) |
| `#[Returns(…)]` | method | the success response: `type`, `list`, `map`, `description`, `status` |
| `#[ListOf(…)]` | DTO property | the element type of an `array` property: `type`, `map` |
| `#[Schema('Name')]` | DTO class | the component name instead of the short class name |
| `#[Property(…)]` | DTO property | `description`, `format`, `example`, `deprecated` |

These attributes only describe; they don't change how a request is handled. Validation attributes are not translated into schema constraints.

Parameters and return types a plugin binds or renders are described by the plugin's `OpenApiExtension` hook (see [Plugins](plugins.md)); without one they appear as plain strings and objects.

See also: [`OpenAPIOptions`](../reference/Waypoint/Options/OpenAPIOptions.md), [`OpenAPIGenerator`](../reference/Waypoint/OpenAPI/OpenAPIGenerator.md), [`Summary`](../reference/Waypoint/Attributes/Summary.md), [`Tags`](../reference/Waypoint/Attributes/Tags.md), [`Throws`](../reference/Waypoint/Attributes/Throws.md), [`Property`](../reference/Waypoint/Attributes/Property.md), [`Schema`](../reference/Waypoint/Attributes/Schema.md).
