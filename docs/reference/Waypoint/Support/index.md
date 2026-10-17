---
title: "Waypoint\\Support"
description: "The classes, interfaces, traits and enums in Waypoint\\Support."
order: 200
generated: true
---

## Types

- [`Arr`](Arr.md) · final class: Narrowing helpers for values whose shape PHPStan can't know statically: a `require`d cache file, a superglobal, json_decode() output.
- [`Base64Url`](Base64Url.md) · final class: RFC 4648 §5 base64url (no padding) plus the HMAC-SHA256 signing JWT and Csrf tokens share.
- [`ErrorLogLogger`](ErrorLogLogger.md) · final class: Where Waypoint's errors go while the app configured no logger: PHP's error_log, so stderr under Tenma and php -S, the server's error log under FPM.
- [`FiberLocal`](FiberLocal.md) · final class: One value per Fiber, for state that belongs to a single request.
- [`LocalStorage`](LocalStorage.md) · interface: Where a FiberLocal keeps its value, see FiberLocal::useStorage().
- [`Row`](Row.md) · final readonly class: A database row or decoded JSON, read with typed getters instead of casts.
- [`Uuid`](Uuid.md) · final class: Random RFC 9562 UUIDs.
