# waypoint.js

Waypoint's client half: one dependency-free script that enhances plain HTML through
`wp-*` attributes and repeats the CSRF token on `fetch()`. Links and forms stay plain browser
navigation.

This directory is the TypeScript source and its tests. `npm run build` writes the bundle to
`../src/Waypoint/UI/waypoint.js`, which is committed and shipped with the PHP package, where
`View::waypointJsTag()` serves it as a content-hashed asset. Apps never build, copy or
reference the file themselves, and never need Node. The `ui/` directory is `export-ignore`d, so
it isn't part of the Composer distribution.

## Attributes

| Attribute | On | Does |
|---|---|---|
| `wp-onclick="name"` | any element | Calls the named handler with the element's `dataset`. |
| `wp-onsubmit="name"` | `<form>` | Calls the named handler with the form serialized to an object (`items[]`, `address[city]` like PHP); the native submit is prevented unless `wp-default="true"`. |

Handlers are plain global functions or registered via `Waypoint.handlers.register(name, fn)`;
nothing is ever eval'd, so a strict CSP is fine.

## Behaviour

- Every same-origin `POST/PUT/PATCH/DELETE` `fetch()` carries the CSRF header, read from the
  cookie. Non-default names arrive as `data-csrf-cookie`/`data-csrf-header` on the script tag,
  which the framework sets from `CsrfOptions`.

## Global

```js
Waypoint.handlers.register('save', (data, event) => {});
Waypoint.csrf.token();
```

## Plugin scripts

A server-side plugin ships JavaScript through its `ClientAsset` hook; the framework serves it
content-hashed and `$this->pluginAssetTags()` renders it after `waypointJsTag()`, so `window.Waypoint`
already exists. Two entry points let such a script add behaviour without touching this bundle: `Waypoint.directive()` and `Waypoint.handlers`.

```js
// a delegated wp-* directive, also for elements added later
Waypoint.directive('wp-sort', 'click', (el, event) => {
    // ...
});
```

## Development

From the repository root:

```sh
composer build:ui   # npm ci + type-check + bundle into src/Waypoint/UI/waypoint.js
composer test:ui    # npm ci + vitest
composer check:ui   # npm ci + is the committed bundle current? (what CI runs; exit 1 if stale)
```

Or inside `ui/`:

```sh
npm install
npm test          # vitest + happy-dom
npm run check     # tsc --noEmit
npm run build     # type-check, bundle (esbuild, ES2019, minified) into ../src/Waypoint/UI/waypoint.js
npm run dev       # rebuild on change, with an inline source map (never commit that build)
```

Layout: one module per concern under `src/` (`dom`, `csrf`, `handlers`, `form`, `directives`), plain functions, no classes. A new directive is a `delegate()` call in
`directives.ts` or a new module registered from `index.ts`. Commit the rebuilt bundle together with
the source change; the UI workflow fails when the committed bundle is out of date.
