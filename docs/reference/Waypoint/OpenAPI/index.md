---
title: "Waypoint\\OpenAPI"
description: "The classes, interfaces, traits and enums in Waypoint\\OpenAPI."
order: 200
generated: true
---

## Types

- [`OpenAPIEndpoint`](OpenAPIEndpoint.md) · final class: Serves the OpenAPI document and Swagger UI under OpenAPIOptions::$path once OpenAPIOptions::$enabled is set.
- [`OpenAPIGenerator`](OpenAPIGenerator.md) · class: Builds an OpenAPI 3.1 document from the Router's route list and the attribute cache RouteCompiler::exportAllAttributes() wrote, with no controller reflection at request time; only DTO classes are reflected, by SchemaBuilder.
- [`SchemaBuilder`](SchemaBuilder.md) · final class: Builds and collects the `components.schemas` of a spec: one object schema per DTO class (public properties, typed; nested DTOs by $ref, recursion- safe; enums inline with their values; lists from `@var list<X>`), plus the shared RFC 9457 ProblemDetails schema.
