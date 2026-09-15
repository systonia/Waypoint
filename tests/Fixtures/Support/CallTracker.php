<?php

namespace Waypoint\Tests\Fixtures\Support;

/**
 * Shared recorder fixtures use to prove call order / whether they ran at all,
 * since Response exposes no header getters to inspect after the fact.
 */
class CallTracker
{
    public static array $calls = [];

    public static function record(string $name): void
    {
        self::$calls[] = $name;
    }

    public static function reset(): void
    {
        self::$calls = [];
    }
}
