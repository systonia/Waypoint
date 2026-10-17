<?php

namespace Waypoint\Tests\Unit\PHPStan;

use PHPUnit\Framework\TestCase;

final class AttributeTypesTest extends TestCase
{
    private static function analyse(bool $withExtension): string
    {
        $root = dirname(__DIR__, 3);
        $config = sys_get_temp_dir() . '/waypoint-phpstan-' . bin2hex(random_bytes(4)) . '.neon';
        file_put_contents($config, ($withExtension ? "includes:\n    - $root/extension.neon\n" : '') . "parameters:\n    level: 10\n");
        exec(escapeshellarg("$root/vendor/bin/phpstan") . ' analyse --no-progress --error-format=raw -c ' . escapeshellarg($config) . ' ' . escapeshellarg("$root/tests/Fixtures/PHPStan/AttributeTyped.php") . ' 2>&1', $output);
        unlink($config);
        return implode("\n", $output);
    }

    public function testReturnsAndListOfNameTheElementTypeForPhpstan(): void
    {
        $without = self::analyse(false);
        $this->assertStringContainsString('::typed() return type has no value type', $without);
        $this->assertStringContainsString('$names with no value type', $without);
        $this->assertStringContainsString('$tags type has no value type', $without);

        $with = self::analyse(true);
        $this->assertStringNotContainsString('::typed() return type', $with);
        $this->assertStringNotContainsString('$names', $with);
        $this->assertStringNotContainsString('$tags', $with);
        // Without an attribute PHPStan still asks
        $this->assertStringContainsString('untyped() return type has no value type', $with);
    }
}
