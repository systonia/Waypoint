---
title: "Waypoint\\Exceptions"
description: "The classes, interfaces, traits and enums in Waypoint\\Exceptions."
order: 200
generated: true
---

## Types

- [`ContainerException`](ContainerException.md) · class: Thrown by Container::get() when a service's constructor fails.
- [`ForbiddenException`](ForbiddenException.md) · class: HTTP 403 "Forbidden": $message becomes the RFC 9457 'detail', $code overrides the status.
- [`HttpException`](HttpException.md) · class: Base of every exception that maps to an RFC 9457 Problem Details response (see App's default handlers).
- [`NotFoundException`](NotFoundException.md) · class: HTTP 404 "Not Found": $message becomes the RFC 9457 'detail', $code overrides the status.
- [`ServiceNotFoundException`](ServiceNotFoundException.md) · class: Thrown by Container::get() for a service it has not registered and cannot construct: a logged 500, never a 404 naming the class.
- [`UnauthorizedException`](UnauthorizedException.md) · class: HTTP 401 "Unauthorized": $message becomes the RFC 9457 'detail', $code overrides the status.
- [`ValidationException`](ValidationException.md) · class: HTTP 422: field-level validation errors, exposed as the RFC 9457 extension member 'errors'.
