---
title: "ViewHelper"
description: "Methods available inside templates as `$this->name(...)`."
order: 100
generated: true
---

`Waypoint\Plugin\ViewHelper` · interface · namespace [`Waypoint\Plugin`](index.md)

```php
interface ViewHelper
```

Methods available inside templates as `$this->name(...)`. Two plugins offering the same name is a boot error.

## Methods

### helpers()

```php
public function helpers(): array
```

**Returns** `array<string, callable>`: name => callable
