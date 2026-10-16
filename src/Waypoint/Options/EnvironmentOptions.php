<?php

namespace Waypoint\Options;

use Waypoint\Support\Arr;

/**
 * The env key/values Waypoint\Environment reads, each layer overriding the one before: the committed defaults
 * config.json and config.$APP_ENV.json, then .env and .env.$APP_ENV, then the real process environment (system
 * env, $_ENV, $_SERVER), then $localConfigFile, then set(). get()/isDev() load lazily on first use; configure
 * explicitly only to point load() at another directory.
 */
class EnvironmentOptions
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, string> What set() put in: it stays over whatever a later load() reads. */
    private array $overrides = [];

    private bool $loaded = false;

    /** A gitignored, per-developer JSON override read last (so it wins over everything); null disables it. */
    public ?string $localConfigFile = 'config.local.json';

    /** @param string|null $dir Where .env/config files live; defaults to getcwd() (the project root under a normal invocation, never vendor/). */
    public function load(?string $dir = null): self
    {
        $this->loaded = true;
        $process = [];
        // Without the request's headers: HTTP_* comes from the client (HTTP_PROXY, httpoxy). FPM puts them into
        // $_ENV as well as $_SERVER, and a CGI server into the process environment.
        foreach ([getenv(), $_ENV, $_SERVER] as $source) {
            foreach (Arr::stringMap($source) as $key => $val) {
                if (!str_starts_with($key, 'HTTP_')) {
                    $process[$key] = $val;
                }
            }
        }

        $base = rtrim($dir ?? (getcwd() ?: __DIR__), '/\\');
        $dotenv = self::readEnvFile("$base/.env");

        $env = $this->overrides['APP_ENV'] ?? $process['APP_ENV'] ?? $dotenv['APP_ENV'] ?? null;
        $env = is_string($env) ? $env : $this->detectEnvironment();

        // Committed JSON is the default; .env files, then the real environment, then the local JSON override it
        $this->data = array_replace(
            self::readJsonFile("$base/config.json"),
            self::readJsonFile("$base/config.$env.json"),
            $dotenv,
            self::readEnvFile("$base/.env.$env"),
            $process,
            $this->localConfigFile !== null ? self::readJsonFile("$base/{$this->localConfigFile}") : [],
            $this->overrides,
            ['APP_ENV' => $env],
        );
        return $this;
    }

    public function set(string $key, string $value): self
    {
        $this->overrides[$key] = $value;
        $this->data[$key] = $value;
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->loaded) {
            $this->load();
        }
        return $this->data[$key] ?? $default;
    }

    public function isDev(): bool
    {
        if (!$this->loaded) {
            $this->load();
        }
        return ($this->data['APP_ENV'] ?? 'production') === 'development';
    }

    /**
     * KEY=value lines; '#' comments and blank lines skipped, a leading `export ` dropped. A quoted value is what stands
     * between its quotes; an unquoted one ends at a ' #' comment. Missing file is fine.
     * @return array<string, string>
     */
    private static function readEnvFile(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $handle = @fopen($file, 'r');
        if (!$handle) {
            // @codeCoverageIgnoreStart
            // only a delete/permission race after is_file().
            return [];
            // @codeCoverageIgnoreEnd
        }
        $values = [];
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            $equals = strpos($line, '=');
            if ($line === '' || $line[0] === '#' || $equals === false) {
                continue;
            }
            $key = trim(substr($line, 0, $equals));
            $key = str_starts_with($key, 'export ') ? trim(substr($key, 7)) : $key;
            $value = trim(substr($line, $equals + 1));
            $quote = $value[0] ?? '';
            $end = $quote === '"' || $quote === "'" ? strpos($value, $quote, 1) : false;
            $values[$key] = $end !== false ? substr($value, 1, $end - 1) : (string) preg_replace('/\s+#.*$/', '', $value);
        }
        fclose($handle);
        return $values;
    }

    /**
     * A flat JSON object; values keep their decoded type. Missing or malformed is silently skipped.
     * @return array<string, mixed>
     */
    private static function readJsonFile(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $contents = @file_get_contents($file);
        if ($contents === false) {
            // @codeCoverageIgnoreStart
            return [];
            // @codeCoverageIgnoreEnd
        }
        return Arr::stringKeyed(json_decode($contents, true));
    }

    private function detectEnvironment(): string
    {
        return self::environmentFor(php_sapi_name() ?: '', get_cfg_var('tenma.watch') === '1');
    }

    /**
     * 'development' for the CLI, PHP's built-in server and `tenma --watch`; every other server, Tenma without
     * --watch included, is 'production' until APP_ENV says otherwise. Never from the request: Apache and Caddy
     * take SERVER_NAME from the client's Host header.
     * @internal
     */
    public static function environmentFor(string $sapi, bool $watch = false): string
    {
        return $sapi === 'cli' || $sapi === 'cli-server' || ($sapi === 'tenma' && $watch) ? 'development' : 'production';
    }
}
