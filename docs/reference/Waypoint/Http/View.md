---
title: "View"
description: "A server-rendered template result: `return new View('Admin/Users', $model)` renders views/Admin/Users.php inside RendererOptions::$layout (unless $partial)."
order: 100
generated: true
---

`Waypoint\Http\View` · class · namespace [`Waypoint\Http`](index.md)

```php
class View
```

A server-rendered template result: `return new View('Admin/Users', $model)`
renders views/Admin/Users.php inside RendererOptions::$layout (unless
$partial). Inside a template `$this` is this View and `$model` the model;
`$this->env`/`$this->csrf`/`$this->request` are set by Router before render().
`{{ $value }}` and `<?= $value ?>` are HTML-escaped; Waypoint\Html (what section(),
assetTags() and the like return) and `<?php echo ?>` print as they are. The
template syntax (`@if`, `@foreach`, `@section`, `@model`, ...) is Compiler's.

## Properties

### $env

```php
protected \Waypoint\Environment $env;
```

Available inside templates as `$this->env`.

### $csrf

```php
protected \Waypoint\Csrf $csrf;
```

Available inside templates as `$this->csrf` (already carrying this request's token).

### $request

```php
public private(set) ?\Waypoint\Http\Request $request = null;
```

The request being answered, inside templates `$this->request`; set by Router::renderView(), null for a View rendered outside one.

### $view

```php
protected string $view;
```

### $model

```php
protected mixed $model;
```

### $partial

```php
protected bool $partial;
```

### $layout

```php
protected ?string $layout = null;
```

Explicit layout file basename (e.g. "_Layout.php"), or null for RendererOptions::$layout.

### $directory

```php
protected ?string $directory = null;
```

A directory of its own for view and layout, e.g. a plugin's views; null for RendererOptions::$directory.

### $sections

```php
protected array $sections = [];
```

**Type** `array<string, string>`

### $currentSection

```php
protected ?string $currentSection = null;
```

### $sectionBufferLevel

```php
protected int $sectionBufferLevel = 0;
```

### $assets

```php
protected array $assets = ['css' => null, 'js' => null];
```

**Type** `array{css: ?string, js: ?string}`

This view's compiled asset filenames, set by Router::renderView().

### $assetsPath

```php
protected string $assetsPath = '/assets';
```

URL prefix assets are served under; Router overwrites it with FileSystemOptions::$assetsPath.

### $waypointJsPath

```php
protected ?string $waypointJsPath = null;
```

URL of the compiled waypoint.js asset, set by Router::renderView(); null only for a View rendered outside a Router.

### $waypointJsAttributes

```php
protected array $waypointJsAttributes = [];
```

**Type** `array<string, string>`

data-* attributes waypoint.js reads off its own `<script>` tag (see Router::renderView()).

### $layoutName

```php
protected ?string $layoutName = null;
```

The layout's basename without .php, set by render() (null for a partial render).

### $layoutAssets

```php
protected array $layoutAssets = ['css' => null, 'js' => null];
```

**Type** `array{css: ?string, js: ?string}`

The layout's own compiled assets (every views/*.php file gets the same sibling lookup).

### $helpers

```php
protected array $helpers = [];
```

**Type** `array<string, callable>`

Plugin view helpers (Plugin\ViewHelper), reachable as $this->name(...) via __call().

### $pluginAssetUrls

```php
protected array $pluginAssetUrls = [];
```

**Type** `list<string>`

URLs of plugin CSS/JS (Plugin\ClientAsset), set by Router::renderView().

## Methods

### __construct()

```php
public function __construct(
    string $view,
    mixed $model = null,
    bool $partial = false,
    ?string $layout = null,
    ?string $directory = null,
)
```

**Parameters**

- `$view` `string`: Path under RendererOptions::$directory without .php, '/'-separated ("Admin/Users"). A leading '/', a backslash or a '..' segment is rejected: the name is often built from request input.
- `$model` `mixed`
- `$partial` `bool`
- `$layout` `string|null`: Overrides RendererOptions::$layout for this render.
- `$directory` `string|null`: Overrides RendererOptions::$directory for view and layout, e.g. a plugin's own views. Such a view gets no compiled CSS/JS of its own.

### getDirectory()

```php
public function getDirectory(): ?string
```

**Returns** `?string`

### getViewName()

```php
public function getViewName(): string
```

**Returns** `string`

### setRequest()

```php
public function setRequest(\Waypoint\Http\Request $request): void
```

**Parameters**

- `$request` [`Request`](Request.md)

### setAssets()

```php
public function setAssets(array $assets): void
```

**Parameters**

- `$assets` `array{css: ?string, js: ?string}`

### setAssetsPath()

```php
public function setAssetsPath(string $assetsPath): void
```

**Parameters**

- `$assetsPath` `string`

### setWaypointJsPath()

```php
public function setWaypointJsPath(?string $waypointJsPath, array $attributes = []): void
```

**Parameters**

- `$waypointJsPath` `?string`
- `$attributes` `array<string, string>`: Extra attributes for the tag, e.g. ['data-csrf-cookie' => 'my_token'].

### setHelpers()

```php
public function setHelpers(array $helpers): void
```

**Parameters**

- `$helpers` `array<string, callable>`

### setPluginAssetUrls()

```php
public function setPluginAssetUrls(array $urls): void
```

**Parameters**

- `$urls` `list<string>`

### __call()

```php
public function __call(string $name, array $arguments): mixed
```

`$this->t('key')` and any other helper a plugin registered.

**Parameters**

- `$name` `string`
- `$arguments` `list<mixed>`

**Returns** `mixed`

### pluginAssetTags()

```php
public function pluginAssetTags(): \Waypoint\Html
```

`<link>`/`<script>` tags for every plugin-shipped asset; place after waypointJsTag().

**Returns** [`Html`](../Html.md)

### assetTags()

```php
public function assetTags(): \Waypoint\Html
```

`<link>`/`<script>` tags for this view's own CSS/JS, for the layout's `<head>`.

**Returns** [`Html`](../Html.md)

### layoutAssetTags()

```php
public function layoutAssetTags(): \Waypoint\Html
```

Same for the layout's own CSS/JS; empty in a partial render.

**Returns** [`Html`](../Html.md)

### waypointJsTag()

```php
public function waypointJsTag(): \Waypoint\Html
```

`<script>` tag for the bundled waypoint.js client (content-hashed, served like any view asset).
Carries the CSRF cookie/header names as data attributes when CsrfOptions differs from the
client's defaults, so the layout never repeats what the options already say.

**Returns** [`Html`](../Html.md)

### scopeAttribute()

```php
public function scopeAttribute(): \Waypoint\Html
```

`data-view="..."` for the element wrapping `$content` (what the view's scoped CSS matches on); '' when it has no assets.

**Returns** [`Html`](../Html.md)

### layoutScopeAttribute()

```php
public function layoutScopeAttribute(): \Waypoint\Html
```

Same for the layout's own scoped CSS, e.g. on `<body>`.

**Returns** [`Html`](../Html.md)

### startSection()

```php
public function startSection(string $name): void
```

**Parameters**

- `$name` `string`

### endSection()

```php
public function endSection(): void
```

### section()

```php
public function section(string $name, bool $required = false): \Waypoint\Html
```

The section's markup, empty when it was never started (or an exception for a $required one).

**Parameters**

- `$name` `string`
- `$required` `bool`

**Returns** [`Html`](../Html.md)

### hasSection()

```php
public function hasSection(string $name): bool
```

Whether the view started the section, e.g. to leave out its wrapper.

**Parameters**

- `$name` `string`

**Returns** `bool`

### render()

```php
public function render(): string
```

Renders the view, then the layout around it. Should a template throw, every output buffer it
opened (its own and any unfinished section) is discarded: the half-rendered page must neither
end up in the error response nor stay open in a persistent worker for the next request.

**Returns** `string`

### origin()

```php
public static function origin(\Throwable $e): ?array
```

The view file and line an error of a render came from: where it was thrown, or where the view called what
threw it. The compiler keeps the lines, so they are the view's. For the developer: logs, the editor.

**Parameters**

- `$e` [`Throwable`](https://www.php.net/manual/en/class.throwable.php)

**Returns** `array{string, int}|null`

### escape()

```php
public static function escape(mixed ...$values): string
```

What `<?= ?>` prints: scalars and Stringable HTML-escaped, Html as it is, null as nothing. Anything else (an
array, an object without __toString()) is a TypeError rather than "Array".

**Parameters**

- `...$values` `mixed`

**Returns** `string`
