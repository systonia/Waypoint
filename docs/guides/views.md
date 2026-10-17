---
title: Views
description: Render HTML from templates inside a layout, with directives, sections, a typed model, scoped CSS and JavaScript per view, the check, forms, waypoint.js and static files.
order: 90
---

A route that returns a [`View`](../reference/Waypoint/Http/View.md) answers with HTML: Waypoint renders the named template and puts the result into the layout.

```php
<?php

namespace App\Controllers;

use Waypoint\Attributes\{Controller, Get};
use Waypoint\Http\View;

#[Controller('/products')]
final class ProductPagesController
{
    #[Get('')]
    public function index(): View
    {
        return new View('Products/Index', [
            'title' => 'Products',
            'products' => [
                ['name' => 'Desk lamp', 'price' => 49.0],
                ['name' => 'Bookshelf', 'price' => 129.0],
            ],
        ]);
    }
}
```

Tell Waypoint where the templates live and which file is the layout:

```php
use Waypoint\Options\RendererOptions;

$app->configure(function (RendererOptions $views) {
    $views->directory = dirname(__DIR__) . '/views';
    $views->layout = '_Layout.php';
});
```

`layout` is a file name including `.php`. Its default, `_Layout`, has no extension and matches no file, so without this setting every view renders without a layout.

```text
views/
├── _Layout.php
├── _Layout.css
└── Products/
    ├── Index.php
    └── Index.css
```

## Templates

A template is a PHP file with a few directives on top. `$model` is the second argument of the `View`, and `$this` is the `View` itself:

```html
{{/* views/Products/Index.php */}}
@model (array{title: string, products: list<array{name: string, price: float}>})

@section('head')
    <meta name="description" content="All our products">
@endsection

<section {{ $this->scopeAttribute() }}>
    <h1>{{ $model['title'] }}</h1>
    <ul>
        @foreach ($model['products'] as $product)
            <li @class.cheap="$product['price'] < 50">
                <span class="name">{{ $product['name'] }}</span>
                <span class="price">{{ number_format($product['price'], 2) }} €</span>
            </li>
        @endforeach
    </ul>
</section>
```

`{{ }}` escapes what it prints with `htmlspecialchars()`, quotes included, so a product name with a `<` in it stays text. `<?= ?>` does the same. Markup goes out as it is in two ways:

- a [`Waypoint\Html`](../reference/Waypoint/Html.md) value: `{{ new Html($trustedMarkup) }}`. `@yield`, `section()`, `assetTags()`, `scopeAttribute()`, `$this->csrf->field()`, the layout's `$content` and the other helpers in the table below return one already, and a plugin's view helper that builds markup returns one too;
- `<?php echo $markup ?>`, for markup the template trusts as it is.

Only `Html` should carry markup: everything from a user, the request or the database is escaped. Printing an array or an object without `__toString()` throws a `TypeError` instead of writing `Array`. `__FILE__` and `__DIR__` name the template itself.

Views can live in subdirectories: `views/Products/Index.php` is `new View('Products/Index')`. A name that starts with `/`, contains a backslash or a `..` segment is refused, so a view name built from request input can't leave the views directory.

## The syntax

| Directive | What it does |
|---|---|
| `{{ expr }}` | prints the value, escaped; an `Html` value as it is |
| `{{/* ... */}}` | a comment, which leaves nothing in the HTML |
| `@if (cond)`, `@elseif (cond)`, `@else`, `@endif` | `<?php if (cond): ?>` and the rest |
| `@foreach (expr)`, `@endforeach` | `<?php foreach (expr): ?>`, `<?php endforeach ?>` |
| `@code` ... `@endcode` | `<?php ... ?>`, for a few lines of PHP |
| `@use (App\Status)`, `@use (App\Status as State)` | imports a class for the whole view |
| `@class.late="$cond"` | in a start tag: the class `late` in the tag's `class` attribute while `$cond` is truthy |
| `@@` | a literal `@` |
| `@model (Type)` | the type of `$model` |
| `@section('name')` ... `@endsection` | the output in between as a section, for the layout |
| `@yield('name')` | in the layout: a section's content, as it is |

- A directive starts a line or follows whitespace, `>` or `}}`. So `shop@example.com`, `@media` in a `<style>` block and attributes like `@click` stay text. Use `@@` where an `@` that should stay text follows a space.
- A condition may span lines and contain parentheses and strings: `@if (in_array('a)', $tags, true))` works.
- Directives work inside a tag, for attributes that come and go: `<button @if ($saving) disabled @endif>`.
- Every directive becomes PHP on the same line, so an error, the editor and the check point at the view's own lines. A `{{ }}` at the end of a line keeps the line break after it, which PHP would drop after `?>`.
- A directive on its own line leaves no whitespace behind: its indentation, the line break after it, blank lines between directives and the whitespace at the start and end of a block's content print nothing. This holds for `@if`, `@elseif`, `@else`, `@endif`, `@foreach`, `@endforeach`, `@section`, `@endsection`, `@code`, `@endcode`, `@use` and `@model`, so `@section('title')`, an indented `{{ $title }}` and `@endsection` on three lines make the title exactly `$title`. Whitespace without a line break stays as written: `Hello @if ($name) {{ $name }} @endif` keeps its spaces. Around `{{ }}` and plain text, whitespace goes only where it touches a directive.
- Inside `<?php ?>` nothing is a directive, and plain PHP works as in any PHP file: `<?php foreach ($rows as $row): ?>` and `@foreach ($rows as $row)` are the same.
- The body of `@code` is PHP only. It ends at the first `@endcode` outside its strings and comments; the last statement needs no `;`.
- A view has no namespace: names resolve from the root (`App\Status::Paid`), through a `use` statement in PHP, or through `@use`.
- `classes('row', ['late' => $order->late])` builds a `class` value from strings and conditions, each class once, in every view without an import; outside views it is `Waypoint\classes()`.

### @model

`@model (App\Models\ProductPage)` names the type of `$model`. The check reads it, so PHPStan knows what `$model->product` is, and so does the editor: `$model->` completes the members of the class and F12 leads to them. In development a model of another type is a `TypeError` with the view's line (`@model (App\Models\ProductPage): the model is array`). The type may be anything a PHP parameter takes (`?Order`, `Order|Invoice`, `array`) or a PHPDoc type for PHPStan alone (`list<App\Models\Product>`, `array{title: string}`), which is not checked at runtime. A view has one `@model`; its names resolve through `@use` like the rest of the view.

### Mistakes

A block that does not close, an `@endif` that closes an `@foreach`, a `{{` without `}}`, an empty `{{ }}`, a second `@model` or a `/*` without `*/` keeps the view from rendering, with its line:

```
View '/srv/shop/views/Products/Index.php', line 4: @if has no @endif
```

So does PHP that every render would run into: a `namespace` or `declare`, a named `function`, `class`, `interface`, `trait` or `enum` (declared again on the next render), a `break` or `continue` outside a loop, a `yield` outside a function and an assignment to `$this`. A misspelt directive such as `@forech` gets a warning (`Did you mean @foreach?`) and stays text. An error while a view renders keeps the view's file and line: [`View::origin()`](../reference/Waypoint/Http/View.md#origin) returns them, and the error log names them.

## The layout

The layout receives the rendered view as `$content` (an `Html`, so `{{ $content }}` prints it as it is), along with `$model`:

```html
{{/* views/_Layout.php */}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title') · Shop</title>
    {{ $this->layoutAssetTags() }}
    {{ $this->assetTags() }}
    @yield('head')
    {{ $this->waypointJsTag() }}
    {{ $this->pluginAssetTags() }}
</head>
<body {{ $this->layoutScopeAttribute() }}>
    <nav><a href="/products">Products</a></nav>
    <main>{{ $content }}</main>
</body>
</html>
```

A view fills the sections: `@section('title'){{ $model['title'] }}@endsection`.

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

`new View('Products/Index', $model, layout: '_Print.php')` uses another layout for one render, and `partial: true` renders the view alone, without a layout, for an HTML fragment.

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
views/Shop/Product.php:12: error: Access to an undefined property App\Models\Product::$pirce.
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

With `tenma lsp`, the language server of [Tenma](tenma.md), Waypoint knows the views too: `$this->` completes the `View`'s members, `$model->` those of the class `@model` names (also along chains such as `$model->customer->` and in `@foreach ($model->items as $item)` for `$item->`, with the element class from `#[ListOf]` on the property or `#[Returns(list: true)]` on a method), hover shows their types, F12 leads from them and from a class in `@model` or `@use` to its declaration, the mistakes above show while you type, and the directives get their colours.

## CSS and JavaScript per view

A `.css` or `.js` file next to a view or layout, with the same name, belongs to it: `Products/Index.css` to `Products/Index.php`, `_Layout.css` to the layout. Nothing has to be registered.

The CSS is scoped to its view. Waypoint wraps it in one CSS nesting rule, `[data-view="Products/Index"] { … }`, so plain rules apply only inside the element that carries `{{ $this->scopeAttribute() }}`:

```css
/* views/Products/Index.css */
ul { list-style: none; padding: 0; }
.price { color: #555; }
```

`@keyframes`, `@font-face`, `:root` and `html` rules at the top level stay global, and top-level `@charset`, `@import` and `@layer` statements move to the front of the file. JavaScript is not changed.

The compiled files are named by a hash of their content and served under `/assets/` (`FileSystemOptions::$assetsPath`) with `Cache-Control: public, max-age=31536000, immutable`: a browser keeps them until they change, and a change gives them a new name. With a [cache directory](caching.md) they are written to `<cacheDirectory>/assets/`; without one they are kept in memory. With `cacheValidate` on, an edited `.css` or `.js` is picked up on the next request.

## Forms

A form posts to a route, which either redirects after success or renders the page again with the messages:

```php
<?php

namespace App\Controllers;

use App\Dto\ContactMessage;
use Waypoint\Attributes\{Body, Controller, Get, Post};
use Waypoint\Http\{Redirect, View};

#[Controller('/contact')]
final class ContactController
{
    #[Get('')]
    public function form(): View
    {
        return new View('Contact', ['title' => 'Contact', 'form' => new ContactMessage(), 'errors' => []]);
    }

    #[Post('')]
    public function send(
        #[Body(
            validate: false
        )]
        ContactMessage $form
    ): View|Redirect {
        if (!$form->isValid) {
            return new View('Contact', ['title' => 'Contact', 'form' => $form, 'errors' => $form->errors]);
        }
        // deliver the message …
        return new Redirect('/contact/thanks', 303);
    }
}
```

```php
<?php

namespace App\Dto;

use Waypoint\Attributes\{Email, Length, NotBlank};
use Waypoint\FromArray;

final class ContactMessage
{
    use FromArray;

    #[NotBlank]
    #[Email]
    public string $email = '';

    #[Length(
        min: 10,
        max: 2000
    )]
    public string $message = '';
}
```

```html
{{/* views/Contact.php */}}
<form method="post" action="/contact">
    {{ $this->csrf->field() }}
    <label>
        Email
        <input type="email" name="email" value="{{ $model['form']->email }}">
        @if (isset($model['errors']['email']))<small>{{ $model['errors']['email'] }}</small>@endif
    </label>
    <label>
        Message
        <textarea name="message">{{ $model['form']->message }}</textarea>
        @if (isset($model['errors']['message']))<small>{{ $model['errors']['message'] }}</small>@endif
    </label>
    <button>Send</button>
</form>
```

`$this->csrf->field()` writes the hidden CSRF field; it is needed as soon as CSRF protection is on (see [Authentication](authentication.md#csrf-protection)). `ContactMessage` validates itself (see [Binding and validation](binding.md#validating-by-hand)). Redirecting with `303` after a successful `POST` keeps a reload from sending the form twice.

Pages are plain browser navigation: links and forms load whole pages. For smooth transitions between pages, CSS is enough: `@view-transition { navigation: auto; }`.

## waypoint.js

`waypoint.js` is a small script that ships with Waypoint and needs no build step. `{{ $this->waypointJsTag() }}` in the layout loads it, content-hashed like the view assets. It does three things:

- **CSRF for `fetch()`**: every same-origin `POST`, `PUT`, `PATCH` and `DELETE` made with `fetch()` gets the `X-CSRF-Token` header, read from the cookie. When `CsrfOptions` uses other names, the script tag carries them.
- **`wp-onclick="name"`** on any element calls the handler `name` with the element's `data-*` values.
- **`wp-onsubmit="name"`** on a form calls the handler with the form's fields as an object (`items[]` and `address[city]` nested as PHP would); the normal submit is prevented unless the form also has `wp-default="true"`.

```html
<button wp-onclick="addToCart" data-sku="LAMP-1">Add to cart</button>

<script>
    Waypoint.handlers.register('addToCart', async (data) => {
        await fetch('/cart', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({sku: data.sku}),
        });
    });
</script>
```

A handler is a function registered with `Waypoint.handlers.register()` or a global function of that name. Nothing is evaluated from attributes, so the script works under a strict Content Security Policy. `Waypoint.csrf.token()` returns the current token for requests made without `fetch()`.

## Static files

[`FileSystemOptions::$publicDirectory`](../reference/Waypoint/Options/FileSystemOptions.md) serves a directory of plain files (images, fonts, `robots.txt`, a prebuilt bundle) at the root of the site:

```php
$app->configure(fn (FileSystemOptions $fs) => $fs->publicDirectory = __DIR__);
```

- Only `GET` and `HEAD` are served from it, and a file wins over a route at the same path. Other methods go to routing.
- Never served: paths with a `.` or `..` segment, dotfiles and dot-directories (`.env`, `.git/`), PHP source (`.php`, `.phtml`, `.phar` and the like), and anything whose real path (after symlinks) lies outside the directory. The front controller can therefore live in the public directory.
- `.css`, `.js`, `.mjs`, `.json`, `.svg` and `.html` get a fixed `Content-Type`; other files are detected by content.
- Each file carries `Last-Modified`; a request whose `If-Modified-Since` is not older gets `304 Not Modified` without the body.

Under Tenma, `serve()` hands the directory to Tenma, which serves it without PHP and adds `ETag` and range requests. Under PHP-FPM the web server usually serves these files itself before PHP is involved (see [Deployment](deployment.md)).

See also: [`View`](../reference/Waypoint/Http/View.md), [`Check`](../reference/Waypoint/View/Check.md), [`RendererOptions`](../reference/Waypoint/Options/RendererOptions.md), [`FileSystemOptions`](../reference/Waypoint/Options/FileSystemOptions.md), [`Csrf`](../reference/Waypoint/Csrf.md).
