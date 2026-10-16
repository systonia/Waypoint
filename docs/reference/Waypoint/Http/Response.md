---
title: "Response"
description: "The outgoing response, built up by middleware/controllers."
order: 100
generated: true
---

`Waypoint\Http\Response` · class · namespace [`Waypoint\Http`](index.md)

```php
class Response
```

The outgoing response, built up by middleware/controllers. App::handle() returns it finalized (gzip, HEAD,
Content-Length) for a runtime that writes it out itself via getBody()/getHeaders()/getCookies();
App::handleHttp() sends it exactly once through header()/echo.

## Methods

### __construct()

```php
public function __construct(
    private \Waypoint\Options\CompressionOptions $compressionOptions = new \Waypoint\Options\CompressionOptions(…),
)
```

**Parameters**

- `$compressionOptions` [`CompressionOptions`](../Options/CompressionOptions.md)

### disableGzip()

```php
public function disableGzip(): \Waypoint\Http\Response
```

Sends this response uncompressed, whatever CompressionOptions say; what #[NoGzip] does.

**Returns** [`Response`](Response.md)

### withHeader()

```php
public function withHeader(string $name, string $value): \Waypoint\Http\Response
```

Sets $name, replacing it in any spelling: HTTP header names know no case.

**Parameters**

- `$name` `string`
- `$value` `string`

**Returns** [`Response`](Response.md)

### addVary()

```php
public function addVary(string $header): \Waypoint\Http\Response
```

Adds a request header to `Vary`, keeping the ones already listed: caches then keep one copy per value.

**Parameters**

- `$header` `string`

**Returns** [`Response`](Response.md)

### hasHeader()

```php
public function hasHeader(string $name): bool
```

Case-insensitive. Lets an after() middleware fill in a header only if the controller didn't set it.

**Parameters**

- `$name` `string`

**Returns** `bool`

### getHeader()

```php
public function getHeader(string $name): ?string
```

The value queued for $name (case-insensitive), or null. For an after() middleware or Plugin\ResponseHook that must inspect what the controller produced.

**Parameters**

- `$name` `string`

**Returns** `?string`

### getStatus()

```php
public function getStatus(): int
```

The HTTP status code set so far (200 by default).

**Returns** `int`

### withRequestId()

```php
public function withRequestId(string $id): \Waypoint\Http\Response
```

Echoes Request::$id back as X-Request-ID.

**Parameters**

- `$id` `string`

**Returns** [`Response`](Response.md)

### withCookie()

```php
public function withCookie(
    string $name,
    string $value,
    ?int $maxAge = null,
    string $path = '/',
    ?string $domain = null,
    bool $secure = false,
    bool $httpOnly = true,
    ?string $sameSite = 'Lax',
): \Waypoint\Http\Response
```

Queues a Set-Cookie. HttpOnly and SameSite=Lax by default, the right defaults for a session cookie.

**Parameters**

- `$name` `string`
- `$value` `string`
- `$maxAge` `int|null`: Seconds; null makes it a session cookie.
- `$path` `string`
- `$domain` `?string`
- `$secure` `bool`
- `$httpOnly` `bool`
- `$sameSite` `string|null`: 'Lax', 'Strict', 'None', or null to omit the attribute.

**Returns** [`Response`](Response.md)

### queuedCookie()

```php
public function queuedCookie(string $name): ?string
```

The value this response sets $name to ('' when it removes it), or null when it doesn't touch that cookie.

**Parameters**

- `$name` `string`

**Returns** `?string`

### withoutCookie()

```php
public function withoutCookie(string $name, string $path = '/', ?string $domain = null): \Waypoint\Http\Response
```

Expires $name immediately (Max-Age=0); $path/$domain must match how it was set.

**Parameters**

- `$name` `string`
- `$path` `string`
- `$domain` `?string`

**Returns** [`Response`](Response.md)

### status()

```php
public function status(int $code): \Waypoint\Http\Response
```

Sets the HTTP status code.

**Parameters**

- `$code` `int`

**Returns** [`Response`](Response.md)

### upgrade()

```php
public function upgrade(object $runtimeResponse): \Waypoint\Http\Response
```

Hands the request over to the runtime: $runtimeResponse replaces this response entirely (status, headers,
cookies and body are dropped), e.g. Tenma's `Response::websocket()` or `Response::stream()`. Waypoint never
looks inside it; only a runtime that knows the object honors it (Tenma\Bridge), handleHttp() under FPM
and php -S does not. App::handle() clears it again when an exception handler writes the response instead.

**Parameters**

- `$runtimeResponse` `object`

**Returns** [`Response`](Response.md)

### getUpgrade()

```php
public function getUpgrade(): ?object
```

What upgrade() handed over, or null for an ordinary response.

**Returns** `?object`

### withoutUpgrade()

```php
public function withoutUpgrade(): \Waypoint\Http\Response
```

Drops what upgrade() handed over: an error response must not be replaced by a WebSocket.

**Returns** [`Response`](Response.md)

### keepContext()

```php
public function keepContext(\Closure $restore): \Waypoint\Http\Response
```

App::handle() keeps the request's FiberLocal values (FiberLocal::capture()) here before it resets them,
for an upgraded response whose handler runs after the request: request id, CSRF token, a plugin's state.

**Parameters**

- `$restore` [`Closure`](https://www.php.net/manual/en/class.closure.php)`(): void`

**Returns** [`Response`](Response.md)

### getContext()

```php
public function getContext(): ?\Closure
```

**Returns** `(`[`Closure`](https://www.php.net/manual/en/class.closure.php)`(): void)|null`: What keepContext() kept: the runtime calls it before it runs the upgrade.

### write()

```php
public function write(string $data): \Waypoint\Http\Response
```

Appends $data to the body.

**Parameters**

- `$data` `string`

**Returns** [`Response`](Response.md)

### mapBody()

```php
public function mapBody(callable $map): \Waypoint\Http\Response
```

Rewrites the body written so far, e.g. a ResponseHook expanding tags in a rendered page. $map gets the body
as written, for a HEAD request too (whose getBody() is ''), so Content-Length stays the GET's.

**Parameters**

- `$map` `callable(string): string`

**Returns** [`Response`](Response.md)

### forRequest()

```php
public function forRequest(\Waypoint\Http\Request $req): \Waypoint\Http\Response
```

Ties the response to $req: HEAD drops the body, its Accept-Encoding decides gzip. App::handle() does this.
Until finalize() a bound response ignores send(): an exception handler calling it itself must not write
through header()/echo when the caller of handle() writes the response out on its own.

**Parameters**

- `$req` [`Request`](Request.md)

**Returns** [`Response`](Response.md)

### finalize()

```php
public function finalize(): \Waypoint\Http\Response
```

Compresses the body if it qualifies and fixes Content-Length to the body actually sent. A second call does
nothing. Without forRequest() it falls back to the superglobals (a Response sent on its own under FPM).

**Returns** [`Response`](Response.md)

### getBody()

```php
public function getBody(): string
```

The body as it goes out: compressed after finalize(), '' for a HEAD request (whose Content-Length is the GET's).

**Returns** `string`

### getHeaders()

```php
public function getHeaders(): array
```

**Returns** `array<string, string>`: Every header but Set-Cookie (see getCookies()).

### getCookies()

```php
public function getCookies(): array
```

**Returns** `list<string>`: One raw Set-Cookie value each.

### send()

```php
public function send(): void
```

Finalizes, then sends status, headers, cookies and body. A second call does nothing.
