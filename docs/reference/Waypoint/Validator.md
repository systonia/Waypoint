---
title: "Validator"
description: "Validates a DTO's public properties against #[NotBlank]/#[Email]/#[Length]/ #[Regex], #[OneOf], #[SameAs]."
order: 100
generated: true
---

`Waypoint\Validator` · class · namespace [`Waypoint`](index.md)

```php
class Validator
```

Validates a DTO's public properties against #[NotBlank]/#[Email]/#[Length]/
#[Regex], #[OneOf], #[SameAs]. The attribute set per class is reflected once and cached, so
validating the same DTO class on every request costs no reflection.

## Methods

### validate()

```php
public function validate(object $dto): array
```

**Parameters**

- `$dto` `object`

**Returns** `array<string, string>`: field => message (the last failing rule wins, in NotBlank, Email, Length, Regex, OneOf, SameAs order).
