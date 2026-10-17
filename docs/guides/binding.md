---
title: Binding and validation
description: Fill route parameters from the path, the query string and the body, hydrate DTOs with FromArray and validate them with attributes.
order: 40
---

Waypoint fills a route method's parameters from the request before it calls the method. What a parameter receives depends on its attribute and its type:

| Parameter | Receives |
|---|---|
| `Request $req`, `Response $res` | the current request and response |
| `#[Param] int $id` | the route placeholder `{id}` |
| `#[Query] string $q` | the query string value `?q=` |
| `#[Body] Credentials $credentials` | the body as a DTO, validated |
| `#[Body]` with `of:` on an `array` parameter | the body as a list of DTOs, each validated |
| `#[Body] array $data` | the decoded body as it came |
| `int $id` or `OrderStatus $status` without attribute | the placeholder `{id}` if the route has one, else `?id=` |
| a parameter a plugin binds | what the plugin provides (see [Plugins](plugins.md)) |

Any other parameter receives `null`, so it has to be nullable: a non-nullable one stops the app at boot with an `InvalidArgumentException` naming it. Services are not parameters: a controller gets them through `#[Inject]` properties (see [Dependency injection](dependency-injection.md)).

## Path and query values

```php
<?php

namespace Bakery\Controllers;

use Waypoint\Attributes\{Authenticated, Controller, Get, Param, Query};

#[Controller('/backoffice/products')]
#[Authenticated]
final class ProductOrdersController
{
    #[Get('/{productId}/orders')]
    public function orders(
        #[Param('productId')] int $id,
        #[Query('status')] ?string $state = null,
        bool $cancelled = false,
        int $page = 1,
    ): array {
        return ['product' => $id, 'status' => $state, 'cancelled' => $cancelled, 'page' => $page];
    }
}
```

`GET /backoffice/products/3/orders?status=ready&cancelled=yes&page=2` calls `orders(3, 'ready', true, 2)`.

- `#[Param]` and `#[Query]` bind by the parameter's name; `#[Param('productId')]` and `#[Query('status')]` bind a differently named placeholder or key.
- A parameter without an attribute and with a scalar type (`string`, `int`, `float`, `bool`) or a backed enum looks for a placeholder of its name first, then for the query key.
- A backed enum (`#[Query] OrderStatus $status`) gets the case whose value the text names (`tryFrom()`); any other text answers `422` with `This value should be one of: pending, received, baking, ready, picked_up, cancelled.`. Missing and without a default or `null`, it answers `422` with `This value is required.`.
- The text is converted to the parameter's type: `int` takes whole numbers only, `float` numbers, and `bool` takes `true`/`false`, `1`/`0`, `yes`/`no` and `on`/`off`. A value that doesn't convert, such as `?page=abc`, answers `422` with the parameter in `errors`; the method is not called.
- A missing value gives the parameter's default, `null` if the type allows it, else the type's empty value (`''`, `0`, `0.0`, `false`). Give a default whenever a value is optional.

Placeholder values arrive URL-decoded once (see [Routing](routing.md#placeholders)).

## Request bodies as DTOs

`#[Body]` turns the request body into an object of the parameter's class. The body is read as JSON for a JSON `Content-Type` and as form fields for a form, so the same route serves a `fetch()` call and a plain HTML form. The bakery's API exchanges email and password for a bearer token; the DTO is `src/Requests/Credentials.php`:

```php
<?php

namespace Bakery\Requests;

use Waypoint\Attributes\{Email, NotBlank, Property};
use Waypoint\FromArray;

final class Credentials
{
    use FromArray;

    #[NotBlank]
    #[Email]
    #[Property(
        example: 'ben@sonnenkorn.test'
    )]
    public string $email = '';

    #[NotBlank]
    #[Property(
        example: 'pretzel'
    )]
    public string $password = '';
}
```

`#[Property]` only describes the field in the [OpenAPI document](openapi.md). The route (from `src/Controllers/ApiController.php`):

```php
<?php

namespace Bakery\Controllers;

use Bakery\Repositories\Users;
use Bakery\Requests\Credentials;
use Bakery\Responses\BearerToken;
use Waypoint\Attributes\{Body, Controller, Inject, Post, SkipCsrf, Version};
use Waypoint\Exceptions\UnauthorizedException;
use Waypoint\JWT;

#[Controller]
#[Version('v1')]
#[SkipCsrf]
final class ApiController
{
    #[Inject]
    private Users $users;

    #[Post('/tokens')]
    public function token(#[Body] Credentials $credentials): BearerToken
    {
        $user = $this->users->verify($credentials->email, $credentials->password)
            ?? throw new UnauthorizedException('Wrong email or password.');
        return new BearerToken(JWT::encode($user));
    }
}
```

When the DTO is not valid, the method is not called. The request answers `422` with every failing field:

```text
$ curl -X POST localhost:8080/v1/tokens -H 'Content-Type: application/json' -d '{"email": "ben@", "password": ""}'
{"type":"about:blank","title":"Validation failed","status":422,"detail":"Validation failed","errors":{"email":"This value is not a valid email address.","password":"This value should not be blank."}}
```

A JSON body that doesn't parse answers `400 Bad Request` before any DTO is built; `$req->bodyError` holds the reason.

The DTO the route built is also in `$req->bodyDto`, even when validation failed, so a middleware or exception handler can see it.

### FromArray

A `#[Body]` DTO is created as `new Credentials($req->body())`. The [`FromArray`](../reference/Waypoint/FromArray.md) trait provides that constructor:

- Each key that names a public property is assigned. Unknown keys are ignored, and so are private, protected, `protected(set)`, `private(set)` and static properties: those are the object's own state, never input.
- A value has to fit the property's type. Form text for a number or a boolean is converted (`"5"` for an `int`, `"on"` for a `bool`); nothing else is. A value that doesn't fit (`"abc"` for an `int`, `"yes"` for a `string[]`, an object for a `string`) leaves the property at its default and is reported under that key, so the request answers `422`, never `500`.
- A writable property without a default (`public string $name;`) is required: when the data leaves it unset, it is reported as `This value is required.`.
- The keys `errors`, `isValid` and `failures` are never assigned.

Backed enums, nested DTOs and lists of them are built from the data too:

```php
<?php

namespace Bakery\Requests;

use Bakery\Models\OrderStatus;
use Waypoint\Attributes\ListOf;
use Waypoint\FromArray;

final class NewOrder
{
    use FromArray;

    public OrderStatus $status = OrderStatus::Pending;   // "received" becomes OrderStatus::Received

    public Customer $customer;                           // a FromArray DTO, built from {"name": ..., "email": ...}

    #[ListOf(OrderItem::class)]
    public array $items = [];                            // each element an OrderItem
}
```

- A backed enum property gets the case its value names (`tryFrom()`); a value no case has fails with `This value should be one of: pending, received, baking, ready, picked_up, cancelled.`.
- A property typed with another class that uses `FromArray` gets that DTO built from the nested array. Its errors appear under the property's name: `customer.email`.
- An `array` property with `#[ListOf(OrderItem::class)]` (or `#[ListOf(type: OrderItem::class, map: true)]` for string keys, as `Product` uses for its names per language) gets each element built the same way, for DTOs and backed enums. An element that doesn't fit is left out and reported as `items.2`; a nested DTO's own errors as `items.0.quantity`.

A property with `#[Ignore]` is never set from input and never required, even without a default: the DTO sets it itself. It is also left out of JSON results and of the OpenAPI schema, so it suits server-side state like a database id or a file name: the bakery's `Product` has it on `$id` and `$picture`. On a promoted constructor parameter it applies to the property; the constructor still has to fill it (a default does).

To exclude a property from input only, use `fromArrayExcludes()`:

```php
<?php

namespace Bakery\Requests;

use Bakery\Models\Order;
use Waypoint\FromArray;

final class OrderNote
{
    use FromArray;

    public string $note = '';

    public ?Order $order = null;   // set by the controller, not from input

    protected function fromArrayExcludes(): array
    {
        return ['order'];
    }
}
```

A DTO that needs more than the plain assignment declares its own constructor and calls `hydrateFromArray()`:

```php
<?php

namespace Bakery\Requests;

use Waypoint\Attributes\NotBlank;
use Waypoint\FromArray;

final class ProductSearch
{
    use FromArray;

    #[NotBlank]
    public string $term = '';

    public function __construct(array $data = [])
    {
        $this->hydrateFromArray($data);
        $this->term = trim($this->term);
    }
}
```

A DTO doesn't have to use `FromArray`. Any class with a constructor that takes the body array works, and its validation attributes are checked the same way.

### Lists of DTOs

For a body that is a JSON list of objects, type the parameter `array` and name the element class with `of:`:

```php
<?php

namespace Bakery\Controllers;

use Bakery\Repositories\Products;
use Bakery\Requests\StockCount;
use Waypoint\Attributes\{Authenticated, Body, Controller, Inject, Put, SkipCsrf, Version};

#[Controller('/stock')]
#[Version('v1')]
#[Authenticated]
#[SkipCsrf]
final class StockApi
{
    #[Inject]
    private Products $products;

    /** The morning's count: [{"product": 3, "stock": 40}, …] */
    #[Put('')]
    public function count(
        #[Body(
            of: StockCount::class
        )]
        array $counts,
    ): array {
        foreach ($counts as $count) {
            $this->products->setStock($count->product, $count->stock);
        }
        return ['updated' => count($counts)];
    }
}
```

Each element becomes a `StockCount` and is validated on its own. The errors are reported per index: `{"errors": {"1": {"stock": "…"}}}`. A body that is a single object instead of a list answers `422` with a `body` error, and an element that is no object (`[1, "two"]`) with an error under its index.

## Validation attributes

The rules are attributes on the DTO's public properties:

| Attribute | Fails when |
|---|---|
| `#[NotBlank]` | the value is `null`, a string of only whitespace, or an empty array |
| `#[Email]` | the value is not `null` and not a valid email address |
| `#[Length]` with `min:` and `max:` | the string is shorter than `min` or longer than `max`, counted in characters; `null` passes (add `#[NotBlank]` to require the value) |
| `#[Regex('/^\d{4}-\d{2}-\d{2}$/')]` | the value doesn't match; numbers match by their text, `null` passes, arrays and booleans never match |
| `#[OneOf(['bread', 'rolls', 'pastry', 'cake'])]` | the value is not `null` and not one of the values, compared strictly (`'1'` is not `1`) |
| `#[SameAs('password')]` | the value differs from the named property, e.g. a password confirmation |

A property can carry several rules; when more than one fails, `errors` holds the message of the last one in the order of the table. The messages are English. A plugin can translate them by binding [`Waypoint\Validation\Messages`](../reference/Waypoint/Validation/Messages.md) in the container, as `systonia/waypoint-i18n` does.

The bakery's checkout form (from `src/Requests/CheckoutForm.php`) combines several:

```php
<?php

namespace Bakery\Requests;

use Waypoint\Attributes\{Email, Length, NotBlank, Regex};
use Waypoint\FromArray;

final class CheckoutForm
{
    use FromArray;

    #[NotBlank]
    #[Length(
        max: 80
    )]
    public string $name = '';

    #[NotBlank]
    #[Email]
    public string $email = '';

    #[NotBlank]
    #[Regex('/^\d{4}-\d{2}-\d{2}$/')]
    public string $pickupDate = '';
}
```

## Validating by hand

A DTO with `FromArray` validates itself. `$dto->isValid` and `$dto->errors` are computed properties: each read applies the rules, so there is no validator object and nothing goes stale. `fail()` adds a failure only the application can know, such as a wrong password. The bakery's back office sign-in (from `src/Controllers/SessionController.php`):

```php
<?php

namespace Bakery\Controllers;

use Bakery\Repositories\Users;
use Bakery\Requests\LoginForm;
use Waypoint\Attributes\{Body, Controller, Inject, Post};
use Waypoint\Flash\Flash;
use Waypoint\Http\{Redirect, Response, View};
use Waypoint\I18n\Translator;
use Waypoint\JWT;

#[Controller('/backoffice')]
final class SessionController
{
    #[Inject]
    private Users $users;

    #[Inject]
    private Flash $flash;

    #[Inject]
    private Translator $translator;

    /** A View with the form while it is invalid answers 422. */
    #[Post('/login')]
    public function login(
        #[Body(
            validate: false
        )]
        LoginForm $form,
        Response $res,
    ): View|Redirect {
        $user = $form->isValid ? $this->users->verify($form->email, $form->password) : null;
        if ($user === null) {
            if ($form->isValid) {
                $form->fail('password', $this->translator->t('Wrong email or password.'));
            }
            return new View('Backoffice/Login', $form, layout: '_Backoffice.php');
        }
        JWT::signIn($res, $user);
        $this->flash->success($this->translator->t('Welcome, {name}.', ['name' => $user['name']]));
        return new Redirect('/backoffice', 303);
    }
}
```

`Flash` and `Translator` come from the [Flash](https://github.com/systonia/Waypoint-Flash) and [I18n](https://github.com/systonia/Waypoint-I18n) plugins.

`#[Body(validate: false)]` binds the DTO without answering `422` first, so the method sees an invalid one too. This is the shape for HTML forms: the page is shown again with the messages next to the fields (see [Views](views.md#forms)). A `View` returned while the bound DTO is invalid answers with status `422`, so a test or a script sees the failed submission. The same works with `new LoginForm($req->body())` when the DTO is built by hand, without the `422`. `errors` maps each field to one message, the attribute rules first and `fail()` entries after them; a later `fail()` for the same field replaces the earlier one. `errors` and `isValid` never appear in the [OpenAPI](openapi.md) schemas or in [redacted logs](logging.md#redacting-sensitive-values).

To answer `422` yourself from anywhere, throw a [`ValidationException`](../reference/Waypoint/Exceptions/ValidationException.md):

```php
throw new ValidationException(errors: ['status' => 'The order is received; its next step is baking.']);
```

See also: [`Param`](../reference/Waypoint/Attributes/Param.md), [`Query`](../reference/Waypoint/Attributes/Query.md), [`Body`](../reference/Waypoint/Attributes/Body.md), [`FromArray`](../reference/Waypoint/FromArray.md), [`Validator`](../reference/Waypoint/Validator.md), [Errors](errors.md).
