<?php

namespace Waypoint\Options;

/**
 * Holds every env key/value Waypoint's Environment service reads from --
 * system env vars, $_ENV, $_SERVER, then .env/.env.$APP_ENV, then
 * config.json/config.$APP_ENV.json/$localConfigFile, each overlaid on top
 * of the last. Resolved through the container like any other Options
 * class -- configure it via App::configure() if you need to seed it
 * explicitly (e.g. a non-default directory, or a different local config
 * filename) before anything reads from it:
 *
 *   $app->configure(function (EnvironmentOptions $opts) {
 *       $opts->load(__DIR__ . '/..');
 *   });
 *
 * Otherwise get()/isDev() lazily call load() themselves on first use, same
 * as always -- explicit configuration is only needed to point load() at a
 * directory other than getcwd(), or to change/disable $localConfigFile.
 */
class EnvironmentOptions
{
    /**
     * All env keys/values managed by this class.
     *
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * An optional, gitignored JSON file (relative to load()'s $dir) read
     * last, so it wins over everything else -- config.json/
     * config.$APP_ENV.json are meant to be committed (safe defaults, or
     * demo/dev-only values), while this one is meant to be an
     * uncommitted, per-developer override: real secrets or local-only
     * values that would otherwise have to live in real system/shell env
     * vars just to keep them out of version control. Silently skipped if
     * the file doesn't exist, same as every other layer here -- set to
     * null to disable this layer entirely.
     *
     * @var string|null
     */
    public ?string $localConfigFile = 'config.local.json';

    /**
     * Loads environment variables from system, then extends with server,
     * .env, and JSON config files. Does not mutate any global state.
     *
     * @param string|null $dir Directory to look for .env/.env.$APP_ENV and
     *  config.json/config.$APP_ENV.json/$localConfigFile in. Defaults to
     *  the current working directory (the project root when PHP is
     *  invoked from there, e.g. `php -S ... -t public`, `composer`/CLI
     *  tasks, most process managers) rather than this file's own location --
     *  under a real Composer install, __DIR__ would resolve to somewhere
     *  inside vendor/, where an application's config is never found.
     * @return self
     */
    public function load(?string $dir = null): self
    {
        $this->data = [];

        // 1. Load from system env vars
        foreach ($this->readSystemEnv() as $key => $val) {
            $this->data[$key] = $val;
        }

        // 2. Overlay $_ENV
        foreach ($_ENV as $key => $val) {
            if (is_string($key)) { // Prevents object/array pollution
                $this->data[$key] = $val;
            }
        }

        // 3. Overlay $_SERVER
        foreach ($_SERVER as $key => $val) {
            if (is_string($key) && is_string($val)) { // Prevents object/array pollution
                $this->data[$key] = $val;
            }
        }

        // 4. Overlay base .env file
        $resolvedDir = $dir ?? (getcwd() ?: __DIR__);
        $basePath = rtrim($resolvedDir, '/\\');
        $this->extendWithEnvFile("$basePath/.env");

        // 5. Detect environment (from merged so far, or detect)
        $env = $this->data['APP_ENV'] ?? null;
        $env = is_string($env) ? $env : $this->detectEnvironment();
        $this->data['APP_ENV'] = $env;

        // 6. Overlay .env.$env file
        $this->extendWithEnvFile("$basePath/.env.$env");

        // 7. Overlay base config.json
        $this->extendWithJsonFile("$basePath/config.json");

        // 8. Overlay config.$env.json
        $this->extendWithJsonFile("$basePath/config.$env.json");

        // 9. Overlay the optional local override file, if any -- always
        // last, so it wins over config.json/config.$env.json too.
        if ($this->localConfigFile !== null) {
            $this->extendWithJsonFile("$basePath/{$this->localConfigFile}");
        }

        return $this;
    }

    /**
     * Adds/overrides a variable in storage only.
     *
     * @return self
     */
    public function set(string $key, string $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Get variable from storage, or default if not present.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (count($this->data) === 0) {
            $this->load();
        }

        return $this->data[$key] ?? $default;
    }

    /**
     * Checks if the current environment is "development".
     */
    public function isDev(): bool
    {
        if (count($this->data) === 0) {
            $this->load();
        }

        return ($this->data['APP_ENV'] ?? 'production') === 'development';
    }

    // --- Private helpers ---

    /**
     * Returns every system env var as an array -- the no-argument form of
     * getenv() always returns array (never false; that's only possible
     * for the single-argument "look up one var" form), so there's nothing
     * to fall back from.
     *
     * @return array<string, string>
     */
    private function readSystemEnv(): array
    {
        return getenv();
    }

    /**
     * Parse and extend storage with a given .env file (does not overwrite existing keys unless force).
     */
    private function extendWithEnvFile(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        $handle = @fopen($file, 'r');
        // @codeCoverageIgnoreStart
        // Only reachable via a race (deleted/permissions changed between
        // is_file() and fopen()) that can't be reliably reproduced cross
        // platform, especially under Windows ACLs.
        if (!$handle) {
            return;
        }
        // @codeCoverageIgnoreEnd

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $equals = strpos($line, '=');
            if ($equals === false) {
                continue;
            }

            $key = trim(substr($line, 0, $equals));
            $val = trim(substr($line, $equals + 1), " \t\n\r\0\x0B\"'");

            $this->data[$key] = $val;
        }

        fclose($handle);
    }

    /**
     * Parse and extend storage with a given JSON file -- a flat {"KEY":
     * value, ...} object, keys overlaid the same way .env lines are.
     * Unlike .env values (always strings), a JSON value keeps its decoded
     * type (bool/int/float/array/null), since get() already returns mixed.
     * Missing, unreadable, malformed, or non-object JSON is silently
     * skipped -- same tolerant contract as extendWithEnvFile().
     */
    private function extendWithJsonFile(string $file): void
    {
        if (!is_file($file)) {
            return;
        }

        $contents = @file_get_contents($file);
        // @codeCoverageIgnoreStart
        // Same unreproducible race as extendWithEnvFile()'s fopen() guard.
        if ($contents === false) {
            return;
        }
        // @codeCoverageIgnoreEnd

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            return;
        }

        foreach ($decoded as $key => $val) {
            if (is_string($key)) {
                $this->data[$key] = $val;
            }
        }
    }

    /**
     * Determines current environment as a fallback.
     *
     * @return string
     */
    private function detectEnvironment(): string
    {
        /** @var string|null $result */
        static $result;
        if ($result !== null) {
            return $result;
        }
        if (php_sapi_name() === 'cli' || php_sapi_name() === 'cli-server') {
            return $result = 'development';
        }
        // @codeCoverageIgnoreStart
        $serverName = $_SERVER['SERVER_NAME'] ?? null;
        if (is_string($serverName) && $serverName !== '' && str_contains($serverName, 'localhost')) {
            return $result = 'development';
        }
        if (extension_loaded('xdebug')) {
            return $result = 'development';
        }
        return $result = 'production';
        // @codeCoverageIgnoreEnd
    }
}
