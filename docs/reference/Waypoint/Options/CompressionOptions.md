---
title: "CompressionOptions"
description: "gzip response compression (Response::send())."
order: 100
generated: true
---

`Waypoint\Options\CompressionOptions` · class · namespace [`Waypoint\Options`](index.md)

```php
class CompressionOptions
```

gzip response compression (Response::send()). Configure via App::configure(); #[NoGzip] opts a single route out.

## Properties

### $enabled

```php
public bool $enabled = true;
```

Master switch.

### $minBytes

```php
public int $minBytes = 1024;
```

A body shorter than this (bytes) is sent uncompressed -- gzip's framing overhead makes it a net loss.
