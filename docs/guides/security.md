---
title: Security
description: What Waypoint protects by default, what the app has to do itself, and a checklist before going live.
order: 170
---

Waypoint's defaults are chosen to fail closed: a missing secret stops the app instead of signing with an empty key, a missing permission provider denies instead of allowing, an unexpected exception shows nothing instead of its message. This page collects what it does and what remains the app's job.

## What Waypoint does

**Input**

- Path and query values are converted to the parameter's type, and a value that doesn't fit answers `422` before the controller runs. `#[Body]` DTOs are hydrated only into public, writable properties and validated before the controller runs.
- A body is decoded as JSON only when its `Content-Type` says JSON, so a cross-site form can't post a JSON request without a CORS preflight.
- `$req->path` is percent-decoded segment by segment; an encoded slash never creates a new segment. A check on `$req->path` in a middleware covers every spelling that reaches the same route.
- Request headers are never read as environment variables, under any SAPI.
- An incoming `X-Request-Id` is only taken when it is short and made of plain characters, so a client can't write into the logs.

**Authentication and CSRF**

- JWTs are HS256 only, must carry a future `exp`, and are compared in constant time. Secrets shorter than 32 bytes are refused.
- CSRF protection covers every `POST`, `PUT`, `PATCH` and `DELETE` once its secret is set. Tokens are bound to the session cookie, and a sign-in or sign-out issues a new one.
- Signing in by cookie without CSRF protection is refused: every request fails until `CsrfOptions::$secret` is set.
- On a `#[SkipCsrf]` route, a session cookie doesn't sign the user in for state-changing methods.
- `#[Permissions]` without a provider denies. A `#[Middleware]` class that doesn't exist or isn't a `MiddlewareBase` stops the route compilation, so a route never runs without the check it declares.

**Output**

- An unexpected exception answers a generic `500`; its message, file and trace go to the log only. An error response drops the cookies and content headers the failed action had set.
- Cookies from `withCookie()` are `HttpOnly` and `SameSite=Lax` by default.
- A `Redirect` refuses an empty location or one with a line break; a download file name loses quotes and line breaks.
- `SecurityHeadersMiddleware` adds `nosniff`, `X-Frame-Options: DENY`, a referrer policy and HSTS on HTTPS.

**Files**

- The public directory never serves dotfiles, PHP source or anything outside itself, also through symlinks.
- View names with `..`, a leading `/` or a backslash are refused.
- `#[FileFormatter]` sends a returned string as content, never as a path.
- The route cache is `require`d, so it lives only where the app says, never in a shared temporary directory.

## What the app does

- **Keep markup in `Html`.** Views escape what `{{ }}` and `<?= ?>` print; only a `Waypoint\Html` value or `<?php echo ?>` goes out unescaped. Never wrap a value from the model, the request or the database in `Html`.
- **Keep secrets out of the repository.** Read them from [`Environment`](application.md#environment-and-env-files): `.env` and `config.local.json` belong in `.gitignore`.
- **Serve over HTTPS.** Cookies (`withCookie()`, the CSRF cookie) get `Secure` when the request came over HTTPS; behind a proxy that ends TLS, list it in `ProxyOptions` so `$req->https` is right, or set `secure: true` and `CsrfOptions::$cookieSecure = true` yourself.
- **Check redirect targets.** Never redirect to a URL from the request without checking it against your own paths.
- **Treat uploads as untrusted.** Never use the client's file name as a path; check the content, not the declared type (see [Requests and responses](requests-responses.md#uploaded-files)).
- **Keep tokens short-lived.** A JWT stays valid until it expires and can't be revoked individually. Keep `JWTOptions::$ttl` short, and check whatever must be revocable (a disabled account) against the database in a [permission provider](authentication.md#roles-and-permissions) or middleware.
- **Check plugin endpoints yourself.** `Endpoint` and `Fallback` hooks run without route checks.
- **Check `Origin` on WebSockets** that act for a signed-in user (see [Tenma](tenma.md#streams-and-websockets)).
- **Trust only your proxies** (see [Proxies](proxies.md)).
- **Decide about the OpenAPI document.** Enabled, it is readable by anyone who reaches the app.
- **No per-request state in services** under Tenma: it leaks one user's data into another's request (see [Dependency injection](dependency-injection.md#controllers-per-request-services-per-process)).
- **Log DTOs redacted.** Mark credentials `#[Sensitive]` and personal data `#[PII]`, and pass objects through `Waypoint::redact()` before logging them.

## Checklist before going live

- [ ] `JWT_SECRET` and `CSRF_SECRET` set from the environment, 32 bytes or more, different from development
- [ ] `APP_ENV=production` (or unset under PHP-FPM and Tenma without `--watch`)
- [ ] `$app->use(new SecurityHeadersMiddleware())`, with a Content Security Policy where possible
- [ ] HTTPS in front, `ProxyOptions::$trusted` listing exactly the proxies
- [ ] a [cache directory](caching.md) writable only by the app's user
- [ ] OpenAPI off, or meant to be public
- [ ] a logger configured, so errors reach someone (see [Logging](logging.md))
- [ ] `composer audit` clean

See also: [Authentication](authentication.md), [Middleware](middleware.md#security-headers), [Deployment](deployment.md).
