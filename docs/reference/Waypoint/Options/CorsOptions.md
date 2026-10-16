---
title: "CorsOptions"
description: "The Access-Control-* headers useCors() attaches to every response."
order: 100
generated: true
---

`Waypoint\Options\CorsOptions` · class · namespace [`Waypoint\Options`](index.md)

```php
class CorsOptions
```

The Access-Control-* headers useCors() attaches to every response. Every
unset property omits its header entirely -- CORS is off until configured.

## Properties

### $allowOrigin

```php
public ?string $allowOrigin = null;
```

### $allowMethods

```php
public ?string $allowMethods = null;
```

### $allowHeaders

```php
public ?string $allowHeaders = null;
```

### $exposeHeaders

```php
public ?string $exposeHeaders = null;
```

Response headers a cross-origin caller may read beyond the browser's default handful.

### $maxAge

```php
public ?int $maxAge = null;
```

Seconds a browser may cache a preflight response.

### $allowCredentials

```php
public bool $allowCredentials = false;
```

Sends "Access-Control-Allow-Credentials: true"; the header is either exactly that or absent.

## Methods

### toHeaders()

```php
public function toHeaders(): array
```

**Returns** `array<string, string>`: Only the headers actually configured.
