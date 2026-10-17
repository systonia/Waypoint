<?php

namespace Waypoint\Tests\Unit\PHPStan;

use PHPUnit\Framework\TestCase;

final class AttributeTypeRuleTest extends TestCase
{
    private static function analyse(): string
    {
        $root = dirname(__DIR__, 3);
        $config = sys_get_temp_dir() . '/waypoint-phpstan-' . bin2hex(random_bytes(4)) . '.neon';
        file_put_contents($config, "includes:\n    - $root/extension.neon\nparameters:\n    level: 10\n");
        exec(escapeshellarg("$root/vendor/bin/phpstan") . ' analyse --no-progress --error-format=raw -c ' . escapeshellarg($config) . ' ' . escapeshellarg("$root/tests/Fixtures/PHPStan/AttributeChecked.php") . ' 2>&1', $output);
        unlink($config);
        return implode("\n", $output);
    }

    public function testAttributesGiveTheTypeAndCheckWhatGoesIn(): void
    {
        $output = self::analyse();
        $fixture = 'Waypoint\Tests\Fixtures\PHPStan\AttributeChecked';
        // Reads: #[ListOf] properties and #[Returns] calls have the attribute's type, nullable with the native type
        $this->assertStringContainsString(":50:Dumped type: list<$fixture>", $output);
        $this->assertStringContainsString(':51:Dumped type: array<string, int>', $output);
        $this->assertStringContainsString(':52:Dumped type: list<string>|null', $output);
        $this->assertStringContainsString(":53:Dumped type: list<$fixture>", $output);
        // A receiver typed only by PHPDoc (mixed natively) still gets the attribute's element type in a loop
        $this->assertStringContainsString(":76:Dumped type: $fixture", $output);
        // An attribute that does not narrow the native type leaves it alone
        $this->assertStringContainsString(':54:Dumped type: stdClass', $output);
        // An offset of the attribute's array has the element type, also where PHPStan narrows it
        $this->assertStringNotContainsString('::count()', $output);
        // Writes and returns that do not fit
        $this->assertStringContainsString(':39:Method ' . $fixture . '::wrongReturn() should return list<string>', $output);
        $this->assertStringContainsString(":65:Property $fixture::\$children takes list<$fixture>", $output);
        $this->assertStringContainsString(':67:Parameter $counts', $output);
        $this->assertStringContainsString(':68:Parameter $tags', $output);
        // Nothing else: every reported line besides the dumps is one of the four above
        $this->assertSame(4, preg_match_all('/AttributeChecked\.php:\d+:(?!Dumped type)/', $output));
    }
}
