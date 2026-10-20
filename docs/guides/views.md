---
title: Views
description: Render HTML from templates inside a layout, with directives, sections, a typed model, scoped CSS and JavaScript per view, the check, forms, waypoint.js and static files.
order: 90
---

A route that returns a [`View`](../reference/Waypoint/Http/View.md) answers with HTML: Waypoint renders the named template and puts the result into the layout.

The bakery's catalog, from `src/Controllers/ShopController.php`:

```php
<?php

namespace Bakery\Controllers;

use Bakery\Repositories\Products;
use Waypoint\Attributes\{Controller, Get, Inject};
use Waypoint\Http\View;

#[Controller]
final class ShopController
{
    #[Inject]
    private Products $products;

    #[Get('/')]
    public function catalog(): View
    {
        return new View('Shop/Catalog', $this->products->all());
    }
}
```

Tell Waypoint where the templates live and which file is the layout:

```php
use Waypoint\Options\RendererOptions;

$app->configure(function (RendererOptions $views) {
    $views->directory = __DIR__ . '/views';
    $views->layout = '_Layout.php';
});
```

`layout` is a file name including `.php`. Its default, `_Layout`, has no extension and matches no file, so without this setting every view renders without a layout.

```text
views/
├── _Layout.php
├── _Layout.css
├── _Backoffice.php
└── Shop/
    ├── Catalog.php
    ├── Catalog.css
    └── Product.php
```

## Templates

A template is a PHP file with a few directives on top. `$model` is the second argument of the `View`, and `$this` is the `View` itself:

```html
{{/* views/Shop/Catalog.php */}}
@use (Bakery\Services\Format)
@model (list<Bakery\Models\Product>)

@code
    $locale = $this->locale()
@endcode

@section('title')
    {{ $this->t('Shop') }}
@endsection

<section {{ $this->scopeAttribute() }}>
    <h1>
        {{ $this->t('Baked this morning') }}
    </h1>
    <ul>
        @foreach ($model as $product)
            <li @class.sold-out="$product->stock === 0">
                <a href="/products/{{ $product->slug }}">{{ $product->name($locale) }}</a>
                <span class="price">{{ Format::price($product->priceCents, $locale) }}</span>
                @if ($product->stock > 0)
                    <span>{{ $this->t('{count} left', ['count' => $product->stock]) }}</span>
                @endif
            </li>
        @endforeach
    </ul>
</section>
```

`$this->locale()` and `$this->t()` are view helpers of the Waypoint I18n plugin.

`{{ }}` escapes what it prints with `htmlspecialchars()`, quotes included, so a customer's name with a `<` in it stays text. `<?= ?>` does the same. Markup goes out as it is in two ways:

- a [`Waypoint\Html`](../reference/Waypoint/Html.md) value: `{{ new Html($trustedMarkup) }}`. `@yield`, `section()`, `assetTags()`, `scopeAttribute()`, `$this->csrf->field()`, the layout's `$content` and the other helpers in the table below return one already, and a plugin's view helper that builds markup returns one too;
- `<?php echo $markup ?>`, for markup the template trusts as it is.

Only `Html` should carry markup: everything from a user, the request or the database is escaped. Printing an array or an object without `__toString()` throws a `TypeError` instead of writing `Array`. `__FILE__` and `__DIR__` name the template itself.

Views can live in subdirectories: `views/Shop/Catalog.php` is `new View('Shop/Catalog')`. A name that starts with `/`, contains a backslash or a `..` segment is refused, so a view name built from request input can't leave the views directory.

## The syntax

| Directive | What it does |
|---|---|
| `{{ expr }}` | prints the value, escaped; an `Html` value as it is |
| `{{/* ... */}}` | a comment, which leaves nothing in the HTML |
| `@if (cond)`, `@elseif (cond)`, `@else`, `@endif` | `<?php if (cond): ?>` and the rest |
| `@foreach (expr)`, `@endforeach` | `<?php foreach (expr): ?>`, `<?php endforeach ?>` |
| `@code` ... `@endcode` | `<?php ... ?>`, for a few lines of PHP |
| `@use (Bakery\Services\Format)`, `@use (Bakery\Models\OrderStatus as Status)` | imports a class for the whole view |
| `@class.late="$cond"` | in a start tag: the class `late` in the tag's `class` attribute while `$cond` is truthy |
| `@@` | a literal `@` |
| `@model (Type)` | the type of `$model` |
| `@section('name')` ... `@endsection` | the output in between as a section, for the layout |
| `@yield('name')` | in the layout: a section's content, as it is |

- A directive starts a line or follows whitespace, `>` or `}}`. So `hello@sonnenkorn.test`, `@media` in a `<style>` block and attributes like `@click` stay text. Use `@@` where an `@` that should stay text follows a space.
- A condition may span lines and contain parentheses and strings: `@if (in_array('a)', $tags, true))` works.
- Directives work inside a tag, for attributes that come and go: `<button @if ($saving) disabled @endif>`.
- Every directive becomes PHP on the same line, so an error, the editor and the check point at the view's own lines. A `{{ }}` at the end of a line keeps the line break after it, which PHP would drop after `?>`.
- A directive on its own line leaves no whitespace behind: its indentation, the line break after it, blank lines between directives and the whitespace at the start and end of a block's content print nothing. This holds for `@if`, `@elseif`, `@else`, `@endif`, `@foreach`, `@endforeach`, `@section`, `@endsection`, `@code`, `@endcode`, `@use` and `@model`, so `@section('title')`, an indented `{{ $title }}` and `@endsection` on three lines make the title exactly `$title`. Whitespace without a line break stays as written: `Hello @if ($name) {{ $name }} @endif` keeps its spaces. Around `{{ }}` and plain text, whitespace goes only where it touches a directive.
- Inside `<?php ?>` nothing is a directive, and plain PHP works as in any PHP file: `<?php foreach ($rows as $row): ?>` and `@foreach ($rows as $row)` are the same.
- The body of `@code` is PHP only. It ends at the first `@endcode` outside its strings and comments; the last statement needs no `;`.
- A view has no namespace: names resolve from the root (`Bakery\Models\OrderStatus::Ready`), through a `use` statement in PHP, or through `@use`.
- `classes('order', ['late' => $order->pickupDate < $today])` builds a `class` value from strings and conditions, each class once, in every view without an import; outside views it is `Waypoint\classes()`.

### @model

`@model (Bakery\Models\ProductPage)` names the type of `$model`. The check reads it, so PHPStan knows what `$model->product` is, and so does the editor: `$model->` completes the members of the class and F12 leads to them. In development a model of another type is a `TypeError` with the view's line (`@model (Bakery\Models\ProductPage): the model is array`). The type may be anything a PHP parameter takes (`?Order`, `Order|OrderSummary`, `array`) or a PHPDoc type for PHPStan alone (`list<Bakery\Models\Product>`, `array{title: string}`), which is not checked at runtime. A view has one `@model`; its names resolve through `@use` like the rest of the view.

### Mistakes

A block that does not close, an `@endif` that closes an `@foreach`, a `{{` without `}}`, an empty `{{ }}`, a second `@model` or a `/*` without `*/` keeps the view from rendering, with its line:

```
View '/srv/bakery/views/Shop/Catalog.php', line 21: @if has no @endif
```

So does PHP that every render would run into: a `namespace` or `declare`, a named `function`, `class`, `interface`, `trait` or `enum` (declared again on the next render), a `break` or `continue` outside a loop, a `yield` outside a function and an assignment to `$this`. A misspelt directive such as `@forech` gets a warning (`Did you mean @foreach?`) and stays text. An error while a view renders keeps the view's file and line: [`View::origin()`](../reference/Waypoint/Http/View.md#origin) returns them, and the error log names them.

## The layout

The layout receives the rendered view as `$content` (an `Html`, so `{{ $content }}` prints it as it is), along with `$model`:

```html
{{/* views/_Layout.php, shortened */}}
<!doctype html>
<html lang="{{ $this->locale() }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title') · Sonnenkorn</title>
    {{ $this->layoutAssetTags() }}
    {{ $this->assetTags() }}
    @yield('head')
</head>
<body {{ $this->layoutScopeAttribute() }}>
{{ $this->flashes() }}
<header>
    <a href="/">Sonnenkorn</a>
    <app-cart-badge></app-cart-badge>
</header>
<main>{{ $content }}</main>
{{ $this->waypointJsTag() }}
{{ $this->pluginAssetTags() }}
</body>
</html>
```

`$this->flashes()` comes from the Waypoint Flash plugin, `<app-cart-badge>` is a live component of Waypoint Live.

A view fills the sections, as `views/Shop/Product.php` does for the title:

```html
@section('title')
    {{ $product->name($locale) }}
@endsection
```

| In a template | Gives |
|---|---|
| `@section('head')` … `@endsection` | captures output under a name, to be placed by the layout; the same as `$this->startSection('head')` … `$this->endSection()` |
| `@yield('head')`, `$this->section('head')` | a captured section as `Html`, empty if the view defined none; `section('head', required: true)` throws instead, `hasSection('head')` tells whether there is one |
| `$this->assetTags()`, `$this->layoutAssetTags()` | `<link>` and `<script>` tags for the view's and the layout's own CSS and JS |
| `$this->scopeAttribute()`, `$this->layoutScopeAttribute()` | the `data-view="…"` attribute their CSS is scoped to |
| `$this->waypointJsTag()` | the `<script>` tag for `waypoint.js` |
| `$this->pluginAssetTags()` | tags for the CSS and JS of plugins, after `waypointJsTag()` |
| `$this->csrf->field()`, `$this->csrf->token()` | the CSRF hidden field and the token itself |
| `$this->env` | the [`Environment`](../reference/Waypoint/Environment.md) |
| `$this->request` | the [`Request`](../reference/Waypoint/Http/Request.md) being answered, read-only: `$this->request->path`, `$this->request->jwt` |
| `$this->t(…)` and other names | view helpers that plugins provide (see [Plugins](plugins.md)) |

### Another layout for some routes

The bakery's back office has a layout of its own. `#[Layout]` on the controller gives it to every route there, so no `View` has to name it:

```php
#[Controller('/backoffice')]
#[Authenticated]
#[Layout('_Backoffice.php')]
final class BackofficeController
{
    #[Get('')]
    public function board(): View
    {
        return new View('Backoffice/Board');
    }
}
```

The layout of a render comes from the first of these that is set:

1. the View itself: `new View('Backoffice/Board', layout: '_Print.php')`, for a layout only known at runtime;
2. `#[Layout]` on the route method;
3. `#[Layout]` on the controller class, or on a class it extends;
4. `RendererOptions::$layout`.

`#[Layout(null)]` renders the route's views without a layout, and so does `partial: true` on a single `View`, for an HTML fragment.

Fetch everything a page shows in the controller, before returning the `View`. Under Tenma, a template that waits for I/O would hold the request in the middle of rendering.

## When views compile

Every view and layout is compiled to plain PHP before it runs, and again when its file changes. With a cache directory (see [Caching](caching.md)) the compiled views are files under `views/` in it, so OPcache keeps them; without one each is compiled once per process.

## The check

`vendor/bin/waypoint-check` checks the views without running the app:

```bash
composer require --dev phpstan/phpstan
vendor/bin/waypoint-check
```

1. Every mistake above, and PHP that does not parse, at its line.
2. PHPStan's findings in every view, read as the PHP it becomes, with `$this` as the `View` and `$model` of the type `@model` names: an undefined property, a method with the wrong arguments, at the view's line.

```
$ vendor/bin/waypoint-check
views/Shop/Product.php:32: error: Access to an undefined property Bakery\Models\Product::$priceCent.
waypoint-check: 1 errors, 0 warnings
```

- The views are the `.php` files below `views/`. A project that keeps them elsewhere names the directories in its `composer.json`: `"extra": {"waypoint": {"views": ["views", "layouts"]}}`.
- PHPStan runs with the project's `phpstan.neon` (its level, its extensions), otherwise at level 6. Views are read at level 8 at most: what a plugin's view helper returns is known only to the running app, so it is `mixed`, which levels 9 and 10 refuse to use. For the same reason a call PHPStan does not find on `View` counts as a helper, unless its name is close to one of `View`'s own methods (`scopeAtribute()`).
- A package adds its own templates to the check through [`Waypoint\View\Checks`](../reference/Waypoint/View/Checks.md): Waypoint-Live checks its components' templates this way.
- The exit code is 0 without errors, 1 with errors and 2 when the check cannot run, for example without PHPStan. An optional argument names the project's directory.

In a `tenma.dev.php` hook, [`Check::publish()`](../reference/Waypoint/View/Check.md#publish) sends PHPStan's findings to the editor whenever a view is saved:

```php
new Tenma\Hook(
    name: 'waypoint-check',
    inputs: ['views/**', 'src/**'],
    run: fn () => Waypoint\View\Check::publish(__DIR__),
),
```

## In the editor

With `tenma lsp`, the language server of [Tenma](tenma.md), Waypoint knows the views too: `$this->` completes the `View`'s members, `$model->` those of the class `@model` names (also along chains such as `$model->product->` and in `@foreach ($model->lines as $line)` for `$line->`, with the element class from `#[ListOf]` on the property or `#[Returns(list: true)]` on a method), hover shows their types, F12 leads from them and from a class in `@model` or `@use` to its declaration, the mistakes above show while you type, and the directives get their colours.

## CSS and JavaScript per view

A `.css` or `.js` file next to a view or layout, with the same name, belongs to it: `Shop/Catalog.css` to `Shop/Catalog.php`, `_Layout.css` to the layout. Nothing has to be registered.

The CSS is scoped to its view. Waypoint wraps it in one CSS nesting rule, `[data-view="Shop/Catalog"] { … }`, so plain rules apply only inside the element that carries `{{ $this->scopeAttribute() }}`:

```css
/* views/Shop/Catalog.css */
ul { list-style: none; padding: 0; }
.price { font-variant-numeric: tabular-nums; }
.sold-out { opacity: .5; }
```

`@keyframes`, `@font-face`, `:root` and `html` rules at the top level stay global, and top-level `@charset`, `@import` and `@layer` statements move to the front of the file. JavaScript is not changed.

The compiled files are named by a hash of their content and served under `/assets/` (`FileSystemOptions::$assetsPath`) with `Cache-Control: public, max-age=31536000, immutable`: a browser keeps them until they change, and a change gives them a new name. With a [cache directory](caching.md) they are written to `<cacheDirectory>/assets/`; without one they are kept in memory. With `cacheValidate` on, an edited `.css` or `.js` is picked up on the next request.

## Forms

A form posts to a route, which either redirects after success or renders the page again with the messages. The back office's sign-in, shortened from `src/Controllers/SessionController.php`:

```php
<?php

namespace Bakery\Controllers;

use Bakery\Repositories\Users;
use Bakery\Requests\LoginForm;
use Waypoint\Attributes\{Body, Controller, Get, Inject, Layout, Post};
use Waypoint\Http\{Redirect, Response, View};
use Waypoint\JWT;

#[Controller('/backoffice')]
#[Layout('_Backoffice.php')]
final class SessionController
{
    #[Inject]
    private Users $users;

    #[Get('/login')]
    public function form(): View
    {
        return new View('Backoffice/Login', new LoginForm());
    }

    #[Post('/login')]
    public function login(
        #[Body(
            validate: false
        )]
        LoginForm $form,
        Response $res,
    ): View|Redirect {
        $user = $form->isValid ? $this->users->verify($form->email, $form->password) : null;
        if ($user === null) {
            if ($form->isValid) {
                $form->fail('password', 'Wrong email or password.');
            }
            return new View('Backoffice/Login', $form);
        }
        JWT::signIn($res, $user);
        return new Redirect('/backoffice', 303);
    }
}
```

```php
<?php

namespace Bakery\Requests;

use Waypoint\Attributes\{Email, NotBlank};
use Waypoint\FromArray;

final class LoginForm
{
    use FromArray;

    #[NotBlank]
    #[Email]
    public string $email = '';

    #[NotBlank]
    public string $password = '';
}
```

```html
{{/* views/Backoffice/Login.php, shortened */}}
@model (Bakery\Requests\LoginForm)

@code
    $errors = $this->request?->method === 'POST' ? $model->errors : []
@endcode

<form method="post" action="/backoffice/login">
    {{ $this->csrf->field() }}
    <label>
        Email
        <input type="email" name="email" value="{{ $model->email }}" required>
        @if (isset($errors['email']))
            <small>{{ $errors['email'] }}</small>
        @endif
    </label>
    <label>
        Password
        <input type="password" name="password" required>
        @if (isset($errors['password']))
            <small>{{ $errors['password'] }}</small>
        @endif
    </label>
    <button>Sign in</button>
</form>
```

A View returned for an invalid `#[Body(validate: false)]` DTO answers `422`.

`$this->csrf->field()` writes the hidden CSRF field; it is needed as soon as CSRF protection is on (see [Authentication](authentication.md#csrf-protection)). `LoginForm` validates itself (see [Binding and validation](binding.md#validating-by-hand)). Redirecting with `303` after a successful `POST` keeps a reload from sending the form twice.

Pages are plain browser navigation: links and forms load whole pages. For smooth transitions between pages, CSS is enough: `@view-transition { navigation: auto; }`.

## waypoint.js

`waypoint.js` is a small script that ships with Waypoint and needs no build step. `{{ $this->waypointJsTag() }}` in the layout loads it, content-hashed like the view assets. It does three things:

- **CSRF for `fetch()`**: every same-origin `POST`, `PUT`, `PATCH` and `DELETE` made with `fetch()` gets the `X-CSRF-Token` header, read from the cookie. When `CsrfOptions` uses other names, the script tag carries them.
- **`wp-onclick="name"`** on any element calls the handler `name` with the element's `data-*` values.
- **`wp-onsubmit="name"`** on a form calls the handler with the form's fields as an object (`items[]` and `address[city]` nested as PHP would); the normal submit is prevented unless the form also has `wp-default="true"`.

```html
<button wp-onclick="setAside" data-product="{{ $product->id }}">Set one aside</button>

<script>
    Waypoint.handlers.register('setAside', async (data) => {
        await fetch('/set-aside', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({product: Number(data.product)}),
        });
    });
</script>
```

A handler is a function registered with `Waypoint.handlers.register()` or a global function of that name. Nothing is evaluated from attributes, so the script works under a strict Content Security Policy. `Waypoint.csrf.token()` returns the current token for requests made without `fetch()`.

## Static files

[`FileSystemOptions::$publicDirectory`](../reference/Waypoint/Options/FileSystemOptions.md) serves a directory of plain files (images, fonts, `robots.txt`, a prebuilt bundle) at the root of the site. The bakery keeps its product pictures, its icon and the built Tailwind stylesheet in `public/`:

```php
$app->configure(fn (FileSystemOptions $fs) => $fs->publicDirectory = __DIR__ . '/public');
```

- Only `GET` and `HEAD` are served from it, and a file wins over a route at the same path. Other methods go to routing.
- Never served: paths with a `.` or `..` segment, dotfiles and dot-directories (`.env`, `.git/`), PHP source (`.php`, `.phtml`, `.phar` and the like), and anything whose real path (after symlinks) lies outside the directory. The front controller can therefore live in the public directory.
- `.css`, `.js`, `.mjs`, `.json`, `.svg` and `.html` get a fixed `Content-Type`; other files are detected by content.
- Each file carries `Last-Modified`; a request whose `If-Modified-Since` is not older gets `304 Not Modified` without the body.

Under Tenma, `serve()` hands the directory to Tenma, which serves it without PHP and adds `ETag` and range requests. Under PHP-FPM the web server usually serves these files itself before PHP is involved (see [Deployment](deployment.md)).

See also: [`View`](../reference/Waypoint/Http/View.md), [`Check`](../reference/Waypoint/View/Check.md), [`RendererOptions`](../reference/Waypoint/Options/RendererOptions.md), [`FileSystemOptions`](../reference/Waypoint/Options/FileSystemOptions.md), [`Csrf`](../reference/Waypoint/Csrf.md).
