---
title: "Html"
description: "Markup a view prints as it is: `<?= $this->section('head') ?>` where section() returns Html."
order: 100
generated: true
---

`Waypoint\Html` · readonly class · namespace [`Waypoint`](index.md)

```php
readonly class Html implements \Stringable
```

Implements [`Stringable`](https://www.php.net/manual/en/class.stringable.php)

Markup a view prints as it is: `<?= $this->section('head') ?>` where section() returns Html. Views escape every
other value `<?= ?>` prints. Only for HTML that was built safely, such as another template's output; anything
from a user stays a string and is escaped.

## Methods

### __construct()

```php
public function __construct(private readonly string $html)
```

**Parameters**

- `$html` `string`: Markup that is safe as it is: escape everything from a user before it goes in

### __toString()

```php
public function __toString(): string
```

**Returns** `string`
