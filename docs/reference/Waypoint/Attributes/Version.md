---
title: "Version"
description: "URI-prefixes a route with a version segment: #[Version('v1')] on #[Get('/users')] routes it at /v1/users."
order: 100
generated: true
---

`Waypoint\Attributes\Version` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Version
```

URI-prefixes a route with a version segment: #[Version('v1')] on
#[Get('/users')] routes it at /v1/users. On a controller class (every
route) or a method (overrides the class); no attribute means no prefix.

## Properties

### $value

```php
public string $value;
```

## Methods

### __construct()

```php
public function __construct(string $value)
```

**Parameters**

- `$value` `string`: e.g. 'v1'; surrounding slashes are stripped.
