---
title: "RendererOptions"
description: "Where view templates live and which layout wraps them."
order: 100
generated: true
---

`Waypoint\Options\RendererOptions` · class · namespace [`Waypoint\Options`](index.md)

```php
class RendererOptions
```

Where view templates live and which layout wraps them.

## Properties

### $directory

```php
public ?string $directory = null { get; set; }
```

Resolved like FileSystemOptions paths; null falls back to the running script's directory.

### $layout

```php
public string $layout = '_Layout';
```

Layout file name including .php ('_Layout.php'). The default has no extension and so matches no file: no layout until configured.

## Methods

### __construct()

```php
public function __construct()
```
