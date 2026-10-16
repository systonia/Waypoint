---
title: "PermissionOptions"
description: "The app's PermissionProvider for #[Permissions(...)]."
order: 100
generated: true
---

`Waypoint\Options\PermissionOptions` · class · namespace [`Waypoint\Options`](index.md)

```php
class PermissionOptions
```

The app's PermissionProvider for #[Permissions(...)]. None configured (default) fails every check closed.

## Properties

### $provider

```php
public ?\Waypoint\PermissionProvider $provider = null;
```

null: a provider a plugin bound with Container::bind(PermissionProvider::class, ...), if any.
