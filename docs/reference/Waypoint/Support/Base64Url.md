---
title: "Base64Url"
description: "RFC 4648 §5 base64url (no padding) plus the HMAC-SHA256 signing JWT and Csrf tokens share."
order: 100
generated: true
---

`Waypoint\Support\Base64Url` · final class · namespace [`Waypoint\Support`](index.md)

```php
final class Base64Url
```

RFC 4648 §5 base64url (no padding) plus the HMAC-SHA256 signing JWT and Csrf tokens share.

## Methods

### encode()

```php
public static function encode(string $raw): string
```

**Parameters**

- `$raw` `string`

**Returns** `string`

### decode()

```php
public static function decode(string $encoded): string
```

**Parameters**

- `$encoded` `string`

**Returns** `string`

### sign()

```php
public static function sign(string $data, string $secret): string
```

base64url(HMAC-SHA256($data, $secret)); compare with hash_equals().

**Parameters**

- `$data` `string`
- `$secret` `string`

**Returns** `string`

### encodeJson()

```php
public static function encodeJson(mixed $value): string
```

base64url(json_encode($value)), '{}' if it can't be encoded.

**Parameters**

- `$value` `mixed`

**Returns** `string`
