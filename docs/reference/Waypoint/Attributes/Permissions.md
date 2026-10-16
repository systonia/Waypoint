---
title: "Permissions"
description: "Requires every permission in $permissions to be held by the current request's subject, checked via whatever PermissionProvider the app configured (see Waypoint\\Options\\PermissionOptions)."
order: 100
generated: true
---

`Waypoint\Attributes\Permissions` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Permissions
```

Requires every permission in $permissions to be held by the current
request's subject, checked via whatever PermissionProvider the app
configured (see Waypoint\Options\PermissionOptions). On the class
(every route on it) or a single method; method wins if both are
present, same "method overrides class, else null" precedence
#[Version]/#[Sunset]/#[Role] use, not merged across both levels.

Implies #[Authenticated] (see its own doc). No PermissionProvider
configured, or any single permission missing, throws
Waypoint\Exceptions\ForbiddenException.

## Properties

### $permissions

```php
public array $permissions;
```

**Type** `array<int, string>`

## Methods

### __construct()

```php
public function __construct(public array $permissions)
```

**Parameters**

- `$permissions` `array<int, string>`
