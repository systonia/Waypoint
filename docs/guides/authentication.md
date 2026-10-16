---
title: Authentication
description: "Sign users in with JWTs as bearer tokens or cookies, protect forms with CSRF tokens, and guard routes with #[Authenticated], #[Role] and #[Permissions]."
order: 70
---

Waypoint signs users in with JSON Web Tokens. A token carries who the user is (`sub`, a role, whatever the app puts in) and is signed with the app's secret, so the server keeps no session store. An API client sends it as a bearer token; a browser keeps it in an HttpOnly cookie, protected against cross-site requests by CSRF tokens.

## Turning it on

```php
use Waypoint\Environment;
use Waypoint\Options\{CsrfOptions, JWTOptions};

$app->configure(function (JWTOptions $jwt, CsrfOptions $csrf, Environment $env) {
    $jwt->secret = $env->get('JWT_SECRET');
    $jwt->ttl = 8 * 3600;
    $jwt->cookieName = 'session';
    $jwt->loginRedirectUrl = '/login';

    $csrf->secret = $env->get('CSRF_SECRET');
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

An API hands the token to the client, which sends it back as `Authorization: Bearer <token>`:

```php
<?php

namespace App\Controllers\Api;

use App\Services\Accounts;
use Waypoint\Attributes\{Authenticated, Controller, Get, Inject, Post, SkipCsrf};
use Waypoint\Exceptions\UnauthorizedException;
use Waypoint\Http\Request;
use Waypoint\JWT;

#[Controller('/api')]
#[SkipCsrf]
final class TokenController
{
    #[Inject]
    private Accounts $accounts;

    #[Post('/tokens')]
    public function issue(Request $req): array
    {
        $user = $this->accounts->verify((string) $req->body('email', ''), (string) $req->body('password', ''))
            ?? throw new UnauthorizedException('Wrong email or password.');
        return ['token' => JWT::encode(['sub' => $user['id'], 'role' => $user['role']])];
    }

    #[Get('/me')]
    #[Authenticated]
    public function me(Request $req): array
    {
        return ['id' => $req->jwt['sub'], 'role' => $req->jwt['role']];
    }
}
```

`#[SkipCsrf]` is right here: these routes are called by programs with a bearer token, not by browsers with cookies.

## Signing in with a cookie

A browser that navigates to a page sends no `Authorization` header, so pages use a cookie. The login form posts to a route that sets it: [`JWT::signIn()`](../reference/Waypoint/JWT.md#signin) signs the claims and sets the `JWTOptions::$cookieName` cookie (HttpOnly, `SameSite=Lax`, `Max-Age` of `JWTOptions::$ttl`, `Secure` over HTTPS), and `JWT::signOut()` removes it:

```php
<?php

namespace App\Controllers;

use App\Services\Accounts;
use Waypoint\Attributes\{Controller, Get, Inject, Post};
use Waypoint\Http\{Redirect, Request, Response, View};
use Waypoint\JWT;

#[Controller]
final class SessionController
{
    #[Inject]
    private Accounts $accounts;

    #[Get('/login')]
    public function form(): View
    {
        return new View('Login', ['error' => null]);
    }

    #[Post('/login')]
    public function login(Request $req, Response $res): View|Redirect
    {
        $user = $this->accounts->verify((string) $req->body('email', ''), (string) $req->body('password', ''));
        if ($user === null) {
            return new View('Login', ['error' => 'Wrong email or password.']);
        }
        JWT::signIn($res, ['sub' => $user['id'], 'role' => $user['role']]);
        return new Redirect('/dashboard', 303);
    }

    #[Post('/logout')]
    public function logout(Response $res): Redirect
    {
        JWT::signOut($res);
        return new Redirect('/login', 303);
    }
}
```

The template carries the CSRF field (see [below](#csrf-protection)):

```php
<?php /** views/Login.php */ ?>
<form method="post" action="/login">
    <?= $this->csrf->field() ?>
    <?php if ($model['error'] !== null): ?>
        <p class="error"><?= $model['error'] ?></p>
    <?php endif ?>
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" required></label>
    <button>Sign in</button>
</form>
```

A cookie is sent by the browser on its own, also with requests another site triggers. Signing in by cookie therefore requires CSRF protection: with `JWTOptions::$cookieName` set and no `CsrfOptions::$secret`, every request fails with `500` until a secret is configured.

### Login pages

With `JWTOptions::$loginRedirectUrl` set, a page navigation to an `#[Authenticated]` route without a valid token answers `302` to that URL. `fetch()` calls and API clients still get `401` (see [Errors](errors.md#401-for-pages-and-for-apis)).

## Guarding routes

```php
<?php

namespace App\Controllers;

use Waypoint\Attributes\{Authenticated, Controller, Get, Permissions, Post, Role};
use Waypoint\Http\{Request, View};

#[Controller('/dashboard')]
#[Authenticated]
final class DashboardController
{
    #[Get('')]
    public function show(Request $req): View
    {
        return new View('Dashboard', ['user' => $req->jwt['sub']]);
    }

    #[Get('/reports')]
    #[Role('admin')]
    public function reports(): array
    {
        return ['reports' => []];
    }

    #[Post('/pages/{id}/publish')]
    #[Permissions(['pages.publish'])]
    public function publish(int $id): array
    {
        return ['published' => $id];
    }
}
```

| Attribute | Requires | Fails with |
|---|---|---|
| `#[Authenticated]` | a valid token (`$req->jwt` is set) | `401`, or the redirect to `loginRedirectUrl` |
| `#[Role('admin')]` | `$req->jwt['role'] === 'admin'` | `403` |
| `#[Permissions(['a', 'b'])]` | every listed permission, from the permission provider | `403` |

`#[Role]` and `#[Permissions]` imply `#[Authenticated]`. All three work on a class (every route, also of subclasses) and on a method; for `#[Role]` and `#[Permissions]` the method's attribute replaces the class's. The checks run before route middlewares and before arguments are bound.

### Claims as a DTO

`#[Claims]` binds the token's payload to a parameter typed with a [`FromArray`](binding.md#fromarray) DTO, so a route works with typed claims instead of `$req->jwt['sub']`:

```php
<?php

namespace App\Auth;

use Waypoint\FromArray;

final class UserClaims
{
    use FromArray;

    public int $sub;
    public string $role = 'user';
}
```

```php
#[Get('/me')]
public function me(#[Claims] UserClaims $user): array
{
    return ['id' => $user->sub, 'role' => $user->role];
}
```

`#[Claims]` implies `#[Authenticated]`: without a valid token the route answers `401`, and so does a token whose claims don't fill the DTO (here, one without a `sub`). A nullable parameter (`#[Claims] ?UserClaims $user`) makes the user optional: it is `null` then. `$req->jwt` itself is the payload as an array, or `null`.

### Roles and permissions

`#[Role]` compares one claim. For anything finer, `#[Permissions]` asks a [`PermissionProvider`](../reference/Waypoint/PermissionProvider.md), which the app implements and registers in [`PermissionOptions`](../reference/Waypoint/Options/PermissionOptions.md):

```php
<?php

namespace App\Security;

use Waypoint\PermissionProvider;

final class RolePermissions implements PermissionProvider
{
    private const GRANTS = [
        'editor' => ['pages.edit'],
        'admin' => ['pages.edit', 'pages.publish'],
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

A requirement that doesn't fit the attributes (a verified email, an active subscription) is a [route middleware](middleware.md#route-middleware) that throws `ForbiddenException`, or, as a reusable package with its own attribute, a plugin `Guard` (see [Plugins](plugins.md)).

## CSRF protection

A cross-site request forgery is a page on another site that makes the browser send a request to yours, with your cookies. Waypoint's protection is stateless double-submit: a signed token sits in a cookie that JavaScript can read, and each state-changing request has to repeat it in a header or a form field. Another site can make the browser send the cookie, but can't read it to repeat it.

It is on as soon as `CsrfOptions::$secret` is set. Then every `POST`, `PUT`, `PATCH` and `DELETE` request must carry the token, on every route without `#[SkipCsrf]`; there is no middleware to add. A missing, wrong or expired token answers `403`.

- **Issued**: every response that renders a `View` sets the token cookie (`csrf_token`), or keeps a still-valid one.
- **Forms**: `<?= $this->csrf->field() ?>` in the template writes a hidden `_csrf` field.
- **`fetch()`**: a page that loads `waypoint.js` (`<?= $this->waypointJsTag() ?>` in the layout, see [Views](views.md#waypointjs)) adds the `X-CSRF-Token` header to every same-origin `POST`, `PUT`, `PATCH` and `DELETE` on its own. Without it, read the cookie and send the header yourself.
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
