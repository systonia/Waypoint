---
title: "Waypoint\\Testing"
description: "The classes, interfaces, traits and enums in Waypoint\\Testing."
order: 200
generated: true
---

## Types

- [`TestCase`](TestCase.md) · abstract class: Base class for app and plugin tests: a fresh Waypoint per test, clean superglobals, and a request builder that drives App::handle() through Request::fromParts(), without header()/echo.
- [`TestRequest`](TestRequest.md) · final class: Builds one request and runs it through App::handle() via Request::fromParts(): no superglobals, no header()/echo.
- [`TestResponse`](TestResponse.md) · final class: What one TestRequest produced: status, headers, cookies, body, plus fluent assertions.
