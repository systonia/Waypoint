---
title: "TestRequest"
description: "Builds one request and runs it through App::handle() via Request::fromParts(): no superglobals, no header()/echo."
order: 100
generated: true
---

`Waypoint\Testing\TestRequest` · final class · namespace [`Waypoint\Testing`](index.md)

```php
final class TestRequest
```

Builds one request and runs it through App::handle() via Request::fromParts(): no superglobals, no
header()/echo. Cookies a test put into $_COOKIE and $_SERVER['HTTPS'] still apply, as defaults.
Every with*() returns $this.

## Methods

### __construct()

```php
public function __construct(private string $method, private string $uri)
```

**Parameters**

- `$method` `string`
- `$uri` `string`

### withHeader()

```php
public function withHeader(string $name, string $value): \Waypoint\Testing\TestRequest
```

**Parameters**

- `$name` `string`
- `$value` `string`

**Returns** [`TestRequest`](TestRequest.md)

### withHeaders()

```php
public function withHeaders(array $headers): \Waypoint\Testing\TestRequest
```

**Parameters**

- `$headers` `array<string, string>`

**Returns** [`TestRequest`](TestRequest.md)

### withBody()

```php
public function withBody(array $body): \Waypoint\Testing\TestRequest
```

Form fields ($_POST).

**Parameters**

- `$body` `array<array-key, mixed>`

**Returns** [`TestRequest`](TestRequest.md)

### withJson()

```php
public function withJson(array $data): \Waypoint\Testing\TestRequest
```

A raw JSON body plus Content-Type: application/json.

**Parameters**

- `$data` `array<array-key, mixed>`

**Returns** [`TestRequest`](TestRequest.md)

### withFile()

```php
public function withFile(
    string $field,
    string $name,
    string $contents,
    string $type = 'application/octet-stream',
): \Waypoint\Testing\TestRequest
```

An uploaded file for the field $field, named as in the form: "avatar", "doc[a]", "photos[]" (appends to a
list). Sets a multipart Content-Type; withBody() fields arrive as the form's fields.

**Parameters**

- `$field` `string`
- `$name` `string`
- `$contents` `string`
- `$type` `string`

**Returns** [`TestRequest`](TestRequest.md)

### withCookie()

```php
public function withCookie(string $name, string $value): \Waypoint\Testing\TestRequest
```

**Parameters**

- `$name` `string`
- `$value` `string`

**Returns** [`TestRequest`](TestRequest.md)

### withJwt()

```php
public function withJwt(array $payload): \Waypoint\Testing\TestRequest
```

Sends a JWT for $payload as a bearer token (JWTOptions must be configured).

**Parameters**

- `$payload` `array<string, mixed>`

**Returns** [`TestRequest`](TestRequest.md)

### withCsrf()

```php
public function withCsrf(): \Waypoint\Testing\TestRequest
```

Carries a valid CSRF cookie + header pair (resolved at send(), after the app is configured); a no-op when CSRF isn't configured.

**Returns** [`TestRequest`](TestRequest.md)

### withHttps()

```php
public function withHttps(bool $https = true): \Waypoint\Testing\TestRequest
```

A request that arrived over HTTPS (or, with false, explicitly not).

**Parameters**

- `$https` `bool`

**Returns** [`TestRequest`](TestRequest.md)

### asNavigation()

```php
public function asNavigation(): \Waypoint\Testing\TestRequest
```

What a browser sends for a top-level navigation (typed URL, link, reload).

**Returns** [`TestRequest`](TestRequest.md)

### send()

```php
public function send(): \Waypoint\Testing\TestResponse
```

**Returns** [`TestResponse`](TestResponse.md)
