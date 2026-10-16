<?php

namespace Waypoint\Tests\Unit\PHPStan;

use PHPUnit\Framework\TestCase;

final class InjectedPropertiesTest extends TestCase
{
    private static function analyse(bool $withExtension): string
    {
        $root = dirname(__DIR__, 3);
        $config = sys_get_temp_dir() . '/waypoint-phpstan-' . bin2hex(random_bytes(4)) . '.neon';
        file_put_contents($config, ($withExtension ? "includes:\n    - $root/extension.neon\n" : '') . "parameters:\n    level: 10\n");
        exec(escapeshellarg("$root/vendor/bin/phpstan") . ' analyse --no-progress --error-format=raw -c ' . escapeshellarg($config) . ' ' . escapeshellarg("$root/tests/Fixtures/PHPStan/InjectedService.php") . ' 2>&1', $output);
        unlink($config);
        return implode("\n", $output);
    }

    public function testAnInjectedPropertyCountsAsWrittenWithTheExtension(): void
    {
        $this->assertStringContainsString('is never written, only read', self::analyse(false));
        $this->assertStringNotContainsString('InjectedService.php', self::analyse(true));
    }
}
