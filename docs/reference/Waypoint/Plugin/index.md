---
title: "Waypoint\\Plugin"
description: "The classes, interfaces, traits and enums in Waypoint\\Plugin."
order: 200
generated: true
---

## Types

- [`ArgumentBinder`](ArgumentBinder.md) · interface: Binds a plugin's own parameter attribute (or type) to a controller method argument.
- [`ClientAsset`](ClientAsset.md) · interface: CSS/JS files a plugin ships; compiled into the asset pipeline (content-hashed) and rendered by View::pluginAssetTags().
- [`Endpoint`](Endpoint.md) · interface: Answers a request before routing (after public files and view assets), like the OpenAPI endpoint.
- [`Fallback`](Fallback.md) · interface: Answers a request no route matched, before the 404: pages from a CMS, short links, legacy URLs.
- [`Guard`](Guard.md) · interface: Runs before the controller, after the core auth/CSRF checks.
- [`OpenApiExtension`](OpenApiExtension.md) · interface: Documents a plugin's own parameters (bound through ArgumentBinder) and return types (rendered through Renderer) in the OpenAPI spec.
- [`Plugin`](Plugin.md) · interface: A plugin is one Composer package with one class implementing this and at most one Options class.
- [`PluginBase`](PluginBase.md) · abstract class: Plugin with every optional part defaulting to "nothing"; override what you need.
- [`PluginRegistry`](PluginRegistry.md) · final class: The registered plugins, in registration order, and their hooks sorted by type.
- [`Renderer`](Renderer.md) · interface: Renders a plugin's own return type.
- [`ResponseHook`](ResponseHook.md) · interface: Runs after the controller's result was rendered and before the app-level middlewares unwind.
- [`RouteAttributeCompiler`](RouteAttributeCompiler.md) · interface: Turns a plugin's own route attributes into plain data at compile time.
- [`ViewHelper`](ViewHelper.md) · interface: Methods available inside templates as `$this->name(...)`.
