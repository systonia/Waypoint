---
title: "TestResponse"
description: "What one TestRequest produced: status, headers, cookies, body, plus fluent assertions."
order: 100
generated: true
---

`Waypoint\Testing\TestResponse` · final class · namespace [`Waypoint\Testing`](index.md)

```php
final class TestResponse
```

What one TestRequest produced: status, headers, cookies, body, plus fluent assertions.

## Methods

### __construct()

```php
public function __construct(private int $status, array $rawHeaders, private string $body)
```

**Parameters**

- `$status` `int`
- `$rawHeaders` `list<string>`: "Name: value" lines as the SAPI sent them
- `$body` `string`

### status()

```php
public function status(): int
```

**Returns** `int`

### body()

```php
public function body(): string
```

**Returns** `string`

### header()

```php
public function header(string $name): ?string
```

**Parameters**

- `$name` `string`

**Returns** `?string`

### headers()

```php
public function headers(): array
```

**Returns** `array<string, string>`: lower-cased name => value

### cookies()

```php
public function cookies(): array
```

**Returns** `list<string>`: Raw Set-Cookie values.

### cookie()

```php
public function cookie(string $name): ?string
```

The value a Set-Cookie for $name carried, decoded; null if none was sent.

**Parameters**

- `$name` `string`

**Returns** `?string`

### json()

```php
public function json(?string $path = null): mixed
```

The decoded JSON body, or the value at a dot path inside it ("errors.email").

**Parameters**

- `$path` `?string`

**Returns** `mixed`

### assertStatus()

```php
public function assertStatus(int $status): \Waypoint\Testing\TestResponse
```

**Parameters**

- `$status` `int`

**Returns** [`TestResponse`](TestResponse.md)

### assertOk()

```php
public function assertOk(): \Waypoint\Testing\TestResponse
```

**Returns** [`TestResponse`](TestResponse.md)

### assertRedirect()

```php
public function assertRedirect(?string $location = null, ?int $status = null): \Waypoint\Testing\TestResponse
```

A 3xx to $location (any 3xx when $location is null).

**Parameters**

- `$location` `?string`
- `$status` `?int`

**Returns** [`TestResponse`](TestResponse.md)

### assertHeader()

```php
public function assertHeader(string $name, ?string $value = null): \Waypoint\Testing\TestResponse
```

**Parameters**

- `$name` `string`
- `$value` `?string`

**Returns** [`TestResponse`](TestResponse.md)

### assertHeaderMissing()

```php
public function assertHeaderMissing(string $name): \Waypoint\Testing\TestResponse
```

**Parameters**

- `$name` `string`

**Returns** [`TestResponse`](TestResponse.md)

### assertContentType()

```php
public function assertContentType(string $prefix): \Waypoint\Testing\TestResponse
```

Content-Type starts with $prefix ("application/json" also matches "application/json; charset=utf-8").

**Parameters**

- `$prefix` `non-empty-string`

**Returns** [`TestResponse`](TestResponse.md)

### assertCookie()

```php
public function assertCookie(string $name, ?string $value = null): \Waypoint\Testing\TestResponse
```

**Parameters**

- `$name` `string`
- `$value` `?string`

**Returns** [`TestResponse`](TestResponse.md)

### assertSee()

```php
public function assertSee(string $text): \Waypoint\Testing\TestResponse
```

**Parameters**

- `$text` `string`

**Returns** [`TestResponse`](TestResponse.md)

### assertDontSee()

```php
public function assertDontSee(string $text): \Waypoint\Testing\TestResponse
```

**Parameters**

- `$text` `string`

**Returns** [`TestResponse`](TestResponse.md)

### assertBody()

```php
public function assertBody(string $exact): \Waypoint\Testing\TestResponse
```

**Parameters**

- `$exact` `string`

**Returns** [`TestResponse`](TestResponse.md)

### assertJson()

```php
public function assertJson(array $expected): \Waypoint\Testing\TestResponse
```

The whole decoded JSON body equals $expected.

**Parameters**

- `$expected` `array<array-key, mixed>`

**Returns** [`TestResponse`](TestResponse.md)

### assertJsonPath()

```php
public function assertJsonPath(string $path, mixed $expected): \Waypoint\Testing\TestResponse
```

**Parameters**

- `$path` `string`
- `$expected` `mixed`

**Returns** [`TestResponse`](TestResponse.md)
