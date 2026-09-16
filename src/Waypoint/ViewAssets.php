<?php

namespace Waypoint;

/**
 * Discovers each view's sibling .css/.js (Home.php -> Home.css/Home.js, any
 * depth under the views directory), scopes the CSS to that view with a
 * `[data-view="Name"]` nesting wrapper, and content-hashes the result into
 * cache-busted filenames. FileSystem stores the files; Router serves them by
 * a strict {filename => mime} lookup, never by a path built from the request.
 */
final class ViewAssets
{
    private const EXTENSIONS = ['css', 'js'];

    /** The bundled client (built by the Waypoint-UI project, shipped inside this package); compiled into the same pipeline by Router. */
    public const WAYPOINT_JS = __DIR__ . '/UI/waypoint.js';

    /**
     * Every view file under $viewsDir as its View name: the path relative to $viewsDir, no .php,
     * '/'-separated on every platform ("Home", "Admin/Users"). The same string keys compile()'s
     * 'views' map and is baked into the CSS scope, so a nested view's assets resolve exactly like a
     * top-level one's.
     *
     * @return string[]
     */
    public static function discoverViewNames(string $viewsDir): array
    {
        $names = [];
        if (is_dir($viewsDir)) {
            self::collectViewNames($viewsDir, '', $names);
        }
        return $names;
    }

    /** @param string[] $names */
    private static function collectViewNames(string $dir, string $prefix, array &$names): void
    {
        foreach (glob("$dir/*.php") ?: [] as $file) {
            $names[] = $prefix . basename($file, '.php');
        }
        foreach (glob("$dir/*", GLOB_ONLYDIR) ?: [] as $subdir) {
            self::collectViewNames($subdir, $prefix . basename($subdir) . '/', $names);
        }
    }

    /**
     * {path => mtime} of every sibling asset -- the cheap freshness check (no hashing) that lets
     * FileSystem::isAvailable() notice an edited .css/.js the same way it notices an edited controller.
     *
     * @return array<string, int>
     */
    public static function discoverMeta(string $viewsDir): array
    {
        $meta = [];
        foreach (self::siblingAssets($viewsDir) as [, , $path]) {
            $mtime = filemtime($path);
            if ($mtime !== false) {
                $meta[$path] = $mtime;
            }
        }
        return $meta;
    }

    /**
     * The full compile pass.
     *
     * @return array{
     *     views: array<string, array{css: ?string, js: ?string}>,
     *     files: array<string, array{content: string, mime: string}>,
     *     meta: array<string, int>,
     * } 'views': name => its cache-busted filenames; 'files': what GET /assets/{filename} serves
     *   (the scoped content itself, so serving never depends on the source file); 'meta': discoverMeta().
     */
    public static function compile(string $viewsDir): array
    {
        $views = [];
        $files = [];
        $meta = [];

        foreach (self::siblingAssets($viewsDir) as [$name, $ext, $path]) {
            $content = file_get_contents($path);
            $mtime = filemtime($path);
            if ($content === false || $mtime === false) {
                // @codeCoverageIgnoreStart
                // only a delete/permission race after is_file().
                continue;
                // @codeCoverageIgnoreEnd
            }
            if ($ext === 'css') {
                $content = self::scopeCss($content, $name);
            }
            $filename = substr(md5($content), 0, 12) . ".$ext";
            $views[$name] ??= ['css' => null, 'js' => null];
            $views[$name][$ext] = $filename;
            $files[$filename] = [
                'content' => $content,
                'mime' => $ext === 'css' ? 'text/css; charset=utf-8' : 'application/javascript; charset=utf-8',
            ];
            $meta[$path] = $mtime;
        }

        return ['views' => $views, 'files' => $files, 'meta' => $meta];
    }

    /** @return iterable<array{0: string, 1: 'css'|'js', 2: string}> [view name, extension, path] for every existing sibling asset. */
    private static function siblingAssets(string $viewsDir): iterable
    {
        foreach (self::discoverViewNames($viewsDir) as $name) {
            foreach (self::EXTENSIONS as $ext) {
                $path = "$viewsDir/$name.$ext";
                if (is_file($path)) {
                    yield [$name, $ext, $path];
                }
            }
        }
    }

    /**
     * Wraps $css in one native CSS Nesting rule, `[data-view="$viewName"] { ... }`, and lets the
     * browser resolve the scope -- nested rules become descendant selectors, and nesting is spec'd to
     * work inside @media/@supports/@container too. Selector text is never touched, so a comma inside
     * `:is(.a, .b)` can't break anything. Top-level @keyframes/@font-face are global constructs that
     * can't be nested, so extractUnscopable() lifts them out unchanged first.
     */
    public static function scopeCss(string $css, string $viewName): string
    {
        [$unscopable, $rest] = self::extractUnscopable($css);
        if (trim($rest) === '') {
            return $unscopable;
        }
        $scope = '[data-view="' . str_replace('"', '\\"', $viewName) . '"]';
        return $unscopable . "$scope {\n" . self::indent($rest, 4) . "}\n";
    }

    private static function indent(string $text, int $spaces): string
    {
        $prefix = str_repeat(' ', $spaces);
        $lines = array_map(fn(string $line): string => $line === '' ? '' : $prefix . $line, explode("\n", rtrim($text, "\n")));
        return implode("\n", $lines) . "\n";
    }

    /**
     * Splits $css into top-level @keyframes/@font-face blocks (verbatim) and everything else, with a
     * single brace-depth-aware scan that skips comments so a `{` inside one can't desynchronize it.
     * Only a top-level (depth 0) at-rule is lifted.
     *
     * @return array{0: string, 1: string} [unscopable blocks, everything else]
     */
    private static function extractUnscopable(string $css): array
    {
        $unscopable = '';
        $rest = '';
        $length = strlen($css);
        $i = 0;
        $depth = 0;
        $captureDepth = null;
        $chunk = '';

        while ($i < $length) {
            $span = strcspn($css, '{}/', $i);
            $chunk .= substr($css, $i, $span);
            $i += $span;

            if ($i >= $length) {
                if ($captureDepth !== null) {
                    $unscopable .= $chunk;
                } else {
                    $rest .= $chunk;
                }
                break;
            }

            $char = $css[$i];
            if ($char === '/') {
                if ($i + 1 < $length && $css[$i + 1] === '*') {
                    $end = strpos($css, '*/', $i + 2);
                    $commentEnd = $end === false ? $length : $end + 2;
                    $chunk .= substr($css, $i, $commentEnd - $i);
                    $i = $commentEnd;
                } else {
                    $chunk .= '/';
                    $i++;
                }
                continue;
            }

            if ($char === '{') {
                if ($depth === 0 && $captureDepth === null && preg_match('/^@(?:-\w+-)?keyframes\b|^@font-face\b/i', trim($chunk)) === 1) {
                    $captureDepth = $depth;
                }
                if ($captureDepth !== null) {
                    $unscopable .= $chunk . '{';
                } else {
                    $rest .= $chunk . '{';
                }
                $depth++;
            } else {
                $depth--;
                if ($captureDepth !== null) {
                    $unscopable .= $chunk . '}';
                    if ($depth <= $captureDepth) {
                        $captureDepth = null;
                    }
                } else {
                    $rest .= $chunk . '}';
                }
            }

            $chunk = '';
            $i++;
        }

        return [$unscopable, $rest];
    }
}
