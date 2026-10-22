<?php

namespace Waypoint\View;

use Closure;
use FilesystemIterator;
use ParseError;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Waypoint\Html;
use Waypoint\Http\View;

/**
 * `vendor/bin/waypoint-check`: the views of a project, and the templates of packages that add theirs (Checks),
 * checked without running the app. The compiler's problems come first, then PHPStan reads every view as the PHP
 * it becomes, with `$this` as the View and `$model` of the type `@model` names: an undefined property, a method
 * with the wrong arguments, at the view's line.
 *
 * The views are the `.php` files below `views/`, or below the directories the project's composer.json names
 * under `extra.waypoint.views`. In a `tenma.dev.php` hook, publish() shows PHPStan's findings in the editor:
 *
 *     new Development\Hook(
 *         name: 'waypoint-check',
 *         inputs: ['views/**', 'src/**'],
 *         run: fn () => Waypoint\View\Check::publish(__DIR__),
 *     ),
 */
final class Check
{
    /**
     * Views are read at the project's PHPStan level, but at 8 at most: what a plugin's view helper returns
     * (`$this->t()`) is known only to the running app, so it is mixed, which levels 9 and 10 refuse to use.
     */
    private const VIEW_LEVEL = 8;

    /**
     * @internal What PHPStan reads in a generated file: $view runs with `$this` as a View, as a view does.
     *
     * @param Closure $view
     * @param-closure-this View $view
     */
    public static function bind(Closure $view): void
    {
    }

    /**
     * What the compiler finds in the views of the project at $root, and what the packages' Checks find in theirs.
     *
     * @return list<array{file: string, line: int, message: string, severity: 'error'|'warning'}>
     */
    public static function problems(string $root): array
    {
        $problems = [];
        foreach (self::views($root) as $file) {
            foreach (self::inView((string) file_get_contents($file), $file) as $problem) {
                $problems[] = ['file' => $file] + $problem;
            }
        }
        foreach (self::checks($root) as $checks) {
            array_push($problems, ...$checks->problems());
        }
        return $problems;
    }

    /**
     * What the compiler finds in one view: its syntax, then, if that compiles, PHP that does not parse and a `//`
     * comment inside an echo.
     *
     * @return list<array{line: int, message: string, severity: 'error'|'warning'}>
     */
    public static function inView(string $source, string $file = 'view.php'): array
    {
        $compiler = new Compiler($source);
        $problems = $compiler->problems;
        if (in_array('error', array_column($problems, 'severity'), true)) {
            return $problems;
        }
        $comments = self::comments($compiler->compile());
        if ($comments !== []) {
            return [...$problems, ...$comments];
        }
        try {
            // Parsed only, never run: the compiled code keeps the view's lines
            token_get_all(View::compile($source, $file), TOKEN_PARSE);
        } catch (ParseError $e) {
            $problems[] = ['line' => $e->getLine(), 'message' => $e->getMessage(), 'severity' => 'error'];
        }
        return $problems;
    }

    /**
     * What PHPStan finds in the views and the packages' templates of the project at $root, at their lines. Files
     * with an error problems() reports are left out: they would only fail to parse. PHPStan runs with the
     * project's phpstan.neon (its level, its extensions) if there is one, otherwise at level 6; views at level 8
     * at most (VIEW_LEVEL).
     *
     * @return list<array{file: string, line: int, message: string, severity: 'error'}>
     */
    public static function phpstan(string $root): array
    {
        $binary = "$root/vendor/bin/phpstan";
        if (!is_file($binary)) {
            throw new RuntimeException('the check needs PHPStan: composer require --dev phpstan/phpstan');
        }
        // In the system's temp directory, one per project: nothing to ignore in the project
        $directory = sys_get_temp_dir() . '/waypoint-check-' . md5((string) realpath($root));
        array_map('unlink', glob("$directory/*.php") ?: []);
        @mkdir($directory, 0777, true);
        $views = [];
        foreach (self::views($root) as $i => $file) {
            $source = (string) file_get_contents($file);
            if (in_array('error', array_column(self::inView($source, $file), 'severity'), true)) {
                continue;
            }
            $generated = "$directory/view-$i-" . basename($file);
            file_put_contents($generated, self::generated($source, $file));
            $views[(string) realpath($generated)] = $file;
        }
        $templates = [];
        foreach (self::checks($root) as $checks) {
            foreach ($checks->generate($directory) as $generated => $template) {
                $templates[realpath($generated) ?: $generated] = $template;
            }
        }
        $config = is_file("$root/phpstan.neon") ? "$root/phpstan.neon" : (is_file("$root/phpstan.neon.dist") ? "$root/phpstan.neon.dist" : null);
        $level = $config !== null && preg_match('/^\s*level:\s*(\d+|max)\b/m', (string) file_get_contents($config), $m) === 1 ? ($m[1] === 'max' ? 10 : (int) $m[1]) : 6;
        return [
            ...self::analyse($root, $templates, $config === null ? 6 : null),
            ...self::helpers(self::analyse($root, $views, $level > self::VIEW_LEVEL || $config === null ? min($level, self::VIEW_LEVEL) : null)),
        ];
    }

    /**
     * A method View lacks is a plugin's view helper, which only the running app knows: PHPStan's finding of it goes,
     * unless the name is close to one of View's own methods, a typo then.
     *
     * @param list<array{file: string, line: int, message: string, severity: 'error'}> $problems
     * @return list<array{file: string, line: int, message: string, severity: 'error'}>
     */
    private static function helpers(array $problems): array
    {
        $methods = array_map(static fn (\ReflectionMethod $method) => $method->getName(), (new \ReflectionClass(View::class))->getMethods());
        $kept = [];
        foreach ($problems as $problem) {
            if (preg_match('/^Call to an undefined method ' . preg_quote(View::class, '/') . '::(\w+)\(\)\.$/', $problem['message'], $m) === 1) {
                $close = array_values(array_filter($methods, static fn (string $method) => !str_starts_with($method, '__') && levenshtein(strtolower($m[1]), strtolower($method)) <= 2));
                if ($close === []) {
                    continue;
                }
                $problem['message'] .= " Did you mean {$close[0]}()?";
            }
            $kept[] = $problem;
        }
        return $kept;
    }

    /** For a tenma.dev.php hook: PHPStan's findings go to the editor, through Development\Problems::report(). */
    public static function publish(string $root): void
    {
        $problems = self::phpstan($root);
        if (class_exists(\Development\Problems::class, false)) {
            \Development\Problems::report('waypoint:phpstan', $problems);
        }
    }

    /**
     * `vendor/bin/waypoint-check [directory]`: every problem, one per line, and the exit code: 0 without errors, 1
     * with, 2 when the check cannot run.
     *
     * @param list<string> $argv
     */
    public static function main(array $argv, string $name = 'waypoint-check'): int
    {
        $root = realpath($argv[1] ?? (string) getcwd()) ?: (string) getcwd();
        try {
            $problems = [...self::problems($root), ...self::phpstan($root)];
        } catch (RuntimeException $e) {
            fwrite(STDERR, "$name: " . $e->getMessage() . "\n");
            return 2;
        }
        usort($problems, static fn (array $a, array $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);
        foreach ($problems as $problem) {
            $file = str_starts_with($problem['file'], "$root/") ? substr($problem['file'], strlen($root) + 1) : $problem['file'];
            echo "$file:{$problem['line']}: {$problem['severity']}: {$problem['message']}\n";
        }
        $errors = count(array_filter($problems, static fn (array $p) => $p['severity'] === 'error'));
        fwrite(STDERR, $problems === [] ? "$name: no problems\n" : "$name: $errors errors, " . (count($problems) - $errors) . " warnings\n");
        return $errors > 0 ? 1 : 0;
    }

    /**
     * @internal The view as PHPStan reads it, line for line: the compiled view leaves PHP on the first line, after
     * its imports and the bind. The closing tag at the end ends a view that ends in PHP, and is only text after one
     * that ends in HTML.
     */
    public static function generated(string $source, string $file): string
    {
        $model = (new Compiler($source))->model;
        $bind = '\\' . self::class . '::bind(function ($model, ?\\' . Html::class . ' $content): void { ' . ($model !== null ? "/** @var $model \$model */ " : '');
        $php = View::compile($source, $file, "\0");
        [$header, $body] = explode("\0", $php, 2);
        [$uses, $body] = self::hoist($body);
        return $header . $uses . $bind . $body . "\n?><?php });\n";
    }

    /**
     * A view's own `use` statements, which PHP takes at its top level but not in the closure PHPStan reads: out of
     * $body, the lines kept, and as they are for the first line.
     *
     * @return array{string, string} The statements, the body without them
     */
    private static function hoist(string $body): array
    {
        $uses = '';
        $code = '';
        $depth = 0;
        $statement = null;
        $tokens = token_get_all('<?php ' . $body);
        array_shift($tokens);
        foreach ($tokens as $i => $token) {
            $text = is_array($token) ? $token[1] : $token;
            $depth += match ($text) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };
            $next = $tokens[$i + 1] ?? null;
            $next = is_array($next) && $next[0] === T_WHITESPACE ? $tokens[$i + 2] ?? null : $next;
            if ($statement === null && $depth === 0 && is_array($token) && $token[0] === T_USE && $next !== '(') {
                $statement = '';
            }
            if ($statement === null) {
                $code .= $text;
                continue;
            }
            $statement .= $text;
            $code .= (string) preg_replace('/[^\r\n]/', ' ', $text);
            if ($text === ';' || (is_array($token) && $token[0] === T_CLOSE_TAG)) {
                $uses .= rtrim(str_replace(["\r", "\n"], ' ', $statement), " ?>\t") . (str_ends_with(rtrim($statement), ';') ? ' ' : '; ');
                $statement = null;
            }
        }
        return [$uses, $code];
    }

    /**
     * The view files of the project at $root: below its views directories (directories()).
     *
     * @return list<string>
     */
    public static function views(string $root): array
    {
        $files = [];
        foreach (self::directories($root) as $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
                $path = $file instanceof \SplFileInfo ? $file->getPathname() : '';
                if (str_ends_with($path, '.php')) {
                    $files[] = $path;
                }
            }
        }
        sort($files);
        return $files;
    }

    /**
     * @internal The views directories of the project at $root that exist: `views/`, or those its composer.json
     * names under `extra.waypoint.views`.
     *
     * @return list<string>
     */
    public static function directories(string $root): array
    {
        $composer = json_decode((string) @file_get_contents("$root/composer.json"), true);
        $named = self::extra($composer, 'views') ?? 'views';
        $directories = [];
        foreach ((array) $named as $directory) {
            if (is_string($directory) && is_dir("$root/" . trim($directory, '/'))) {
                $directories[] = "$root/" . trim($directory, '/');
            }
        }
        return $directories;
    }

    /**
     * The packages' Checks: the classes named under `extra.waypoint.checks` in the project's composer.json and in
     * every installed package's.
     *
     * @return list<Checks>
     */
    private static function checks(string $root): array
    {
        $extras = [];
        $project = json_decode((string) @file_get_contents("$root/composer.json"), true);
        $extras[] = self::extra($project, 'checks');
        $installed = json_decode((string) @file_get_contents("$root/vendor/composer/installed.json"), true);
        $packages = is_array($installed) ? $installed['packages'] ?? $installed : [];
        foreach (is_array($packages) ? $packages : [] as $package) {
            // The package's own composer.json: one from a path repository may be newer than installed.json
            $path = is_array($package) && is_string($package['install-path'] ?? null) ? "$root/vendor/composer/{$package['install-path']}/composer.json" : null;
            $own = $path !== null ? json_decode((string) @file_get_contents($path), true) : null;
            $extras[] = self::extra(is_array($own) ? $own : $package, 'checks');
        }
        $checks = [];
        foreach ($extras as $extra) {
            foreach ((array) $extra as $class) {
                if (is_string($class) && is_subclass_of($class, Checks::class)) {
                    $checks[$class] ??= new $class($root);
                }
            }
        }
        return array_values($checks);
    }

    /** `extra.waypoint.$key` of a decoded composer.json, or null. */
    private static function extra(mixed $composer, string $key): mixed
    {
        foreach (['extra', 'waypoint', $key] as $name) {
            $composer = is_array($composer) ? $composer[$name] ?? null : null;
        }
        return $composer;
    }

    /**
     * PHPStan on $files (generated file => source file), their findings at the source's lines.
     *
     * @param array<string, string> $files
     * @return list<array{file: string, line: int, message: string, severity: 'error'}>
     */
    private static function analyse(string $root, array $files, ?int $level): array
    {
        if ($files === []) {
            return [];
        }
        // Outside the PHP CLI (under Tenma PHP_BINARY is empty) the php on the PATH runs PHPStan
        $command = [PHP_SAPI === 'cli' ? PHP_BINARY : 'php', "$root/vendor/bin/phpstan", 'analyse', '--no-progress', '--error-format=json', '--memory-limit=1G'];
        $command = [...$command, ...($level !== null ? ["--level=$level"] : []), ...array_keys($files)];
        // stderr into a file: read after stdout through a pipe, a full pipe would leave both processes waiting
        $log = (string) tempnam(sys_get_temp_dir(), 'waypoint-check');
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['file', $log, 'w']], $pipes, $root);
        if ($process === false) {
            throw new RuntimeException('PHPStan does not start');
        }
        $output = (string) stream_get_contents($pipes[1]);
        proc_close($process);
        $errors = (string) @file_get_contents($log);
        @unlink($log);
        $result = json_decode($output, true);
        if (!is_array($result) || !is_array($result['files'] ?? null)) {
            throw new RuntimeException('PHPStan failed: ' . trim($errors . "\n" . $output));
        }
        $problems = [];
        foreach ($result['files'] as $file => $found) {
            foreach (is_array($found) && is_array($found['messages'] ?? null) ? $found['messages'] : [] as $message) {
                if (!is_array($message) || !is_int($message['line'] ?? null) || !is_string($message['message'] ?? null)) {
                    continue;
                }
                $problems[] = [
                    'file' => $files[realpath((string) $file) ?: (string) $file] ?? (string) $file,
                    'line' => $message['line'],
                    'message' => $message['message'],
                    'severity' => 'error',
                ];
            }
        }
        return $problems;
    }

    /**
     * A `//` or `#` comment inside an echo of compiled template code, which would also comment out what the
     * compiler adds before `?>`. $noun is what the message calls the file.
     *
     * @return list<array{line: int, message: string, severity: 'error'}>
     */
    public static function comments(string $php, string $noun = 'view'): array
    {
        $problems = [];
        $echo = false;
        foreach (token_get_all($php) as $token) {
            if (!is_array($token)) {
                continue;
            }
            $echo = match ($token[0]) {
                T_OPEN_TAG_WITH_ECHO => true,
                T_CLOSE_TAG => false,
                default => $echo,
            };
            if ($echo && $token[0] === T_COMMENT && !str_starts_with($token[1], '/*')) {
                $problems[] = [
                    'line' => $token[2],
                    'message' => "A line comment inside <?= ?> also comments out what the $noun adds before ?>: use /* */",
                    'severity' => 'error',
                ];
            }
        }
        return $problems;
    }
}
