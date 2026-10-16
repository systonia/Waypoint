---
title: "Csrf"
description: "Stateless double-submit-cookie CSRF protection."
order: 100
generated: true
---

`Waypoint\Csrf` · class · namespace [`Waypoint`](index.md)

```php
class Csrf
```

Stateless double-submit-cookie CSRF protection. A token is a self-contained
HMAC-signed {exp, nonce} pair (same shape as JWT, minus the header segment);
it is issued as a readable cookie and must be repeated back by the client
in CsrfOptions::$headerName (AJAX) or $fieldName (plain form). A forged
cross-site request rides the cookie jar but can't read the cookie to
repeat it, so the two values never match.

The signature also covers the session cookie (JWTOptions::$cookieName): a
token someone fetched for themselves and planted in a victim's cookie jar
(from a sibling subdomain, say) doesn't verify against the victim's session.
A login or logout changes the session, so the next rendered view mints a new token.

A container singleton: $currentToken is per-request state set by
issueFor() (Router::renderView) and read by token()/field() from inside
a template via `$this->csrf`; App::handle() reset()s it afterwards. It is
kept per Fiber, so requests running interleaved in Fibers keep their own.

Every check is a no-op when CsrfOptions::$secret was never configured --
an app that never opted in must keep rendering views and accepting POSTs.

## Methods

### __construct()

```php
public function __construct()
```

### issueFor()

```php
public function issueFor(\Waypoint\Http\Request $req, \Waypoint\Http\Response $res): void
```

Reuses the request's still-valid cookie token, or mints a fresh one and sets it (not HttpOnly: JS must be able to read it).

**Parameters**

- `$req` [`Request`](Http/Request.md)
- `$res` [`Response`](Http/Response.md)

### followSession()

```php
public function followSession(\Waypoint\Http\Response $res): void
```

A response that signs someone in or out (it sets JWTOptions::$cookieName) also gets a CSRF token for that new
session: the old one is bound to the old session, and a fetch() right after a login would otherwise get a 403.
ponytail: a page rendered into that same response still embeds the old token; a login answers with a redirect.

**Parameters**

- `$res` [`Response`](Http/Response.md)

### token()

```php
public function token(): string
```

The current request's token, or '' if issueFor() never ran.

**Returns** `string`

### reset()

```php
public function reset(): void
```

### field()

```php
public function field(): string
```

`<input type="hidden" name="{fieldName}" value="{token}">` for a plain `<form>`.

**Returns** `string`

### verify()

```php
public function verify(\Waypoint\Http\Request $req): bool
```

True if the cookie token is valid and the client repeated exactly it back via header or body field.

**Parameters**

- `$req` [`Request`](Http/Request.md)

**Returns** `bool`

### generateToken()

```php
public static function generateToken(string $session = ''): string
```

A fresh signed token for $session (the value of the session cookie, '' without one); the nonce only keeps
two tokens minted in the same second distinct.

**Parameters**

- `$session` `string`

**Returns** `string`

### isValidToken()

```php
public static function isValidToken(?string $token, string $session = ''): bool
```

True if $token was signed against the current secret for $session (see generateToken()) and its 'exp' hasn't passed.

**Parameters**

- `$token` `?string`
- `$session` `string`

**Returns** `bool`
