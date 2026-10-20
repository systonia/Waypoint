---
title: "Layout"
description: "The layout every View of a controller class, or of one route method, renders in: a file name under RendererOptions::$directory, such as `_Backoffice.php`."
order: 100
generated: true
---

`Waypoint\Attributes\Layout` · class · namespace [`Waypoint\Attributes`](index.md)

```php
class Layout
```

The layout every View of a controller class, or of one route method, renders in: a file name under
RendererOptions::$directory, such as `_Backoffice.php`. `#[Layout(null)]` renders without one.
The method's wins over the class's, the class's over its parents'. A layout given to the View itself
(`new View(..., layout: ...)`) wins over both, and RendererOptions::$layout applies where none is set.

## Properties

### $file

```php
public ?string $file;
```

## Methods

### __construct()

```php
public function __construct(public ?string $file)
```

**Parameters**

- `$file` `?string`
