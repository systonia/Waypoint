---
title: "JWT"
description: "Minimal HS256 JWT: header.payload.signature, each segment base64url, signed against JWTOptions::$secret."
order: 100
generated: true
---

`Waypoint\JWT` · class · namespace [`Waypoint`](index.md)

```php
class JWT
```

Minimal HS256 JWT: header.payload.signature, each segment base64url, signed against JWTOptions::$secret.

## Methods

### encode()

```php
public static function encode(array $payload, ?int $ttl = null): string
```

**Parameters**

- `$payload` `array<string, mixed>`: 'exp' is set here from $ttl (or JWTOptions::$ttl).
- `$ttl` `?int`

**Returns** `string`

### signIn()

```php
public static function signIn(\Waypoint\Http\Response $res, array $claims): string
```

Signs $claims into a token and sets it as the JWTOptions::$cookieName session cookie: HttpOnly, SameSite=Lax,
Max-Age JWTOptions::$ttl, Secure when the request came over HTTPS. Returns the token, e.g. for an API client.

**Parameters**

- `$res` [`Response`](Http/Response.md)
- `$claims` `array<string, mixed>`

**Returns** `string`

### signOut()

```php
public static function signOut(\Waypoint\Http\Response $res): void
```

Removes the JWTOptions::$cookieName session cookie.

**Parameters**

- `$res` [`Response`](Http/Response.md)

### decode()

```php
public static function decode(string $jwt): ?array
```

**Parameters**

- `$jwt` `string`

**Returns** `array<string, mixed>|null`: The payload, or null for a malformed, wrongly-signed, non-HS256, expired or never-expiring token.

### fromRequestHeaders()

```php
public static function fromRequestHeaders(array $headers): ?array
```

The payload of the bearer token in JWTOptions::$header (matched case-insensitively), or null.

**Parameters**

- `$headers` `array<string, string|string[]>`

**Returns** `array<string, mixed>|null`
