---
title: "Redirect"
description: "Returned from a route method to send a redirect instead of a body: `return new Redirect('/backoffice')` next to the View the same handler renders on failure (POST/redirect/GET)."
order: 100
generated: true
---

`Waypoint\Http\Redirect` · final class · namespace [`Waypoint\Http`](index.md)

```php
final class Redirect
```

Returned from a route method to send a redirect instead of a body:
`return new Redirect('/backoffice')` next to the View the same handler
renders on failure (POST/redirect/GET). Router turns it into status +
Location; cookies/headers already queued on the Response are kept.
A plain result, not an exception: a redirect is a normal outcome, and
`View|Redirect` on the signature says so. Auth failures that should
redirect to the login page go through UnauthorizedException + JWTOptions::$loginRedirectUrl.

## Properties

### $location

```php
public readonly string $location;
```

**Type** `string`

Sent verbatim as the Location header.

### $status

```php
public readonly int $status = 302;
```

**Type** `int`

Any 3xx; 302 by default (303 for an explicit See Other, 301/308 permanent, 307 keeps the method).

## Methods

### __construct()

```php
public function __construct(public readonly string $location, public readonly int $status = 302)
```

**Parameters**

- `$location` `string`: Sent verbatim as the Location header.
- `$status` `int`: Any 3xx; 302 by default (303 for an explicit See Other, 301/308 permanent, 307 keeps the method).
