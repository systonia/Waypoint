---
title: "Manager"
description: "Marks a class as a CLI task manager, with an optional name prefix (\"prefix:task\") for every #[Task] on it."
order: 100
generated: true
---

`Waypoint\Attributes\Manager` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Manager
```

Marks a class as a CLI task manager, with an optional name prefix ("prefix:task") for every #[Task] on it.

## Methods

### __construct()

```php
public function __construct(?string $name = null)
```

**Parameters**

- `$name` `?string`

### getName()

```php
public function getName(): string
```

The prefix, or '' when none.

**Returns** `string`
