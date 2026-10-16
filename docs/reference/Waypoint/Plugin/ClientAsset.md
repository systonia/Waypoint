---
title: "ClientAsset"
description: "CSS/JS files a plugin ships; compiled into the asset pipeline (content-hashed) and rendered by View::pluginAssetTags()."
order: 100
generated: true
---

`Waypoint\Plugin\ClientAsset` · interface · namespace [`Waypoint\Plugin`](index.md)

```php
interface ClientAsset
```

CSS/JS files a plugin ships; compiled into the asset pipeline (content-hashed) and rendered by View::pluginAssetTags().

## Methods

### assets()

```php
public function assets(): array
```

**Returns** `array<string, string>`: {logical filename ending in .css or .js => absolute source path}
