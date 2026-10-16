---
title: "SecurityHeaderOptions"
description: "The headers Waypoint\\Http\\SecurityHeadersMiddleware fills in, each with an on/off flag and a value."
order: 100
generated: true
---

`Waypoint\Options\SecurityHeaderOptions` · class · namespace [`Waypoint\Options`](index.md)

```php
class SecurityHeaderOptions
```

The headers Waypoint\Http\SecurityHeadersMiddleware fills in, each with an
on/off flag and a value. Four ship enabled with safe defaults; CSP is opt-in
because no one-size-fits-all policy exists.

## Properties

### $contentTypeOptionsEnabled

```php
public bool $contentTypeOptionsEnabled = true;
```

X-Content-Type-Options: no MIME sniffing.

### $contentTypeOptions

```php
public string $contentTypeOptions = 'nosniff';
```

### $frameOptionsEnabled

```php
public bool $frameOptionsEnabled = true;
```

X-Frame-Options: clickjacking protection ('SAMEORIGIN' to allow same-origin framing).

### $frameOptions

```php
public string $frameOptions = 'DENY';
```

### $referrerPolicyEnabled

```php
public bool $referrerPolicyEnabled = true;
```

Referrer-Policy.

### $referrerPolicy

```php
public string $referrerPolicy = 'strict-origin-when-cross-origin';
```

### $hstsEnabled

```php
public bool $hstsEnabled = true;
```

Strict-Transport-Security -- only ever sent on a request that itself arrived over HTTPS.

### $hsts

```php
public string $hsts = 'max-age=31536000; includeSubDomains';
```

### $cspEnabled

```php
public bool $cspEnabled = false;
```

Content-Security-Policy -- sent only when enabled and $csp is set.

### $csp

```php
public ?string $csp = null;
```
