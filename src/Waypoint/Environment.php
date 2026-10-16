<?php

namespace Waypoint;

use RuntimeException;
use Waypoint\Options\EnvironmentOptions;

/**
 * #[Inject]-able facade over EnvironmentOptions (resolved live from the container, so a `new Environment()` always
 * sees the configured one). string()/int()/float()/bool() read a value as that type: without a default, a missing
 * key throws, naming it, and so does a value that isn't of the type.
 */
class Environment
{
    /** @param string|null $dir Directory holding .env/config files; defaults to getcwd(). */
    public function load(?string $dir = null): void
    {
        Waypoint::getConfig(EnvironmentOptions::class)->load($dir);
    }

    public function set(string $key, string $value): void
    {
        Waypoint::getConfig(EnvironmentOptions::class)->set($key, $value);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Waypoint::getConfig(EnvironmentOptions::class)->get($key, $default);
    }

    public function string(string $key, ?string $default = null): string
    {
        $value = $this->required($key, $default);
        if (!is_scalar($value)) {
            throw new RuntimeException("Environment value '$key' is not a string.");
        }
        return (string) $value;
    }

    public function int(string $key, ?int $default = null): int
    {
        $value = $this->required($key, $default);
        $int = is_int($value) ? $value : (is_string($value) ? filter_var(trim($value), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null);
        return $int ?? throw new RuntimeException("Environment value '$key' is not an integer.");
    }

    public function float(string $key, ?float $default = null): float
    {
        $value = $this->required($key, $default);
        $float = is_float($value) || is_int($value) ? (float) $value : (is_string($value) ? filter_var(trim($value), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE) : null);
        return $float ?? throw new RuntimeException("Environment value '$key' is not a number.");
    }

    /** "true"/"1"/"yes"/"on" are true, "false"/"0"/"no"/"off"/"" false (FILTER_VALIDATE_BOOLEAN); anything else throws. */
    public function bool(string $key, ?bool $default = null): bool
    {
        $value = $this->required($key, $default);
        $bool = is_bool($value) ? $value : (is_string($value) || is_int($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null);
        return $bool ?? throw new RuntimeException("Environment value '$key' is not a boolean.");
    }

    public function isDev(): bool
    {
        return Waypoint::getConfig(EnvironmentOptions::class)->isDev();
    }

    /** The value of $key, else $default, else an exception naming the key. */
    private function required(string $key, mixed $default): mixed
    {
        return $this->get($key) ?? $default ?? throw new RuntimeException("Environment value '$key' is not set.");
    }
}
