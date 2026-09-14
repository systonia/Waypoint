<?php

namespace Waypoint;

use Waypoint\Options\EnvironmentOptions;

/**
 * Reads env vars/`.env` files through whatever EnvironmentOptions the app
 * is using
 *
 * Deliberately stateless -- every call resolves the live EnvironmentOptions
 * the container currently holds (Waypoint::getConfig()) rather than capturing
 * one in its own constructor. That's what makes Environment freely
 * #[Inject]-able/container-resolvable: Container::get()'s auto-
 * instantiation path (see Container::isAutoInstantiable()) always calls
 * `new Environment()` with no arguments, so a constructor-captured
 * EnvironmentOptions would silently pin every injected Environment to a
 * throwaway empty instance instead of whatever configure() set up (or the
 * one get()/isDev() already lazily loaded on first use elsewhere).
 */
class Environment
{
    /**
     * Loads environment variables from system, then extends with server, .env files.
     *
     * @param string|null $dir Directory to look for .env/.env.$APP_ENV in.
     *  Defaults to the current working directory.
     */
    public function load(?string $dir = null): void
    {
        Waypoint::getConfig(EnvironmentOptions::class)->load($dir);
    }

    /**
     * Adds/overrides a variable in storage only.
     */
    public function set(string $key, string $value): void
    {
        Waypoint::getConfig(EnvironmentOptions::class)->set($key, $value);
    }

    /**
     * Get variable from storage, or default if not present.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Waypoint::getConfig(EnvironmentOptions::class)->get($key, $default);
    }

    public function isDev(): bool
    {
        return Waypoint::getConfig(EnvironmentOptions::class)->isDev();
    }
}
