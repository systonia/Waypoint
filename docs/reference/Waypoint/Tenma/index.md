---
title: "Waypoint\\Tenma"
description: "The classes, interfaces, traits and enums in Waypoint\\Tenma."
order: 200
generated: true
---

## Types

- [`AsyncLocalStorage`](AsyncLocalStorage.md) · final class: A FiberLocal's value in Tenma's async context: a task a request starts gets a copy of it.
- [`Bridge`](Bridge.md) · final class: Translates between Tenma (PHP with an event loop) and Waypoint for App::__invoke(), which makes an app Tenma's request handler: `Tenma::serve(3000, $app)`.
