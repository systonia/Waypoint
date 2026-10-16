---
title: "ViewAssets"
description: "Discovers each view's sibling .css/.js (Home.php -> Home.css/Home.js, any depth under the views directory), scopes the CSS to that view with a `[data-view=\"Name\"]` nesting wrapper, and content-hashes the result into cache-busted filenames."
order: 100
generated: true
---

`Waypoint\ViewAssets` · final class · namespace [`Waypoint`](index.md)

```php
final class ViewAssets
```

Discovers each view's sibling .css/.js (Home.php -> Home.css/Home.js, any
depth under the views directory), scopes the CSS to that view with a
`[data-view="Name"]` nesting wrapper, and content-hashes the result into
cache-busted filenames. FileSystem stores the files; Router serves them by
a strict {filename => mime} lookup, never by a path built from the request.

## Constants

### WAYPOINT_JS

```php
public const WAYPOINT_JS = '/Users/lento/Coding/Leisenfeld Media/Systonia/Waypoint/src/Waypoint/UI/waypoint.js';
```

The bundled client (built by the Waypoint-UI project, shipped inside this package); compiled into the same pipeline by Router.

## Methods

### discoverViewNames()

```php
public static function discoverViewNames(string $viewsDir): array
```

Every view file under $viewsDir as its View name: the path relative to $viewsDir, no .php,
'/'-separated on every platform ("Home", "Admin/Users"). The same string keys compile()'s
'views' map and is baked into the CSS scope, so a nested view's assets resolve exactly like a
top-level one's.

**Parameters**

- `$viewsDir` `string`

**Returns** `string[]`

### discoverMeta()

```php
public static function discoverMeta(string $viewsDir): array
```

{path => mtime} of every sibling asset -- the cheap freshness check (no hashing) that lets
FileSystem::isAvailable() notice an edited .css/.js the same way it notices an edited controller.

**Parameters**

- `$viewsDir` `string`

**Returns** `array<string, int>`

### compile()

```php
public static function compile(string $viewsDir): array
```

The full compile pass.

**Parameters**

- `$viewsDir` `string`

**Returns** `array{ views: array<string, array{css: ?string, js: ?string}>, files: array<string, array{content: string, mime: string}>, meta: array<string, int>, }`: 'views': name => its cache-busted filenames; 'files': what GET /assets/{filename} serves (the scoped content itself, so serving never depends on the source file); 'meta': discoverMeta().

### scopeCss()

```php
public static function scopeCss(string $css, string $viewName): string
```

Wraps $css in one native CSS Nesting rule, `[data-view="$viewName"] { ... }`, and lets the
browser resolve the scope -- nested rules become descendant selectors, and nesting is spec'd to
work inside @media/@supports/@container too. Selector text is never touched, so a comma inside
`:is(.a, .b)` can't break anything. Top-level @keyframes/@font-face are global constructs that
can't be nested, so extractUnscopable() lifts them out unchanged first.

**Parameters**

- `$css` `string`
- `$viewName` `string`

**Returns** `string`
