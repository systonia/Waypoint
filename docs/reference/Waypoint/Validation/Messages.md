---
title: "Messages"
description: "Translates validation messages."
order: 100
generated: true
---

`Waypoint\Validation\Messages` · interface · namespace [`Waypoint\Validation`](index.md)

```php
interface Messages
```

Translates validation messages. The Validator looks this up in the container
(bound by a plugin, e.g. i18n) and interpolates the English text itself when
nothing is bound. $text is the English source text; it is also the catalog key.

## Methods

### translate()

```php
public function translate(string $text, array $params): string
```

**Parameters**

- `$text` `string`
- `$params` `array<string, scalar>`: Replace {placeholders} in the translated text.

**Returns** `string`
