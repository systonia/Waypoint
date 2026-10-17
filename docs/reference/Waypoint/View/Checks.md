---
title: "Checks"
description: "What a package adds to `vendor/bin/waypoint-check`: its own templates, checked without running the app."
order: 100
generated: true
---

`Waypoint\View\Checks` · interface · namespace [`Waypoint\View`](index.md)

```php
interface Checks
```

What a package adds to `vendor/bin/waypoint-check`: its own templates, checked without running the app. The
package names its class in its composer.json, under `extra.waypoint.checks`; the check creates it with the
project's directory:

    "extra": { "waypoint": { "checks": "Waypoint\\Live\\TemplateChecks" } }

## Methods

### __construct()

```php
public function __construct(string $root)
```

**Parameters**

- `$root` `string`

### problems()

```php
public function problems(): array
```

What the package finds in its templates without PHPStan: syntax, names, wiring.

**Returns** `list<array{file: string, line: int, message: string, severity: 'error'|'warning'}>`

### generate()

```php
public function generate(string $directory): array
```

Writes its templates into $directory as the PHP PHPStan reads, each line where the template has it, and
leaves out those with an error problems() reports.

**Parameters**

- `$directory` `string`

**Returns** `array<string, string>`: generated file => template file
