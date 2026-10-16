---
title: Views
description: Render HTML from PHP templates inside a layout, with sections, scoped CSS and JavaScript per view, forms, waypoint.js and static files.
order: 90
---

A route that returns a [`View`](../reference/Waypoint/Http/View.md) answers with HTML: Waypoint renders the named PHP template and puts the result into the layout.

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

A template is a PHP file. `$model` is the second argument of the `View`, and `$this` is the `View` itself:

```php
<?php /** views/Products/Index.php */ ?>
<?php $this->startSection('head') ?>
    <meta name="description" content="All our products">
<?php $this->endSection() ?>

<section <?= $this->scopeAttribute() ?>>
    <h1><?= htmlspecialchars($model['title']) ?></h1>
    <ul>
        <?php foreach ($model['products'] as $product): ?>
            <li>
                <span class="name"><?= htmlspecialchars($product['name']) ?></span>
                <span class="price"><?= number_format($product['price'], 2) ?> €</span>
            </li>
        <?php endforeach ?>
    </ul>
</section>
```

Templates don't escape on their own: pass every value from the model or the request through `htmlspecialchars()`. Views can live in subdirectories: `views/Products/Index.php` is `new View('Products/Index')`. A name that starts with `/`, contains a backslash or a `..` segment is refused, so a view name built from request input can't leave the views directory.

## The layout

The layout receives the rendered view as `$content`, along with `$model`:

```php
<?php /** views/_Layout.php */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($model['title'] ?? 'Shop') ?></title>
    <?= $this->layoutAssetTags() ?>
    <?= $this->assetTags() ?>
    <?= $this->section('head') ?>
    <?= $this->waypointJsTag() ?>
    <?= $this->pluginAssetTags() ?>
</head>
<body <?= $this->layoutScopeAttribute() ?>>
    <nav><a href="/products">Products</a></nav>
    <main><?= $content ?></main>
</body>
</html>
```

| In a template | Gives |
|---|---|
| `$this->startSection('head')` … `$this->endSection()` | captures output under a name, to be placed by the layout |
| `$this->section('head')` | a captured section, `''` if the view defined none; `section('head', required: true)` throws instead |
| `$this->assetTags()`, `$this->layoutAssetTags()` | `<link>` and `<script>` tags for the view's and the layout's own CSS and JS |
| `$this->scopeAttribute()`, `$this->layoutScopeAttribute()` | the `data-view="…"` attribute their CSS is scoped to |
| `$this->waypointJsTag()` | the `<script>` tag for `waypoint.js` |
| `$this->pluginAssetTags()` | tags for the CSS and JS of plugins, after `waypointJsTag()` |
| `$this->csrf->field()`, `$this->csrf->token()` | the CSRF hidden field and the token itself |
| `$this->env` | the [`Environment`](../reference/Waypoint/Environment.md) |
| `$this->t(…)` and other names | view helpers that plugins provide (see [Plugins](plugins.md)) |

`new View('Products/Index', $model, layout: '_Print.php')` uses another layout for one render, and `partial: true` renders the view alone, without a layout, for an HTML fragment.

Fetch everything a page shows in the controller, before returning the `View`. Under Tenma, a template that waits for I/O would hold the request in the middle of rendering.

## CSS and JavaScript per view

A `.css` or `.js` file next to a view or layout, with the same name, belongs to it: `Products/Index.css` to `Products/Index.php`, `_Layout.css` to the layout. Nothing has to be registered.

The CSS is scoped to its view. Waypoint wraps it in one CSS nesting rule, `[data-view="Products/Index"] { … }`, so plain rules apply only inside the element that carries `<?= $this->scopeAttribute() ?>`:

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

```php
<?php /** views/Contact.php */ ?>
<form method="post" action="/contact">
    <?= $this->csrf->field() ?>
    <label>
        Email
        <input type="email" name="email" value="<?= htmlspecialchars($model['form']->email) ?>">
        <?php if (isset($model['errors']['email'])): ?><small><?= htmlspecialchars($model['errors']['email']) ?></small><?php endif ?>
    </label>
    <label>
        Message
        <textarea name="message"><?= htmlspecialchars($model['form']->message) ?></textarea>
        <?php if (isset($model['errors']['message'])): ?><small><?= htmlspecialchars($model['errors']['message']) ?></small><?php endif ?>
    </label>
    <button>Send</button>
</form>
```

`$this->csrf->field()` writes the hidden CSRF field; it is needed as soon as CSRF protection is on (see [Authentication](authentication.md#csrf-protection)). `ContactMessage` validates itself (see [Binding and validation](binding.md#validating-by-hand)). Redirecting with `303` after a successful `POST` keeps a reload from sending the form twice.

Pages are plain browser navigation: links and forms load whole pages. For smooth transitions between pages, CSS is enough: `@view-transition { navigation: auto; }`.

## waypoint.js

`waypoint.js` is a small script that ships with Waypoint and needs no build step. `<?= $this->waypointJsTag() ?>` in the layout loads it, content-hashed like the view assets. It does three things:

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

See also: [`View`](../reference/Waypoint/Http/View.md), [`RendererOptions`](../reference/Waypoint/Options/RendererOptions.md), [`FileSystemOptions`](../reference/Waypoint/Options/FileSystemOptions.md), [`Csrf`](../reference/Waypoint/Csrf.md).
