---
title: Authentication
description: "Sign users in with JWTs as bearer tokens or cookies, protect forms with CSRF tokens, and guard routes with #[Authenticated], #[Role] and #[Permissions]."
order: 70
---

Waypoint signs users in with JSON Web Tokens. A token carries who the user is (`sub`, a role, whatever the app puts in) and is signed with the app's secret, so the server keeps no session store. An API client sends it as a bearer token; a browser keeps it in an HttpOnly cookie, protected against cross-site requests by CSRF tokens.

## Turning it on

The bakery's back office signs in with a cookie, its JSON API with bearer tokens. From `app.php`:

```php
use Waypoint\Environment;
use Waypoint\Options\{CsrfOptions, JWTOptions};

$env = $app->get(Environment::class);

$app->configure(function (JWTOptions $jwt, CsrfOptions $csrf) use ($env) {
    // The back office signs in with a JWT in an HttpOnly cookie; forms and fetch() carry CSRF tokens
    $jwt->secret = $env->string('JWT_SECRET');
    $jwt->cookieName = 'session';
    $jwt->ttl = 12 * 3600;
    $jwt->loginRedirectUrl = '/backoffice/login';
    $csrf->secret = $env->string('CSRF_SECRET');
});

$app->useJwt();
```

[`useJwt()`](../reference/Waypoint/App.md#usejwt) adds a middleware that reads the token from the `Authorization: Bearer …` header, or, with `cookieName` set, from that cookie, and puts the decoded payload into `$req->jwt`. A missing, malformed, expired or wrongly signed token leaves `$req->jwt` at `null`; the request goes on, and the routes decide whether they need a user.

The secrets have no default. Reading an unset secret throws, and a secret shorter than 32 bytes is refused, so a missing `.env` entry never signs tokens with an empty key. Generate them with `php -r 'echo bin2hex(random_bytes(32)), "\n";'`.

| [`JWTOptions`](../reference/Waypoint/Options/JWTOptions.md) | Default | |
|---|---|---|
| `secret` | none | HMAC key, at least 32 bytes |
| `ttl` | `3600` | lifetime of a token in seconds |
| `header`, `tokenType` | `Authorization`, `Bearer` | where the bearer token is read from |
| `cookieName` | `null` | cookie to read the token from when the header has none |
| `loginRedirectUrl` | `null` | where a page navigation without a valid token is redirected |

## Issuing tokens

[`JWT::encode()`](../reference/Waypoint/JWT.md#encode) signs a payload with HS256 and sets `exp` from `JWTOptions::$ttl` (or its second argument). [`JWT::decode()`](../reference/Waypoint/JWT.md#decode) returns the payload, or `null` for a token that is malformed, wrongly signed, not HS256, expired or without `exp`.

An API hands the token to the client, which sends it back as `Authorization: Bearer <token>`. The till and the delivery app of the bakery exchange an email and a password for one (shortened from `src/Controllers/ApiController.php`):

```php
<?php

namespace Bakery\Controllers;

use Bakery\Repositories\{Orders, Users};
use Bakery\Requests\Credentials;
use Bakery\Responses\{BearerToken, OrderSummary};
use Waypoint\Attributes\{Authenticated, Body, Controller, Get, Inject, Post, Returns, SkipCsrf, Version};
use Waypoint\Exceptions\UnauthorizedException;
use Waypoint\JWT;

#[Controller]
#[Version('v1')]
#[SkipCsrf]
final class ApiController
{
    #[Inject]
    private Users $users;

    #[Inject]
    private Orders $orders;

    #[Post('/tokens')]
    public function token(#[Body] Credentials $credentials): BearerToken
    {
        $user = $this->users->verify($credentials->email, $credentials->password)
            ?? throw new UnauthorizedException('Wrong email or password.');
        return new BearerToken(JWT::encode($user));
    }

    #[Get('/orders')]
    #[Authenticated]
    #[Returns(
        type: OrderSummary::class,
        list: true
    )]
    public function openOrders(): array
    {
        return array_map(OrderSummary::of(...), $this->orders->open());
    }
}
```

`Users::verify()` returns the claims of the person (`['sub' => 2, 'name' => 'Ben', 'role' => 'staff']`) or `null`, and `JWT::encode()` signs them as they are.

`#[SkipCsrf]` is right here: these routes are called by programs with a bearer token, not by browsers with cookies.

## Signing in with a cookie

A browser that navigates to a page sends no `Authorization` header, so pages use a cookie. The login form posts to a route that sets it: [`JWT::signIn()`](../reference/Waypoint/JWT.md#signin) signs the claims and sets the `JWTOptions::$cookieName` cookie (HttpOnly, `SameSite=Lax`, `Max-Age` of `JWTOptions::$ttl`, `Secure` over HTTPS), and `JWT::signOut()` removes it:

```php
<?php

namespace Bakery\Controllers;

use Bakery\Repositories\Users;
use Bakery\Requests\LoginForm;
use Waypoint\Attributes\{Body, Controller, Get, Inject, Post};
use Waypoint\Flash\Flash;
use Waypoint\Http\{Redirect, Response, View};
use Waypoint\JWT;

#[Controller('/backoffice')]
final class SessionController
{
    #[Inject]
    private Users $users;

    #[Inject]
    private Flash $flash;

    #[Get('/login')]
    public function form(): View
    {
        return new View('Backoffice/Login', new LoginForm(), layout: '_Backoffice.php');
    }

    /** A View with the form while it is invalid answers 422. */
    #[Post('/login')]
    public function login(
        #[Body(
            validate: false
        )]
        LoginForm $form,
        Response $res,
    ): View|Redirect {
        $user = $form->isValid ? $this->users->verify($form->email, $form->password) : null;
        if ($user === null) {
            if ($form->isValid) {
                $form->fail('password', 'Wrong email or password.');
            }
            return new View('Backoffice/Login', $form, layout: '_Backoffice.php');
        }
        JWT::signIn($res, $user);
        $this->flash->success("Welcome, {$user['name']}.");
        return new Redirect('/backoffice', 303);
    }

    #[Post('/logout')]
    public function logout(Response $res): Redirect
    {
        JWT::signOut($res);
        return new Redirect('/backoffice/login', 303);
    }
}
```

`#[Body(validate: false)]` hands the route the form even when it is invalid, so the page can show it again with its errors (see [Binding and validation](binding.md)). `Flash` comes from the Waypoint Flash plugin. The template carries the CSRF field (see [below](#csrf-protection)), shortened from `views/Backoffice/Login.php`:

```html
@model (Bakery\Requests\LoginForm)

@code
    $errors = $this->request?->method === 'POST' ? $model->errors : []
@endcode

@section('title')
    Sign in
@endsection

<form method="post" action="/backoffice/login">
    {{ $this->csrf->field() }}
    <label>
        Email
        <input type="email" name="email" value="{{ $model->email }}" required autocomplete="username">
        @if (isset($errors['email']))
            <span class="error">{{ $errors['email'] }}</span>
        @endif
    </label>
    <label>
        Password
        <input type="password" name="password" required autocomplete="current-password">
        @if (isset($errors['password']))
            <span class="error">{{ $errors['password'] }}</span>
        @endif
    </label>
    <button>Sign in</button>
</form>
```

A cookie is sent by the browser on its own, also with requests another site triggers. Signing in by cookie therefore requires CSRF protection: with `JWTOptions::$cookieName` set and no `CsrfOptions::$secret`, every request fails with `500` until a secret is configured.

### Login pages

With `JWTOptions::$loginRedirectUrl` set, a page navigation to an `#[Authenticated]` route without a valid token answers `302` to that URL. `fetch()` calls and API clients still get `401` (see [Errors](errors.md#401-for-pages-and-for-apis)).

## Guarding routes

Everyone in the bakery sees the order board and the stock; the sales and the exports are for the owner (from `src/Controllers/BackofficeController.php`, with a permission check added):

```php
<?php

namespace Bakery\Controllers;

use Waypoint\Attributes\{Authenticated, Controller, Get, Permissions, Post, Role};
use Waypoint\Http\View;

#[Controller('/backoffice')]
#[Authenticated]
final class BackofficeController
{
    #[Get('')]
    public function board(): View
    {
        return new View('Backoffice/Board', layout: '_Backoffice.php');
    }

    #[Get('/sales')]
    #[Role('owner')]
    public function sales(): View
    {
        // …
    }

    #[Post('/products/{id}/price')]
    #[Permissions(['prices.edit'])]
    public function setPrice(int $id): array
    {
        // …
    }
}
```

| Attribute | Requires | Fails with |
|---|---|---|
| `#[Authenticated]` | a valid token (`$req->jwt` is set) | `401`, or the redirect to `loginRedirectUrl` |
| `#[Role('owner')]` | `$req->jwt['role'] === 'owner'` | `403` |
| `#[Permissions(['a', 'b'])]` | every listed permission, from the permission provider | `403` |

`#[Role]` and `#[Permissions]` imply `#[Authenticated]`. All three work on a class (every route, also of subclasses) and on a method; for `#[Role]` and `#[Permissions]` the method's attribute replaces the class's. The checks run before route middlewares and before arguments are bound.

### Claims as a DTO

`#[Claims]` binds the token's payload to a parameter typed with a [`FromArray`](binding.md#fromarray) DTO, so a route works with typed claims instead of `$req->jwt['sub']`:

```php
<?php

namespace Bakery\Requests;

use Waypoint\FromArray;

/** What Users::verify() puts into the token. */
final class StaffClaims
{
    use FromArray;

    public int $sub;
    public string $name = '';
    public string $role = 'staff';
}
```

```php
#[Get('/me')]
public function me(#[Claims] StaffClaims $staff): array
{
    return ['name' => $staff->name, 'role' => $staff->role];
}
```

`#[Claims]` implies `#[Authenticated]`: without a valid token the route answers `401`, and so does a token whose claims don't fill the DTO (here, one without a `sub`). A nullable parameter (`#[Claims] ?StaffClaims $staff`) makes the user optional: it is `null` then. `$req->jwt` itself is the payload as an array, or `null`.

### Roles and permissions

`#[Role]` compares one claim. For anything finer, `#[Permissions]` asks a [`PermissionProvider`](../reference/Waypoint/PermissionProvider.md), which the app implements and registers in [`PermissionOptions`](../reference/Waypoint/Options/PermissionOptions.md):

```php
<?php

namespace Bakery\Services;

use Waypoint\PermissionProvider;

final class RolePermissions implements PermissionProvider
{
    private const GRANTS = [
        'staff' => ['stock.edit'],
        'owner' => ['stock.edit', 'prices.edit', 'sales.view'],
    ];

    public function hasPermission(mixed $jwt, string $permission): bool
    {
        $role = is_array($jwt) ? ($jwt['role'] ?? '') : '';
        return in_array($permission, self::GRANTS[$role] ?? [], true);
    }
}
```

```php
$app->configure(fn (PermissionOptions $permissions) => $permissions->provider = new RolePermissions());
```

`$jwt` is the token's payload. A provider can look the user up in a database as well. Without a provider every `#[Permissions]` check fails, so a forgotten setup never opens a route. A plugin can provide one by binding `PermissionProvider` in the container; one set in `PermissionOptions` wins.

### Further checks

A requirement that doesn't fit the attributes (only from the bakery's own network, only during opening hours) is a [route middleware](middleware.md#route-middleware) that throws `ForbiddenException`, or, as a reusable package with its own attribute, a plugin `Guard` (see [Plugins](plugins.md)).

## CSRF protection

A cross-site request forgery is a page on another site that makes the browser send a request to yours, with your cookies. Waypoint's protection is stateless double-submit: a signed token sits in a cookie that JavaScript can read, and each state-changing request has to repeat it in a header or a form field. Another site can make the browser send the cookie, but can't read it to repeat it.

It is on as soon as `CsrfOptions::$secret` is set. Then every `POST`, `PUT`, `PATCH` and `DELETE` request must carry the token, on every route without `#[SkipCsrf]`; there is no middleware to add. A missing, wrong or expired token answers `403`.

- **Issued**: every response that renders a `View` sets the token cookie (`csrf_token`), or keeps a still-valid one.
- **Forms**: `{{ $this->csrf->field() }}` in the template writes a hidden `_csrf` field.
- **`fetch()`**: a page that loads `waypoint.js` (`{{ $this->waypointJsTag() }}` in the layout, see [Views](views.md#waypointjs)) adds the `X-CSRF-Token` header to every same-origin `POST`, `PUT`, `PATCH` and `DELETE` on its own. Without it, read the cookie and send the header yourself.
- **Bound to the session**: with `JWTOptions::$cookieName` set, a token is signed for the session cookie it was issued with, so a token from one session doesn't work in another. A response that signs in or out gets a fresh token with it.

| [`CsrfOptions`](../reference/Waypoint/Options/CsrfOptions.md) | Default | |
|---|---|---|
| `secret` | none | HMAC key, at least 32 bytes; unset means no CSRF checks at all |
| `ttl` | `3600` | lifetime of a token and its cookie in seconds |
| `cookieName` | `csrf_token` | readable by JavaScript on purpose |
| `headerName`, `fieldName` | `X-CSRF-Token`, `_csrf` | where the token is repeated |
| `cookieSecure`, `cookieSameSite` | `null`, `Lax` | the cookie's attributes; `null` sets `Secure` exactly when the request came over HTTPS (`$req->https`, behind a [trusted proxy](proxies.md) as it says) |

### #[SkipCsrf]

`#[SkipCsrf]` on a controller or method turns the check off for those routes: endpoints for programs that authenticate with a bearer token, webhooks that verify their own signature. On such a route a token from the session cookie is ignored for `POST`, `PUT`, `PATCH` and `DELETE`: without the CSRF check the cookie proves nothing, so `$req->jwt` is only set from the `Authorization` header there.

See also: [`JWT`](../reference/Waypoint/JWT.md), [`Csrf`](../reference/Waypoint/Csrf.md), [`Authenticated`](../reference/Waypoint/Attributes/Authenticated.md), [`Role`](../reference/Waypoint/Attributes/Role.md), [`Permissions`](../reference/Waypoint/Attributes/Permissions.md), [`SkipCsrf`](../reference/Waypoint/Attributes/SkipCsrf.md), [Security](security.md).
