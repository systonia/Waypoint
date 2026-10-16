---
title: "Waypoint\\Routing"
description: "The classes, interfaces, traits and enums in Waypoint\\Routing."
order: 200
generated: true
---

## Types

- [`ArgumentResolver`](ArgumentResolver.md) · final class: Turns a compiled argPlan (see RouteCompiler::buildArgPlan()) into the actual argument list for a route method call.
- [`PropertyInjector`](PropertyInjector.md) · final class: Sets #[Inject] properties.
- [`RouteGuard`](RouteGuard.md) · final class: The per-request access checks a compiled route carries: #[Authenticated]/ #[Role]/#[Permissions] (folded into 'authenticated'/'role'/'permissions' by RouteCompiler) and CSRF verification for state-changing methods unless #[SkipCsrf].
- [`ServiceDiscovery`](ServiceDiscovery.md) · final class: Follows #[Inject] properties/constructor parameters transitively from the attached controllers to find every class the container must construct at boot.
