---
title: "CsrfOptions"
description: "Stateless double-submit-cookie CSRF protection (see Waypoint\\Csrf)."
order: 100
generated: true
---

`Waypoint\Options\CsrfOptions` · class · namespace [`Waypoint\Options`](index.md)

```php
class CsrfOptions
```

Stateless double-submit-cookie CSRF protection (see Waypoint\Csrf). Off
until $secret is set; #[SkipCsrf] opts a single route out.

## Properties

### $secret

```php
public string $secret;
```

HMAC key. Deliberately uninitialized: reading it unset throws instead of signing against a guessable default.

### $ttl

```php
public int $ttl = 3600;
```

Token lifetime in seconds (signed into the token, and the cookie's Max-Age).

### $cookieName

```php
public string $cookieName = 'csrf_token';
```

The cookie name. Not HttpOnly on purpose: client-side JS mirrors it into $headerName.

### $headerName

```php
public string $headerName = 'X-CSRF-Token';
```

Request header checked first (the fetch()/XHR path).

### $fieldName

```php
public string $fieldName = '_csrf';
```

Body field checked second (the plain `<form>` path, see Csrf::field()).

### $cookieSecure

```php
public ?bool $cookieSecure = null;
```

Cookie Secure attribute. Null (default): Secure when the request came over HTTPS (behind a trusted proxy, as it says).

### $cookieSameSite

```php
public string $cookieSameSite = 'Lax';
```

Cookie SameSite attribute; 'Lax' still sends it on a top-level navigation into a form.
