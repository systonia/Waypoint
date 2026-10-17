---
title: "Body"
description: "Binds the request body to a method parameter: a DTO class (hydrated via its array constructor, see FromArray, then validated), or, for an `array` parameter with `of: OrderLine::class`, a list of them, one per element."
order: 100
generated: true
---

`Waypoint\Attributes\Body` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Body
```

Binds the request body to a method parameter: a DTO class (hydrated via its
array constructor, see FromArray, then validated), or, for an `array`
parameter with `of: OrderLine::class`, a list of them, one per element.
`validate: false` hands an invalid DTO to the method instead of answering 422,
for an HTML form that shows the errors itself; a View it returns for such a
DTO still answers 422.

## Properties

### $name

```php
public ?string $name = null;
```

**Type** `string|null`

Unused for bodies; kept for symmetry with #[Param]/#[Query].

### $of

```php
public ?string $of = null;
```

**Type** `class-string|null`

Element DTO class for an `array`-typed parameter.

### $validate

```php
public bool $validate = true;
```

**Type** `bool`

False: no 422 before the method runs; it reads `$dto->isValid` and `$dto->errors` itself.

## Methods

### __construct()

```php
public function __construct(
    public ?string $name = null,
    public ?string $of = null,
    public bool $validate = true,
)
```

**Parameters**

- `$name` `string|null`: Unused for bodies; kept for symmetry with #[Param]/#[Query].
- `$of` `class-string|null`: Element DTO class for an `array`-typed parameter.
- `$validate` `bool`: False: no 422 before the method runs; it reads `$dto->isValid` and `$dto->errors` itself.
