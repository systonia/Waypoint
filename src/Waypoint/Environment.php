<?php

namespace Waypoint;

use Waypoint\Options\EnvironmentOptions;

/** #[Inject]-able facade over EnvironmentOptions (resolved live from the container, so a `new Environment()` always sees the configured one). */
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

    public function isDev(): bool
    {
        return Waypoint::getConfig(EnvironmentOptions::class)->isDev();
    }
}
