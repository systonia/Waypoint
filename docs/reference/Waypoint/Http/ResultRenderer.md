---
title: "ResultRenderer"
description: "Writes a route's return value to the Response as JSON (default), a raw file/body (#[FileFormatter]) or XML (#[SimpleXmlFormatter])."
order: 100
generated: true
---

`Waypoint\Http\ResultRenderer` · final class · namespace [`Waypoint\Http`](index.md)

```php
final class ResultRenderer
```

Writes a route's return value to the Response as JSON (default), a raw file/body (#[FileFormatter]) or XML (#[SimpleXmlFormatter]). Views and Redirects never come here.

## Methods

### render()

```php
public function render(mixed $result, \Waypoint\Http\Response $res, array $formatter): void
```

**Parameters**

- `$result` `mixed`
- `$res` [`Response`](Response.md)
- `$formatter` `array{type?: string, options?: array<string, mixed>|null}`
