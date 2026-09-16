# waypoint.js

Waypoint's client half: one dependency-free script that enhances plain HTML through
`wp-*` attributes. Every link and form keeps working without it; with it, navigation
swaps fragments instead of reloading the page.

This directory is the TypeScript source and its tests. `npm run build` writes the bundle to
`../src/Waypoint/UI/waypoint.js`, which is committed and shipped with the PHP package, where
`View::waypointJsTag()` serves it as a content-hashed asset. Apps never build, copy or
reference the file themselves, and never need Node. The `ui/` directory is `export-ignore`d, so
it isn't part of the Composer distribution.

## Attributes

| Attribute | On | Does |
|---|---|---|
| `wp-target="key"` | `<a>`, `<form>` | Re-issues the link/form request with `X-Waypoint-Accept: partial` and swaps the response into `[wp-container="key"]`. Without JS the link and form work exactly the same, with the layout around it. |
| `wp-container="key"` | any element | The swap target for `wp-target="key"`. |
| `wp-swap-oob="key"` | `<template>` in a response | Extra fragment routed to another container in the same round trip. |
| `wp-onclick="name"` | any element | Calls the named handler with the element's `dataset`. |
| `wp-onsubmit="name"` | `<form>` | Calls the named handler with the form serialized to an object (`items[]`, `address[city]` like PHP); the native submit is prevented unless `wp-default="true"`. |

Handlers are plain global functions or registered via `Waypoint.handlers.register(name, fn)`;
nothing is ever eval'd, so a strict CSP is fine.

## Behaviour

- A redirect after a form POST is followed by `fetch()`; history records the final URL.
- A 401 on an authenticated page is already a server-side redirect to the login page for a
  partial navigation (`JWTOptions::$loginRedirectUrl`), so the client needs no special case.
- An HTML error response (a 404 page, a re-rendered form) is shown; a non-HTML error is logged.
- A response's `X-Waypoint-View-Name/-Css/-Js` headers set `data-view` on the container and
  inject the view's own CSS/JS once, next to this script's own URL.
- Every same-origin `POST/PUT/PATCH/DELETE` `fetch()` carries the CSRF header, read from the
  cookie. Non-default names arrive as `data-csrf-cookie`/`data-csrf-header` on the script tag,
  which the framework sets from `CsrfOptions`.
- `wp:navigated` is dispatched on `document` after every swap (`detail: {href, target}`).

## Global

```js
Waypoint.navigate('/path', 'main');
Waypoint.handlers.register('save', (data, event) => {});
Waypoint.csrf.token();
```

## Plugin scripts

A server-side plugin ships JavaScript through its `ClientAsset` hook; the framework serves it
content-hashed and `$this->pluginAssetTags()` renders it after `waypointJsTag()`, so `window.Waypoint`
already exists. Three entry points let such a script add behaviour without touching this bundle:

```js
// a delegated wp-* directive, valid for elements swapped in later too
Waypoint.directive('wp-sort', 'click', (el, event) => {
    event.preventDefault();
    const container = el.closest('[wp-container]').getAttribute('wp-container');
    Waypoint.navigate(el.getAttribute('href'), container);
});

// after every partial navigation
Waypoint.onNavigated(({ href, target }) => { /* re-read state, focus, analytics */ });

// put HTML into a container yourself (scripts run, wp-swap-oob templates routed)
Waypoint.swap('main', html);
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

Layout: one module per concern under `src/` (`dom`, `csrf`, `handlers`, `form`, `fragments`,
`navigation`, `directives`), plain functions, no classes. A new directive is a `delegate()` call in
`directives.ts` or a new module registered from `index.ts`. Commit the rebuilt bundle together with
the source change; the UI workflow fails when the committed bundle is out of date.
