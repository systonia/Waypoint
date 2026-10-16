---
title: "JWTOptions"
description: "HS256 JWT signing/verification (Waypoint\\JWT, useJwt())."
order: 100
generated: true
---

`Waypoint\Options\JWTOptions` · class · namespace [`Waypoint\Options`](index.md)

```php
class JWTOptions
```

HS256 JWT signing/verification (Waypoint\JWT, useJwt()).

## Properties

### $secret

```php
public string $secret;
```

Signing secret. Deliberately uninitialized: reading it unset throws instead of signing with a guessable default.

### $ttl

```php
public int $ttl = 3600;
```

Token lifetime in seconds.

### $tokenType

```php
public string $tokenType = 'Bearer';
```

Expected prefix on the header value.

### $header

```php
public string $header = 'Authorization';
```

Request header the token is read from.

### $cookieName

```php
public ?string $cookieName = null;
```

Cookie to also read the token from when the header has none -- what lets a signed-in session survive a full page load. Null disables it.

### $loginRedirectUrl

```php
public ?string $loginRedirectUrl = null;
```

Where an UnauthorizedException sends a browser page navigation (Sec-Fetch-Mode: navigate) as a
302 instead of 401 JSON. Null (default): always 401, right for an API with no login page. Any
other fetch()/XHR call gets the 401 either way.
