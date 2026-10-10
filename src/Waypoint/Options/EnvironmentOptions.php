<?php

namespace Waypoint\Options;

use Waypoint\Support\Arr;

/**
 * The env key/values Waypoint\Environment reads: system env, $_ENV, $_SERVER,
 * .env, .env.$APP_ENV, config.json, config.$APP_ENV.json, then
 * $localConfigFile -- each overlaid on the last. get()/isDev() load lazily on
 * first use; configure explicitly only to point load() at another directory.
 */
class EnvironmentOptions
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** A gitignored, per-developer JSON override read last (so it wins over everything); null disables it. */
    public ?string $localConfigFile = 'config.local.json';

    /** @param string|null $dir Where .env/config files live; defaults to getcwd() (the project root under a normal invocation, never vendor/). */
    public function load(?string $dir = null): self
    {
        $this->data = getenv();
        foreach ($_ENV as $key => $val) {
            if (is_string($key)) {
                $this->data[$key] = $val;
            }
        }
        foreach (Arr::stringMap($_SERVER) as $key => $val) {
            $this->data[$key] = $val;
        }

        $base = rtrim($dir ?? (getcwd() ?: __DIR__), '/\\');
        $this->extendWithEnvFile("$base/.env");

        $env = $this->data['APP_ENV'] ?? null;
        $env = is_string($env) ? $env : $this->detectEnvironment();
        $this->data['APP_ENV'] = $env;

        $this->extendWithEnvFile("$base/.env.$env");
        $this->extendWithJsonFile("$base/config.json");
        $this->extendWithJsonFile("$base/config.$env.json");
        if ($this->localConfigFile !== null) {
            $this->extendWithJsonFile("$base/{$this->localConfigFile}");
        }
        return $this;
    }

    public function set(string $key, string $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->data === []) {
            $this->load();
        }
        return $this->data[$key] ?? $default;
    }

    public function isDev(): bool
    {
        if ($this->data === []) {
            $this->load();
        }
        return ($this->data['APP_ENV'] ?? 'production') === 'development';
    }

    /** KEY=value lines; '#' comments and blank lines skipped; surrounding quotes stripped. Missing file is fine. */
    private function extendWithEnvFile(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        $handle = @fopen($file, 'r');
        if (!$handle) {
            // @codeCoverageIgnoreStart
            // only a delete/permission race after is_file().
            return;
            // @codeCoverageIgnoreEnd
        }
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            $equals = strpos($line, '=');
            if ($line === '' || $line[0] === '#' || $equals === false) {
                continue;
            }
            $this->data[trim(substr($line, 0, $equals))] = trim(substr($line, $equals + 1), " \t\n\r\0\x0B\"'");
        }
        fclose($handle);
    }

    /** A flat JSON object; values keep their decoded type. Missing or malformed is silently skipped. */
    private function extendWithJsonFile(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        $contents = @file_get_contents($file);
        if ($contents === false) {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }
        foreach (Arr::stringKeyed(json_decode($contents, true)) as $key => $val) {
            $this->data[$key] = $val;
        }
    }

    private function detectEnvironment(): string
    {
        return self::environmentFor(php_sapi_name() ?: '', $_SERVER['SERVER_NAME'] ?? null);
    }

    /**
     * 'development' for the CLI, PHP's built-in server or a request to a local host name; everything else is 'production'.
     * The host must be local exactly: "localhost.example.com" is a public server, and so is any server with Xdebug loaded.
     * @internal
     */
    public static function environmentFor(string $sapi, mixed $serverName): string
    {
        if ($sapi === 'cli' || $sapi === 'cli-server') {
            return 'development';
        }
        $host = is_string($serverName) ? strtolower(trim($serverName, '[]')) : '';
        $local = $host === 'localhost' || str_ends_with($host, '.localhost') || $host === '127.0.0.1' || $host === '::1';
        return $local ? 'development' : 'production';
    }
}
