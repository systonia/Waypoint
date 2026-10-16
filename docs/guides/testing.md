---
title: Testing
description: Drive real requests through the app from PHPUnit with Waypoint\Testing, and assert on status, headers, cookies, HTML and JSON.
order: 160
---

`Waypoint\Testing` runs requests through the whole app (middlewares, routing, binding, validation, rendering) without a server: each request goes through [`App::handle()`](../reference/Waypoint/App.md#handle), so nothing touches the superglobals, `header()` or `echo`. It needs PHPUnit as a development dependency:

```bash
composer require --dev phpunit/phpunit
```

A test extends [`Waypoint\Testing\TestCase`](../reference/Waypoint/Testing/TestCase.md), sets the app up the way the front controller does, and sends requests:

```php
<?php

namespace App\Tests;

use App\Controllers\{CustomersController, GreetingController};
use Waypoint\Options\CsrfOptions;
use Waypoint\Testing\TestCase;

final class CustomersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $app = $this->app();
        $app->configure(fn (CsrfOptions $csrf) => $csrf->secret = str_repeat('x', 32));
        $app->attach([GreetingController::class, CustomersController::class]);
    }

    public function testGreets(): void
    {
        $this->get('/hello/Ada?shout=yes')
            ->assertOk()
            ->assertContentType('application/json')
            ->assertJson(['greeting' => 'HELLO, ADA!']);
    }

    public function testRejectsAnInvalidCustomer(): void
    {
        $this->request('POST', '/customers')
            ->withJson(['name' => 'Ada', 'email' => 'not-an-address'])
            ->withCsrf()
            ->send()
            ->assertStatus(422)
            ->assertJsonPath('errors.email', 'This value is not a valid email address.');
    }

    public function testNeedsTheCsrfToken(): void
    {
        $this->json('POST', '/customers', ['name' => 'Ada', 'email' => 'ada@example.com'])->assertStatus(403);
    }

    public function testCreatesACustomer(): void
    {
        $response = $this->request('POST', '/customers')
            ->withBody(['name' => 'Ada', 'email' => 'ada@example.com', 'newsletter' => 'on'])
            ->withCsrf()
            ->send()
            ->assertStatus(201);

        $this->assertTrue($response->json('newsletter'));
    }
}
```

Every test starts with a fresh app ([`Waypoint::reset()`](../reference/Waypoint/Waypoint.md#reset)) and empty superglobals. `$this->app()` is that app; configure and attach it in `setUp()` or in the test itself. A test that overrides `setUp()` or `tearDown()` calls the parent's.

## Sending requests

| Shortcut | Sends |
|---|---|
| `$this->get($uri, $headers)` | a `GET` |
| `$this->post($uri, $fields, $headers)` | a `POST` with form fields |
| `$this->json($method, $uri, $data, $headers)` | a request with a JSON body and `Content-Type: application/json` |
| `$this->request($method, $uri)` | a [`TestRequest`](../reference/Waypoint/Testing/TestRequest.md) to build, sent with `->send()` |

| Builder method | Adds |
|---|---|
| `withHeader($name, $value)`, `withHeaders([...])` | request headers |
| `withBody([...])` | form fields |
| `withJson([...])` | a JSON body |
| `withFile($field, $name, $contents, $type)` | an uploaded file, named as in the form (`avatar`, `photos[]`, `doc[a]`) |
| `withCookie($name, $value)` | a cookie for this request |
| `withJwt([...])` | a bearer token for the payload, signed with the configured `JWTOptions` |
| `withCsrf()` | a valid CSRF cookie and header, bound to the session cookie the request carries; nothing when CSRF is off |
| `withHttps()` | a request that arrived over HTTPS |
| `asNavigation()` | `Sec-Fetch-Mode: navigate`, what a browser sends when it loads a page |

Cookies a test puts into `$_COOKIE`, and `$_SERVER['HTTPS']`, apply to every request as defaults. Output a controller echoes itself lands in front of the body, as it would under a server.

## Checking responses

A [`TestResponse`](../reference/Waypoint/Testing/TestResponse.md) has readers and fluent assertions:

| Readers | Assertions |
|---|---|
| `status()` | `assertStatus(404)`, `assertOk()` |
| `header('Location')`, `headers()` | `assertHeader($name, $value)`, `assertHeaderMissing($name)`, `assertContentType('text/html')` |
| `cookie('session')`, `cookies()` | `assertCookie($name, $value)` |
| `body()` | `assertSee($text)`, `assertDontSee($text)`, `assertBody($exact)` |
| `json()`, `json('errors.email')` | `assertJson([...])`, `assertJsonPath('errors.email', $value)` |
| | `assertRedirect('/login', 303)` |

A cookie that was removed reads as `''`. A redirect can be followed by sending the next request to its `Location`, carrying over the cookies the response set:

```php
public function testSignInLeadsToTheDashboard(): void
{
    $login = $this->request('POST', '/login')
        ->withBody(['email' => 'ada@example.com', 'password' => 'secret'])
        ->withCsrf()
        ->send()
        ->assertRedirect('/dashboard', 303);

    $this->request('GET', $login->header('Location'))
        ->withCookie('session', $login->cookie('session'))
        ->send()
        ->assertOk()
        ->assertSee('Hello');
}
```

## Files and directories

`$this->tempDir()` returns a fresh directory that is removed after the test, for example for a [cache directory](caching.md) or uploads:

```php
$this->app()->configure(fn (FileSystemOptions $fs) => $fs->cacheDirectory = $this->tempDir());
```

Testing with a cache directory also exercises the cached route plans, which is what production runs.

## Running

```bash
vendor/bin/phpunit
```

The requests run in the test process. For an app meant for Tenma, also run it under Tenma with parallel requests, which shows state shared between requests that a sequential test can't (see [Dependency injection](dependency-injection.md#controllers-per-request-services-per-process)).

See also: [`TestCase`](../reference/Waypoint/Testing/TestCase.md), [`TestRequest`](../reference/Waypoint/Testing/TestRequest.md), [`TestResponse`](../reference/Waypoint/Testing/TestResponse.md), [Plugins](plugins.md#testing-a-plugin).
