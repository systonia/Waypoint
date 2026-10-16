---
title: "Query"
description: "Binds a query-string value to a method parameter; $name overrides the key when it differs from the parameter name."
order: 100
generated: true
---

`Waypoint\Attributes\Query` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Query
```

Binds a query-string value to a method parameter; $name overrides the key when it differs from the parameter name.

## Properties

### $name

```php
public ?string $name = null;
```

## Methods

### __construct()

```php
public function __construct(public ?string $name = null)
```

**Parameters**

- `$name` `?string`
