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
| `#[Body] NewCustomer $customer` | the body as a DTO, validated |
| `#[Body]` with `of:` on an `array` parameter | the body as a list of DTOs, each validated |
| `#[Body] array $data` | the decoded body as it came |
| `int $id` or `OrderStatus $status` without attribute | the placeholder `{id}` if the route has one, else `?id=` |
| a parameter a plugin binds | what the plugin provides (see [Plugins](plugins.md)) |

Any other parameter receives `null`, so it has to be nullable: a non-nullable one stops the app at boot with an `InvalidArgumentException` naming it. Services are not parameters: a controller gets them through `#[Inject]` properties (see [Dependency injection](dependency-injection.md)).

## Path and query values

```php
<?php

namespace App\Controllers;

use Waypoint\Attributes\{Controller, Get, Param, Query};

#[Controller('/customers')]
final class CustomerSearchController
{
    #[Get('/{customerId}/orders')]
    public function orders(
        #[Param('customerId')] int $id,
        #[Query('status')] ?string $state = null,
        bool $archived = false,
        int $page = 1,
    ): array {
        return ['customer' => $id, 'status' => $state, 'archived' => $archived, 'page' => $page];
    }
}
```

`GET /customers/42/orders?status=open&archived=yes&page=2` calls `orders(42, 'open', true, 2)`.

- `#[Param]` and `#[Query]` bind by the parameter's name; `#[Param('customerId')]` and `#[Query('status')]` bind a differently named placeholder or key.
- A parameter without an attribute and with a scalar type (`string`, `int`, `float`, `bool`) or a backed enum looks for a placeholder of its name first, then for the query key.
- A backed enum (`#[Query] OrderStatus $status`) gets the case whose value the text names (`tryFrom()`); any other text answers `422` with `This value should be one of: open, done.`. Missing and without a default or `null`, it answers `422` with `This value is required.`.
- The text is converted to the parameter's type: `int` takes whole numbers only, `float` numbers, and `bool` takes `true`/`false`, `1`/`0`, `yes`/`no` and `on`/`off`. A value that doesn't convert, such as `?page=abc`, answers `422` with the parameter in `errors`; the method is not called.
- A missing value gives the parameter's default, `null` if the type allows it, else the type's empty value (`''`, `0`, `0.0`, `false`). Give a default whenever a value is optional.

Placeholder values arrive URL-decoded once (see [Routing](routing.md#placeholders)).

## Request bodies as DTOs

`#[Body]` turns the request body into an object of the parameter's class. The body is read as JSON for a JSON `Content-Type` and as form fields for a form, so the same route serves a `fetch()` call and a plain HTML form:

```php
<?php

namespace App\Dto;

use Waypoint\Attributes\{Email, Length, NotBlank, OneOf};
use Waypoint\FromArray;

final class NewCustomer
{
    use FromArray;

    #[NotBlank]
    #[Length(
        max: 120
    )]
    public string $name = '';

    #[NotBlank]
    #[Email]
    public string $email = '';

    #[OneOf(['de', 'en'])]
    public string $language = 'de';

    public ?string $phone = null;

    public bool $newsletter = false;
}
```

```php
<?php

namespace App\Controllers;

use App\Dto\NewCustomer;
use Waypoint\Attributes\{Body, Controller, Post};
use Waypoint\Http\Response;

#[Controller('/customers')]
final class CustomersController
{
    #[Post('')]
    public function create(#[Body] NewCustomer $customer, Response $res): array
    {
        $res->status(201);
        return ['name' => $customer->name, 'email' => $customer->email, 'newsletter' => $customer->newsletter];
    }
}
```

When the DTO is not valid, the method is not called. The request answers `422` with every failing field:

```text
$ curl -X POST localhost:8080/customers -H 'Content-Type: application/json' -d '{"name": "", "email": "ada@", "language": "fr"}'
{"type":"about:blank","title":"Validation failed","status":422,"detail":"Validation failed","errors":{"name":"This value should not be blank.","email":"This value is not a valid email address.","language":"This value should be one of: de, en."}}
```

A JSON body that doesn't parse answers `400 Bad Request` before any DTO is built; `$req->bodyError` holds the reason.

The DTO the route built is also in `$req->bodyDto`, even when validation failed, so a middleware or exception handler can see it.

### FromArray

A `#[Body]` DTO is created as `new NewCustomer($req->body())`. The [`FromArray`](../reference/Waypoint/FromArray.md) trait provides that constructor:

- Each key that names a public property is assigned. Unknown keys are ignored, and so are private, protected, `protected(set)`, `private(set)` and static properties: those are the object's own state, never input.
- A value has to fit the property's type. Form text for a number or a boolean is converted (`"5"` for an `int`, `"on"` for a `bool`); nothing else is. A value that doesn't fit (`"abc"` for an `int`, `"yes"` for a `string[]`, an object for a `string`) leaves the property at its default and is reported under that key, so the request answers `422`, never `500`.
- A writable property without a default (`public string $name;`) is required: when the data leaves it unset, it is reported as `This value is required.`.
- The keys `errors`, `isValid` and `failures` are never assigned.

Hydration is flat: a property typed with another DTO class doesn't get one built from a nested array. Exclude such properties, and others that must never come from input, with `fromArrayExcludes()`:

```php
<?php

namespace App\Dto;

use Waypoint\FromArray;

final class OrderUpdate
{
    use FromArray;

    public string $note = '';

    public ?Address $deliveryAddress = null;   // set by the controller, not from input

    protected function fromArrayExcludes(): array
    {
        return ['deliveryAddress'];
    }
}
```

A DTO that needs more than the plain assignment declares its own constructor and calls `hydrateFromArray()`:

```php
<?php

namespace App\Dto;

use Waypoint\Attributes\NotBlank;
use Waypoint\FromArray;

final class Search
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

namespace App\Controllers;

use App\Dto\OrderLine;
use Waypoint\Attributes\{Body, Controller, Post};

#[Controller('/orders')]
final class BulkOrdersController
{
    #[Post('/{orderId}/lines')]
    public function addLines(
        int $orderId,
        #[Body(
            of: OrderLine::class
        )]
        array $lines,
    ): array {
        return ['order' => $orderId, 'added' => count($lines)];
    }
}
```

Each element becomes an `OrderLine` and is validated on its own. The errors are reported per index: `{"errors": {"1": {"quantity": "…"}}}`. A body that is a single object instead of a list answers `422` with a `body` error, and an element that is no object (`[1, "two"]`) with an error under its index.

## Validation attributes

The rules are attributes on the DTO's public properties:

| Attribute | Fails when |
|---|---|
| `#[NotBlank]` | the value is `null`, a string of only whitespace, or an empty array |
| `#[Email]` | the value is not `null` and not a valid email address |
| `#[Length]` with `min:` and `max:` | the string is shorter than `min` or longer than `max`, counted in characters; `null` passes (add `#[NotBlank]` to require the value) |
| `#[Regex('/^[A-Z]{2}\d{4}$/')]` | the value doesn't match; numbers match by their text, `null` passes, arrays and booleans never match |
| `#[OneOf(['draft', 'live'])]` | the value is not `null` and not one of the values, compared strictly (`'1'` is not `1`) |
| `#[SameAs('password')]` | the value differs from the named property, e.g. a password confirmation |

A property can carry several rules; when more than one fails, `errors` holds the message of the last one in the order of the table. The messages are English. A plugin can translate them by binding [`Waypoint\Validation\Messages`](../reference/Waypoint/Validation/Messages.md) in the container, as `systonia/waypoint-i18n` does.

```php
<?php

namespace App\Dto;

use Waypoint\Attributes\{Email, Length, NotBlank, Regex, SameAs};
use Waypoint\FromArray;

final class Registration
{
    use FromArray;

    #[NotBlank]
    #[Email]
    public string $email = '';

    #[Length(
        min: 12
    )]
    public string $password = '';

    #[SameAs('password')]
    public string $passwordConfirmation = '';

    #[Regex('/^\d{5}$/')]
    public ?string $postcode = null;
}
```

## Validating by hand

A DTO with `FromArray` validates itself. `$dto->isValid` and `$dto->errors` are computed properties: each read applies the rules, so there is no validator object and nothing goes stale. `fail()` adds a failure only the application can know, such as an email that is already registered:

```php
<?php

namespace App\Controllers;

use App\Dto\Registration;
use App\Services\Accounts;
use Waypoint\Attributes\{Controller, Inject, Post};
use Waypoint\Http\{Redirect, Request, View};

#[Controller('/register')]
final class RegistrationController
{
    #[Inject]
    private Accounts $accounts;

    #[Post('')]
    public function register(Request $req): View|Redirect
    {
        $form = new Registration($req->body());
        if ($form->isValid && $this->accounts->exists($form->email)) {
            $form->fail('email', 'This email is already registered.');
        }
        if (!$form->isValid) {
            return new View('Register', ['form' => $form, 'errors' => $form->errors]);
        }
        $this->accounts->create($form->email, $form->password);
        return new Redirect('/welcome', 303);
    }
}
```

This is the shape for HTML forms: instead of a `422`, the page is shown again with the messages next to the fields (see [Views](views.md#forms)). `errors` maps each field to one message, the attribute rules first and `fail()` entries after them; a later `fail()` for the same field replaces the earlier one. `errors` and `isValid` never appear in the [OpenAPI](openapi.md) schemas or in [redacted logs](logging.md#redacting-sensitive-values).

To answer `422` yourself from anywhere, throw a [`ValidationException`](../reference/Waypoint/Exceptions/ValidationException.md):

```php
throw new ValidationException(errors: ['email' => 'This email is already registered.']);
```

See also: [`Param`](../reference/Waypoint/Attributes/Param.md), [`Query`](../reference/Waypoint/Attributes/Query.md), [`Body`](../reference/Waypoint/Attributes/Body.md), [`FromArray`](../reference/Waypoint/FromArray.md), [`Validator`](../reference/Waypoint/Validator.md), [Errors](errors.md).
