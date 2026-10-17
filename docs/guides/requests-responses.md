---
title: Requests and responses
description: Read the request, set status, headers and cookies, answer with JSON, redirects, files, XML or streams, and control compression and caching.
order: 30
---

A route method gets the [`Request`](../reference/Waypoint/Http/Request.md) and the [`Response`](../reference/Waypoint/Http/Response.md) by declaring parameters of those types. Both belong to the current request only.

```php
#[Get('/whoami')]
public function whoami(Request $req, Response $res): array
{
    $res->withHeader('Cache-Control', 'no-store');
    return ['ip' => $req->ip, 'requestId' => $req->id];
}
```

A controller or middleware can receive them as properties instead, with `#[Inject] private Request $req;`. Since a controller is created for every request, the property always holds the request being handled.

## The request

```php
<?php

namespace App\Controllers;

use Waypoint\Attributes\{Controller, Get};
use Waypoint\Http\Request;

#[Controller('/debug')]
final class RequestInfoController
{
    #[Get('/request')]
    public function describe(Request $req): array
    {
        return [
            'method' => $req->method,                          // GET, POST, …
            'path' => $req->path,                              // /debug/request
            'query' => $req->query,                            // ['tab' => 'orders']
            'tab' => $req->query('tab', 'overview'),           // one value with a default
            'agent' => $req->headers['User-Agent'] ?? null,
            'theme' => $req->cookies['theme'] ?? 'light',
            'https' => $req->https,
            'ip' => $req->ip,
            'requestId' => $req->id,
        ];
    }
}
```

| Property | Holds |
|---|---|
| `method` | the HTTP method in upper case |
| `path` | the path the router matches: without query string, slashes at either end trimmed, each segment percent-decoded (`/caf%C3%A9` is `/café`) |
| `headers` | every header, the name in `Title-Case` (`User-Agent`, `If-None-Match`, `X-Csrf-Token`) |
| `query` | the query string, parsed like `$_GET` (`tags[]=a&tags[]=b` is a list) |
| `body` | the decoded body: JSON for a JSON `Content-Type`, form fields for a form |
| `cookies` | name ⇒ value |
| `files` | uploaded files, see [below](#uploaded-files) |
| `https` | whether the request arrived over HTTPS |
| `ip` | the client's address, see [Proxies](proxies.md) |
| `jwt` | the decoded JWT payload, see [Authentication](authentication.md) |
| `id` | the correlation id, see [Logging](logging.md#request-ids) |

Header names are normalised by turning the parts between dashes into `Title-Case`; look them up that way. An underscore stays an underscore, so `X_Forwarded_For` is a different header from `X-Forwarded-For`.

### The body

The body is decoded only as what its `Content-Type` declares:

- `application/json` or any `application/*+json`: the JSON, as arrays,
- `application/x-www-form-urlencoded` and `multipart/form-data`: the form fields, nested like `$_POST` (`address[city]`),
- anything else: nothing; `body` stays empty.

A `text/plain` body that looks like JSON is not read as JSON, so a form on another site can't pose as a JSON request. `$req->body('email')` returns one field (`$req->input()` is the same), `$req->body()` the whole array. For anything beyond a field or two, bind a DTO with `#[Body]` instead (see [Binding and validation](binding.md)).

### Uploaded files

A `multipart/form-data` request carries its files in `$req->files`, nested as the field names say. [`$req->file()`](../reference/Waypoint/Http/Request.md#file) returns one [`UploadedFile`](../reference/Waypoint/Http/UploadedFile.md), named as in the form: `'avatar'`, `'documents[contract]'`, `'photos[1]'`:

```php
<?php

namespace App\Controllers;

use Waypoint\Attributes\{Controller, Post};
use Waypoint\Exceptions\ValidationException;
use Waypoint\Http\{Redirect, Request};

#[Controller('/profile')]
final class AvatarController
{
    #[Post('/avatar')]
    public function upload(Request $req): Redirect
    {
        $avatar = $req->file('avatar') ?? throw new ValidationException(errors: ['avatar' => 'Choose a picture.']);
        $type = $avatar->isValid() ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($avatar->contents()) : '';
        $extension = ['image/png' => 'png', 'image/jpeg' => 'jpg'][$type]
            ?? throw new ValidationException(errors: ['avatar' => 'Upload a PNG or JPEG picture.']);

        $avatar->moveTo(dirname(__DIR__, 2) . '/storage/avatars/' . bin2hex(random_bytes(8)) . ".$extension");
        return new Redirect('/profile', 303);
    }
}
```

- `name`, `type` and `size` come from the client. Never use the name as a path, and check the content before trusting the type.
- `isValid()` is false when PHP or the runtime refused the file; `error` holds the `UPLOAD_ERR_*` code.
- `contents()` returns the bytes; `moveTo($path)` writes the file there, once.
- A file input left empty is not in `files`, and a list (`photos[]`) stays numbered without gaps.

The size limit is `upload_max_filesize` and `post_max_size` under PHP-FPM and `--max-body` under Tenma. The behaviour is the same under FPM, Tenma, the PSR-15 handler and the testing kit.

## The response

[`Response`](../reference/Waypoint/Http/Response.md) collects status, headers, cookies and body; Waypoint sends it once, after every middleware has run. Its methods return the response, so calls chain:

| Method | Does |
|---|---|
| `status(201)` | sets the status code (200 by default) |
| `withHeader('Cache-Control', 'no-store')` | sets a header, replacing it in any spelling |
| `addVary('Accept-Language')` | adds a request header to `Vary`, keeping those listed |
| `hasHeader($name)`, `getHeader($name)` | ask what is set so far, case-insensitively |
| `withCookie($name, $value, …)` | queues a `Set-Cookie` |
| `withoutCookie($name)` | expires a cookie |
| `write($text)` | appends to the body |
| `disableGzip()` | sends this response uncompressed |
| `upgrade($tenmaResponse)` | hands the request to Tenma: a stream or WebSocket, see [Tenma](tenma.md#streams-and-websockets) |

A route that returns a value still uses the response for everything but the body:

```php
#[Post('')]
public function create(#[Body] NewProduct $product, Response $res): array
{
    $created = $this->catalog->add($product);
    $res->status(201)->withHeader('Location', "/products/{$created['id']}");
    return $created;
}
```

### Cookies

```php
$res->withCookie('theme', 'dark', maxAge: 60 * 60 * 24 * 365);
$res->withoutCookie('theme');
```

[`withCookie()`](../reference/Waypoint/Http/Response.md#withcookie) defaults to `Path=/`, `HttpOnly` and `SameSite=Lax`, the right defaults for a session cookie. `maxAge: null` (the default) makes a session cookie that ends with the browser session; `Secure` (HTTPS only) is set when the request came over HTTPS (`$req->https`); `secure: true` or `false` decides it yourself. `httpOnly: false` lets JavaScript read it, and `sameSite: null` leaves the attribute out. `withoutCookie()` needs the same `path` and `domain` the cookie was set with.

## JSON

A returned array, object or scalar goes out as JSON with `Content-Type: application/json`. An object is encoded by its public properties, or by `jsonSerialize()` when it implements `JsonSerializable`. A property with `#[Ignore]` is left out, in nested objects and lists too, and from XML (`#[SimpleXmlFormatter]`) as well:

```php
use Waypoint\Attributes\Ignore;

final class Account
{
    public function __construct(
        public string $name,
        #[Ignore]
        public string $passwordHash = '',
    ) {
    }
}
```

`jsonSerialize()` decides on its own: `#[Ignore]` changes nothing for a `JsonSerializable` object.

A value JSON can't hold, such as a string with invalid UTF-8, is not sent as `null`: the request fails with `500`, and the reason goes to the [log](logging.md).

## Redirects

Return a [`Redirect`](../reference/Waypoint/Http/Redirect.md). It is a normal result, so `View|Redirect` on the signature says what the method does:

```php
#[Post('/newsletter')]
public function subscribe(#[Body] Subscription $subscription): Redirect
{
    $this->newsletter->add($subscription->email);
    return new Redirect('/newsletter/thanks', 303);
}
```

The status is `302` by default and may be any `3xx`: `303 See Other` after a form, `301`/`308` for a permanent move, `307` to keep the method. The response is the status and `Location`, without a body; cookies and headers set on the `Response` are kept. A location that is empty or contains a line break is refused when the `Redirect` is created. Never redirect to a URL taken from the request without checking it against your own paths.

## Files and raw bodies

`#[FileFormatter]` sends the return value as it is instead of JSON. Return an `SplFileInfo` to send a file from disk, or a string to send that text:

```php
<?php

namespace App\Controllers;

use Waypoint\Attributes\{Controller, FileFormatter, Get};
use Waypoint\Exceptions\NotFoundException;

#[Controller('/invoices')]
final class InvoicesController
{
    private const DIRECTORY = '/srv/invoices';

    #[Get('/{number}.pdf')]
    #[FileFormatter(
        mimetype: 'application/pdf',
        download: true
    )]
    public function download(string $number): \SplFileInfo
    {
        if (preg_match('/^\d{4}-\d{4}$/', $number) !== 1 || !is_file(self::DIRECTORY . "/$number.pdf")) {
            throw new NotFoundException("No invoice $number.");
        }
        return new \SplFileInfo(self::DIRECTORY . "/$number.pdf");
    }

    #[Get('/export.csv')]
    #[FileFormatter(
        mimetype: 'text/csv; charset=utf-8',
        filename: 'invoices.csv',
        download: true
    )]
    public function export(): string
    {
        return "number,total\n2026-0001,120.00\n";
    }
}
```

- `mimetype` sets `Content-Type` (`application/octet-stream` without one).
- `download: true` adds `Content-Disposition: attachment` with `filename`, or the file's own name for an `SplFileInfo`.
- A returned string is always the content itself, even when it happens to name a file, so text from a user can never turn into a file read.

The file is read into memory. For large files under Tenma, stream them instead (see [Tenma](tenma.md#streams-and-websockets)) or serve them from the [public directory](views.md#static-files).

## XML

`#[SimpleXmlFormatter]` sends the return value as XML under a `<root>` element, with `Content-Type: application/xml`. Nested arrays and objects become nested elements, text is escaped, booleans become `true`/`false`, and a key that is no valid element name (a list index) becomes `<item key="0">`:

```php
#[Get('/feed.xml')]
#[SimpleXmlFormatter]
public function feed(): array
{
    return ['title' => 'News', 'entries' => [['id' => 1, 'title' => 'Opening hours']]];
}
```

```xml
<?xml version="1.0"?>
<root><title>News</title><entries><item key="0"><id>1</id><title>Opening hours</title></item></entries></root>
```

## Streams

Under Tenma a route can hand the request over to a streamed response or server-sent events with `$res->upgrade()`; the route's guards and middlewares run first. Under PHP-FPM a response is always sent in one piece. See [Tenma](tenma.md#streams-and-websockets).

## Compression

A response is gzipped when the client's `Accept-Encoding` allows `gzip` and the body has at least [`CompressionOptions::$minBytes`](../reference/Waypoint/Options/CompressionOptions.md) (1024 by default); smaller bodies would only grow. It then carries `Content-Encoding: gzip` and `Vary: Accept-Encoding`, and `Content-Length` is always the length actually sent. A response that already has a `Content-Encoding` is left alone.

```php
$app->configure(function (CompressionOptions $gzip) {
    $gzip->minBytes = 2048;
    // $gzip->enabled = false;   // off everywhere, e.g. when a proxy in front compresses
});
```

`#[NoGzip]` on a controller or a route method turns it off for those routes, for example for content that is compressed already:

```php
#[Get('/backup.tar.gz')]
#[NoGzip]
#[FileFormatter(
    mimetype: 'application/gzip',
    download: true
)]
public function backup(): \SplFileInfo
{
    return new \SplFileInfo('/srv/backups/latest.tar.gz');
}
```

## Caching headers and conditional requests

Waypoint sets no caching headers on route responses; a route sets them itself. Compiled view assets are the exception: their names change with their content, so they are served with `Cache-Control: public, max-age=31536000, immutable`.

A conditional `GET` saves the body when the client already has the current version. A route that writes its response itself (declared `: void`) can answer `304 Not Modified`:

```php
#[Get('/catalog')]
public function catalog(Request $req, Response $res): void
{
    $body = json_encode($this->catalog->page(1), JSON_THROW_ON_ERROR);
    $etag = '"' . hash('xxh128', $body) . '"';
    $res->withHeader('ETag', $etag)->withHeader('Cache-Control', 'private, max-age=60');

    if (($req->headers['If-None-Match'] ?? null) === $etag) {
        $res->status(304);
        return;
    }
    $res->withHeader('Content-Type', 'application/json')->write($body);
}
```

Responses with status `204` and `304` never carry a body: Waypoint drops the body, `Content-Type` and `Content-Length` for them, so a route can set the status without clearing what it already wrote.

## Errors

Throw an [`HttpException`](../reference/Waypoint/Exceptions/HttpException.md) to answer with an error: `NotFoundException` (404), `ForbiddenException` (403), `UnauthorizedException` (401), `ValidationException` (422), or your own subclass. When an exception ends a request, what the route wrote (body, cookies, content and caching headers) is discarded and the error response is built from scratch. See [Errors](errors.md).

See also: [`Request`](../reference/Waypoint/Http/Request.md), [`Response`](../reference/Waypoint/Http/Response.md), [`UploadedFile`](../reference/Waypoint/Http/UploadedFile.md), [`Redirect`](../reference/Waypoint/Http/Redirect.md), [`FileFormatter`](../reference/Waypoint/Attributes/FileFormatter.md), [`SimpleXmlFormatter`](../reference/Waypoint/Attributes/SimpleXmlFormatter.md), [`NoGzip`](../reference/Waypoint/Attributes/NoGzip.md).
