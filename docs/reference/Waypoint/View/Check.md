---
title: "Check"
description: "`vendor/bin/waypoint-check`: the views of a project, and the templates of packages that add theirs (Checks), checked without running the app."
order: 100
generated: true
---

`Waypoint\View\Check` · final class · namespace [`Waypoint\View`](index.md)

```php
final class Check
```

`vendor/bin/waypoint-check`: the views of a project, and the templates of packages that add theirs (Checks),
checked without running the app. The compiler's problems come first, then PHPStan reads every view as the PHP
it becomes, with `$this` as the View and `$model` of the type `@model` names: an undefined property, a method
with the wrong arguments, at the view's line.

The views are the `.php` files below `views/`, or below the directories the project's composer.json names
under `extra.waypoint.views`. In a `tenma.dev.php` hook, publish() shows PHPStan's findings in the editor:

    new Development\Hook(
        name: 'waypoint-check',
        inputs: ['views/**', 'src/**'],
        run: fn () => Waypoint\View\Check::publish(__DIR__),
    ),

## Methods

### problems()

```php
public static function problems(string $root): array
```

What the compiler finds in the views of the project at $root, and what the packages' Checks find in theirs.

**Parameters**

- `$root` `string`

**Returns** `list<array{file: string, line: int, message: string, severity: 'error'|'warning'}>`

### inView()

```php
public static function inView(string $source, string $file = 'view.php'): array
```

What the compiler finds in one view: its syntax, then, if that compiles, PHP that does not parse and a `//`
comment inside an echo.

**Parameters**

- `$source` `string`
- `$file` `string`

**Returns** `list<array{line: int, message: string, severity: 'error'|'warning'}>`

### phpstan()

```php
public static function phpstan(string $root): array
```

What PHPStan finds in the views and the packages' templates of the project at $root, at their lines. Files
with an error problems() reports are left out: they would only fail to parse. PHPStan runs with the
project's phpstan.neon (its level, its extensions) if there is one, otherwise at level 6; views at level 8
at most (VIEW_LEVEL).

**Parameters**

- `$root` `string`

**Returns** `list<array{file: string, line: int, message: string, severity: 'error'}>`

### publish()

```php
public static function publish(string $root): void
```

For a tenma.dev.php hook: PHPStan's findings go to the editor, through Development\Problems::report().

**Parameters**

- `$root` `string`

### main()

```php
public static function main(array $argv, string $name = 'waypoint-check'): int
```

`vendor/bin/waypoint-check [directory]`: every problem, one per line, and the exit code: 0 without errors, 1
with, 2 when the check cannot run.

**Parameters**

- `$argv` `list<string>`
- `$name` `string`

**Returns** `int`

### views()

```php
public static function views(string $root): array
```

The view files of the project at $root: below its views directories (directories()).

**Parameters**

- `$root` `string`

**Returns** `list<string>`
