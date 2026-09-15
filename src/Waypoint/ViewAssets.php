<?php

namespace Waypoint;

/**
 * Discovers each view's sibling .css/.js files (view "Home" -> Home.css/
 * Home.js, next to Home.php in the configured RendererOptions directory),
 * scopes the CSS to that view (prefixing its selectors with
 * `[data-view="Home"]`, so the client only needs to set that attribute on
 * the swapped container for the browser's own selector matching to enforce
 * the scope -- no runtime CSS engine of ours involved), and content-hashes
 * the result into cache-busted filenames. compile()'s return value is
 * written to disk by FileSystem::storeViewAssetFiles() (one file per
 * filename, under FileSystem::getAssetsDirectory()) and served through
 * Router::tryServeViewAsset() -- a strict {filename => mime} map lookup,
 * never a path built from request input, so a request is only ever
 * answered for a filename this compile pass actually produced, regardless
 * of what a client asks for. Every '*.php' file in the views directory is
 * a candidate view name; there's no separate declaration to keep in sync
 * with what's on disk.
 */
final class ViewAssets
{
    private const EXTENSIONS = ['css', 'js'];

    /** @return string[] Basenames (no .php) of every view file found. */
    public static function discoverViewNames(string $viewsDir): array
    {
        if (!is_dir($viewsDir)) {
            return [];
        }

        $names = [];
        foreach (glob("$viewsDir/*.php") ?: [] as $file) {
            $names[] = basename($file, '.php');
        }
        return $names;
    }

    /**
     * Cheap pass (glob + filemtime only, no hashing) for isAvailable()'s
     * freshness check -- so editing a view's .css/.js invalidates the
     * compiled cache exactly the same way editing a controller does,
     * without paying the hashing cost just to answer "has anything changed".
     *
     * @return array<string, int> {absolute path => mtime}
     */
    public static function discoverMeta(string $viewsDir): array
    {
        $meta = [];
        foreach (self::discoverViewNames($viewsDir) as $name) {
            foreach (self::EXTENSIONS as $ext) {
                $path = "$viewsDir/$name.$ext";
                if (!is_file($path)) {
                    continue;
                }
                $mtime = filemtime($path);
                // @codeCoverageIgnoreStart
                // Only reachable via a race (deleted/permissions changed
                // between is_file() above and filemtime()) that can't be
                // reliably reproduced cross platform -- same guard as
                // FileSystem::readViewAssetFile().
                if ($mtime === false) {
                    continue;
                }
                // @codeCoverageIgnoreEnd
                $meta[$path] = $mtime;
            }
        }
        return $meta;
    }

    /**
     * The real (expensive-ish, only run when a rebuild was already decided)
     * compile pass.
     *
     * @return array{
     *     views: array<string, array{css: ?string, js: ?string}>,
     *     files: array<string, array{content: string, mime: string}>,
     *     meta: array<string, int>,
     * } 'views' is what a request needs to know which cache-busted filenames
     *   belong to the view it's rendering; 'files' is the reverse lookup
     *   GET /assets/{filename} resolves through -- content is stored
     *   directly (the CSS already scoped) rather than a path, so serving
     *   never depends on the source file still existing; 'meta' mirrors
     *   discoverMeta() so storeFromRouter() can persist the same mtimes it
     *   used to decide a rebuild was needed.
     */
    public static function compile(string $viewsDir): array
    {
        $views = [];
        $files = [];
        $meta = [];

        foreach (self::discoverViewNames($viewsDir) as $name) {
            $entry = ['css' => null, 'js' => null];

            foreach (self::EXTENSIONS as $ext) {
                $path = "$viewsDir/$name.$ext";
                if (!is_file($path)) {
                    continue;
                }

                $content = file_get_contents($path);
                $mtime = filemtime($path);
                // @codeCoverageIgnoreStart
                // Only reachable via a race (deleted/permissions changed
                // between is_file() above and file_get_contents()/
                // filemtime()) that can't be reliably reproduced cross
                // platform -- same guard as FileSystem::readViewAssetFile().
                if ($content === false || $mtime === false) {
                    continue;
                }
                // @codeCoverageIgnoreEnd
                if ($ext === 'css') {
                    $content = self::scopeCss($content, $name);
                }

                $filename = substr(md5($content), 0, 12) . ".$ext";
                $entry[$ext] = $filename;
                $files[$filename] = [
                    'content' => $content,
                    'mime' => $ext === 'css' ? 'text/css; charset=utf-8' : 'application/javascript; charset=utf-8',
                ];
                $meta[$path] = $mtime;
            }

            if ($entry['css'] !== null || $entry['js'] !== null) {
                $views[$name] = $entry;
            }
        }

        return ['views' => $views, 'files' => $files, 'meta' => $meta];
    }

    /**
     * Scopes $css to one view by wrapping it in a single native CSS
     * Nesting parent selector, `[data-view="$viewName"] { ...css... }`,
     * rather than rewriting every selector individually. The browser's own
     * nesting resolution does the real work: a plain rule nested under the
     * wrapper becomes a descendant selector under it, and nesting is
     * explicitly spec'd to work the same way *inside* `@media`/`@supports`/
     * `@container` blocks -- so `[data-view="X"] { @media (...) { .card {}
     * } }` correctly scopes `.card` to `X` without this needing to know
     * `@media` exists at all. That also sidesteps a real bug the previous
     * per-selector-rewrite version of this method had: splitting a
     * selector list on every comma breaks as soon as one selector uses a
     * functional pseudo-class with its own comma, e.g. `:is(.a, .b) > .c`
     * -- nesting never touches selector text, so there's nothing to break.
     *
     * The one thing that can't just be nested inside the wrapper:
     * `@keyframes`/`@font-face`. Both are global constructs (a keyframes
     * name, a font family), not selectors -- CSS Nesting doesn't allow
     * nesting them inside a style rule, and their own "selectors" (`0%`,
     * `from`, `to`) aren't real CSS selectors either. extractUnscopable()
     * pulls any such top-level blocks out first so they stay unwrapped, at
     * the stylesheet's top level, exactly as they'd need to be written by
     * hand.
     */
    public static function scopeCss(string $css, string $viewName): string
    {
        $scopeAttr = '[data-view="' . str_replace('"', '\\"', $viewName) . '"]';
        [$unscopable, $rest] = self::extractUnscopable($css);

        if (trim($rest) === '') {
            return $unscopable;
        }

        return $unscopable . "$scopeAttr {\n" . self::indent($rest, 4) . "}\n";
    }

    /** Prefixes every non-blank line of $text with $spaces spaces. */
    private static function indent(string $text, int $spaces): string
    {
        $prefix = str_repeat(' ', $spaces);
        $lines = explode("\n", rtrim($text, "\n"));

        $indented = implode("\n", array_map(
            fn(string $line): string => $line === '' ? '' : $prefix . $line,
            $lines,
        ));

        return $indented . "\n";
    }

    /**
     * Splits $css into top-level @keyframes/@font-face blocks (returned
     * verbatim) and everything else. A single-pass, brace-depth-aware
     * scan, not a full parser: it only classifies a block as unscopable
     * when the at-rule starts at the top level (depth 0) -- an
     * @font-face nested inside e.g. @media is exotic enough in real
     * stylesheets not to special-case here. Comments are recognized
     * (never mistaken for a `{`/`}` inside a string) so a comment
     * containing either character can't desynchronize the brace count.
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
        $captureDepth = null; // depth an unscopable block was entered at; null when not currently inside one
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

            if ($char === '/' && $i + 1 < $length && $css[$i + 1] === '*') {
                $end = strpos($css, '*/', $i + 2);
                $commentEnd = $end === false ? $length : $end + 2;
                $chunk .= substr($css, $i, $commentEnd - $i);
                $i = $commentEnd;
                continue;
            }

            if ($char === '/') {
                $chunk .= '/';
                $i++;
                continue;
            }

            if ($char === '{') {
                if ($depth === 0 && $captureDepth === null) {
                    $isUnscopable = preg_match('/^@(?:-\w+-)?keyframes\b|^@font-face\b/i', trim($chunk)) === 1;
                    if ($isUnscopable) {
                        $captureDepth = $depth;
                    }
                }

                if ($captureDepth !== null) {
                    $unscopable .= $chunk . '{';
                } else {
                    $rest .= $chunk . '{';
                }
                $depth++;
            } else { // '}'
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
