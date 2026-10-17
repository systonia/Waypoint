---
title: "Returns"
description: "Documents what a route method answers with in generated OpenAPI: the success response's status, description and schema."
order: 100
generated: true
---

`Waypoint\Attributes\Returns` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Returns
```

Documents what a route method answers with in generated OpenAPI: the success response's status, description and
schema. $type is a class, an enum or a scalar keyword (int, string, float, bool); $list makes it a list of $type,
$map an object of $type values by string key. A nullable return type still allows null. It wins over the return
type, so it is also how an `array` return type gets its elements.

## Properties

### $type

```php
public string $type;
```

### $list

```php
public bool $list = false;
```

### $map

```php
public bool $map = false;
```

### $description

```php
public ?string $description = null;
```

### $status

```php
public int $status = 200;
```

## Methods

### __construct()

```php
public function __construct(
    public string $type,
    public bool $list = false,
    public bool $map = false,
    public ?string $description = null,
    public int $status = 200,
)
```

**Parameters**

- `$type` `string`
- `$list` `bool`
- `$map` `bool`
- `$description` `?string`
- `$status` `int`
