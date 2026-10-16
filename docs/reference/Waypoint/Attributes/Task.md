---
title: "Task"
description: "Marks a #[Manager] method as a CLI task, invoked as `php index.php [prefix:]name`."
order: 100
generated: true
---

`Waypoint\Attributes\Task` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Task
```

Marks a #[Manager] method as a CLI task, invoked as `php index.php [prefix:]name`.

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

The task name, or '' when none (such a task is skipped).

**Returns** `string`
