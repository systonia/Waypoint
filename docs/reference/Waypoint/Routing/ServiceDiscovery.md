---
title: "ServiceDiscovery"
description: "Follows #[Inject] properties/constructor parameters transitively from the attached controllers to find every class the container must construct at boot."
order: 100
generated: true
---

`Waypoint\Routing\ServiceDiscovery` · final class · namespace [`Waypoint\Routing`](index.md)

```php
final class ServiceDiscovery
```

Follows #[Inject] properties/constructor parameters transitively from the attached controllers to find every class the container must construct at boot.

## Methods

### discover()

```php
public static function discover(array $roots): array
```

**Parameters**

- `$roots` `class-string[]`

**Returns** `class-string[]`: $roots plus everything they (transitively) inject, in discovery order.
