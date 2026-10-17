---
title: "Waypoint\\Http"
description: "The classes, interfaces, traits and enums in Waypoint\\Http."
order: 200
generated: true
---

## Types

- [`MiddlewareBase`](MiddlewareBase.md) · abstract class: Base class for every #[Middleware(...)] handler, also usable directly on `$app->use()` via __invoke().
- [`PublicFileServer`](PublicFileServer.md) · final class: Serves the app's own static files from FileSystemOptions::$publicDirectory, and only from there: a path with a '..' or any other dot-segment, a dotfile or dot-directory (.env, .git/), or a PHP source file is never served, and the resolved file (symlinks followed) must lie inside the directory itself, not merely start with its name, which a sibling like `public-backup` would.
- [`Redirect`](Redirect.md) · final class: Returned from a route method to send a redirect instead of a body: `return new Redirect('/backoffice')` next to the View the same handler renders on failure (POST/redirect/GET).
- [`Request`](Request.md) · class: The incoming HTTP request.
- [`Response`](Response.md) · class: The outgoing response, built up by middleware/controllers.
- [`ResultRenderer`](ResultRenderer.md) · final class: Writes a route's return value to the Response as JSON (default), a raw file/body (#[FileFormatter]) or XML (#[SimpleXmlFormatter]).
- [`SecurityHeadersMiddleware`](SecurityHeadersMiddleware.md) · class: Fills in the standard security headers (SecurityHeaderOptions) in before(), so they are there on an error a later middleware throws too (after() would be skipped then); a controller or middleware setting one of them afterwards replaces it, and one set before is kept.
- [`UploadedFile`](UploadedFile.md) · final class: A file from a multipart/form-data request, the same under FPM, Tenma and any PSR-7 server: `$req->file('picture')?->moveTo($dir . '/' . bin2hex(random_bytes(8)))`.
- [`View`](View.md) · class: A server-rendered template result: `return new View('Backoffice/Sales', $model)` renders views/Backoffice/Sales.php inside RendererOptions::$layout (unless $partial).
