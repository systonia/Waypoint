---
title: "Body"
description: "Binds the request body to a method parameter: a DTO class (hydrated via its array constructor, see FromArray, then validated), or, for an `array` parameter with `of: SomeDto::class`, a list of them, one per element."
order: 100
generated: true
---

`Waypoint\Attributes\Body` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Body
```

Binds the request body to a method parameter: a DTO class (hydrated via its
array constructor, see FromArray, then validated), or, for an `array`
parameter with `of: SomeDto::class`, a list of them, one per element.

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

## Methods

### __construct()

```php
public function __construct(public ?string $name = null, public ?string $of = null)
```

**Parameters**

- `$name` `string|null`: Unused for bodies; kept for symmetry with #[Param]/#[Query].
- `$of` `class-string|null`: Element DTO class for an `array`-typed parameter.
