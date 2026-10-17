<?php

namespace Waypoint\View;

use ReflectionClass;
use Tenma\Lsp\{Elements, Imports, Provider, Tokens, Variables};
use Waypoint\Attributes\ListOf;
use Waypoint\Attributes\Returns;
use Waypoint\Http\View;

/**
 * @internal Views in the editor, through `tenma lsp` (composer.json names it under extra.tenma.lsp): `$this` in a
 * view is the View and `$model` the class `@model` names, so tenma completes their members, shows them on hover
 * and leads F12 to them; F12 on a class name in `@model (...)` or `@use (...)` leads to the class. The problems
 * the check finds without PHPStan show while the view is typed, and directives get their colours: the editor's PHP
 * grammar sees them as text.
 *
 * For every class, not only in views: the element class `#[ListOf]` names for an array property (also a promoted
 * one) and `#[Returns(list: true)]` or `map: true` for a method, so tenma types `@foreach ($model->items as $item)`
 * and `foreach ($this->items() as $item)` wherever it types variables.
 *
 * The views are the files below Check::directories(): the editor runs no app.
 *
 * @phpstan-type TokenType 'keyword'|'comment'|'variable'|'property'|'method'|'function'|'class'|'string'|'number'|'operator'
 * @phpstan-type Token array{line: int, column: int, length: int, type: TokenType}
 */
final class Editor implements Provider, Tokens, Imports, Variables, Elements
{
    /** @var list<string>|null The views directories, each with a / at the end */
    private ?array $directories = null;

    public function __construct(private readonly string $root)
    {
    }

    public function thisClass(string $file): ?string
    {
        return $this->isView($file) ? View::class : null;
    }

    public function completions(string $file, string $text, int $offset): array
    {
        return [];
    }

    public function definition(string $file, string $text, int $offset): ?array
    {
        if (!$this->isView($file)) {
            return null;
        }
        $compiler = new Compiler($text);
        // A class name in @model (...) or @use (...)
        foreach ($compiler->found as $found) {
            [$from, $to] = $found['expression'] ?? [0, 0];
            if (in_array($found['kind'], ['model', 'use'], true) && $offset >= $from && $offset <= $to) {
                preg_match('/[\w\\\\]*$/', substr($text, $from, $offset - $from), $head);
                preg_match('/^[\w\\\\]*/', substr($text, $offset, $to - $offset), $tail);
                $class = self::resolve(($head[0] ?? '') . ($tail[0] ?? ''), $compiler->uses);
                if ($class !== null) {
                    $reflection = new ReflectionClass($class);
                    return ['file' => (string) $reflection->getFileName(), 'line' => (int) $reflection->getStartLine()];
                }
            }
        }
        return null;
    }

    public function diagnostics(string $file, string $text): array
    {
        return $this->isView($file) ? Check::inView($text, $file) : [];
    }

    public function variables(string $file, string $text): array
    {
        $model = $this->isView($file) ? self::model($text) : null;
        return $model !== null ? ['model' => $model] : [];
    }

    public function elements(string $class): array
    {
        if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
            return [];
        }
        $reflection = new ReflectionClass($class);
        $elements = [];
        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes(ListOf::class) as $attribute) {
                $elements[$property->getName()] = $attribute->newInstance()->type;
            }
        }
        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes(Returns::class) as $attribute) {
                $returns = $attribute->newInstance();
                if ($returns->list || $returns->map) {
                    $elements[$method->getName() . '()'] = $returns->type;
                }
            }
        }
        // int, string, ...: no class to complete
        return array_filter($elements, static fn (string $type) => class_exists($type) || interface_exists($type));
    }

    public function imports(string $file, string $text): array
    {
        return $this->isView($file) ? ['' => ''] + (new Compiler($text))->uses : [];
    }

    /** @return list<Token> */
    public function tokens(string $file, string $text): array
    {
        return $this->isView($file) ? self::highlight(new Compiler($text), $text) : [];
    }

    /**
     * @internal The tokens of the directives a compiler found in $text, for views and Live templates.
     *
     * @return list<Token>
     */
    public static function highlight(Compiler $compiler, string $text): array
    {
        $tokens = [];
        foreach ($compiler->found as $found) {
            if ($found['kind'] === 'at' || $found['kind'] === 'insert') {
                continue;
            }
            if ($found['kind'] === 'class') {
                self::token($tokens, $text, $found['start'], $found['start'] + 6, 'keyword');
                self::token($tokens, $text, $found['start'] + 7, $found['start'] + 7 + strlen($found['class'] ?? ''), 'string');
                self::php($tokens, $text, ...($found['expression'] ?? [0, 0]));
                continue;
            }
            if ($found['kind'] === 'comment' || $found['kind'] === 'broken') {
                self::token($tokens, $text, $found['start'], $found['end'], $found['kind'] === 'comment' ? 'comment' : 'keyword');
                continue;
            }
            [$from, $to] = $found['expression'] ?? [$found['end'], $found['end']];
            if (in_array($found['kind'], ['directive', 'use', 'model'], true)) {
                self::token($tokens, $text, $found['start'], $found['start'] + 1 + strlen($found['name'] ?? ''), 'keyword');
            } else {
                // {{ and }}, @code and @endcode
                self::token($tokens, $text, $found['start'], $from, 'keyword');
                self::token($tokens, $text, $to, $found['end'], 'keyword');
            }
            self::php($tokens, $text, $from, $to);
        }
        return $tokens;
    }

    /**
     * @internal PHP's own tokens of an expression: variables, members, calls, classes, strings, numbers, keywords.
     *
     * @param list<Token> $tokens
     */
    public static function php(array &$tokens, string $text, int $from, int $to): void
    {
        $php = token_get_all('<?php ' . substr($text, $from, $to - $from));
        $at = $from - 6;
        foreach ($php as $i => $token) {
            $length = strlen(is_array($token) ? $token[1] : $token);
            if (is_array($token)) {
                $type = match ($token[0]) {
                    T_VARIABLE => 'variable',
                    T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE => 'string',
                    T_LNUMBER, T_DNUMBER => 'number',
                    T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED => 'class',
                    T_STRING => self::name($php, $i),
                    T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT => null,
                    // as, instanceof, fn, match, new, ...: every other word
                    default => ctype_alpha($token[1]) ? 'keyword' : 'operator',
                };
                if ($type !== null) {
                    self::token($tokens, $text, $at, $at + $length, $type);
                }
            }
            $at += $length;
        }
    }

    /**
     * What a bare name is by what surrounds it: `->name(` a method, `->name` a property, `name(` a function.
     *
     * @param list<string|array{int, string, int}> $php
     * @return 'method'|'property'|'keyword'|'function'|'class'
     */
    private static function name(array $php, int $i): string
    {
        $neighbour = static function (int $step) use ($php, $i): string {
            for ($j = $i + $step; isset($php[$j]); $j += $step) {
                if (!is_array($php[$j]) || $php[$j][0] !== T_WHITESPACE) {
                    return is_array($php[$j]) ? $php[$j][1] : $php[$j];
                }
            }
            return '';
        };
        $call = $neighbour(1) === '(';
        return match (true) {
            in_array($neighbour(-1), ['->', '?->', '::'], true) => $call ? 'method' : 'property',
            in_array(strtolower(is_array($php[$i]) ? $php[$i][1] : ''), ['true', 'false', 'null'], true) => 'keyword',
            $call => 'function',
            default => 'class',
        };
    }

    /**
     * @internal One token per line it covers: editors take no token across lines.
     *
     * @param list<Token> $tokens
     * @param TokenType $type
     */
    public static function token(array &$tokens, string $text, int $start, int $end, string $type): void
    {
        while ($start < $end) {
            $newlineBefore = strrpos(substr($text, 0, $start), "\n");
            $lineStart = $newlineBefore === false ? 0 : $newlineBefore + 1;
            $newline = strpos($text, "\n", $start);
            $stop = $newline === false ? $end : min($end, $newline);
            if ($stop > $start) {
                $tokens[] = ['line' => substr_count($text, "\n", 0, $start) + 1, 'column' => $start - $lineStart, 'length' => $stop - $start, 'type' => $type];
            }
            $start = $stop + 1;
        }
    }

    /** @return class-string|null The class `@model` names, when it names one */
    private static function model(string $text): ?string
    {
        $compiler = new Compiler($text);
        return $compiler->model !== null ? self::resolve(ltrim($compiler->model, '?'), $compiler->uses) : null;
    }

    /**
     * The class a name in a view means, through its `@use`; null for no class.
     *
     * @param array<string, string> $uses
     * @return class-string|null
     */
    private static function resolve(string $name, array $uses): ?string
    {
        if (preg_match('/^\\\\?[A-Za-z_][\w\\\\]*$/', $name) !== 1) {
            return null;
        }
        $name = ltrim($name, '\\');
        [$first] = explode('\\', $name, 2);
        foreach ($uses as $alias => $class) {
            if (strcasecmp($alias, $first) === 0) {
                $name = $class . substr($name, strlen($first));
            }
        }
        return class_exists($name) || interface_exists($name) ? $name : null;
    }

    private function isView(string $file): bool
    {
        $this->directories ??= array_map(static fn (string $directory) => (realpath($directory) ?: $directory) . '/', Check::directories($this->root));
        $file = realpath($file) ?: $file;
        foreach ($this->directories as $directory) {
            if (str_starts_with($file, $directory) && str_ends_with($file, '.php')) {
                return true;
            }
        }
        return false;
    }
}
