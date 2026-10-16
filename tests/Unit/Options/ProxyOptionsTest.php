<?php

namespace Waypoint\Tests\Unit\Options;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Waypoint\Options\ProxyOptions;

final class ProxyOptionsTest extends TestCase
{
    public function testTakesAddressesAndRanges(): void
    {
        $opts = new ProxyOptions();
        $opts->trusted = ['127.0.0.1', '10.0.0.0/8', '::1', 'fd00::/128', '0.0.0.0/0'];

        $this->assertSame(['127.0.0.1', '10.0.0.0/8', '::1', 'fd00::/128', '0.0.0.0/0'], $opts->trusted);
    }

    public function testRefusesWhatIsNoAddressOrRange(): void
    {
        $opts = new ProxyOptions();
        foreach (['10.0.0.0/', '10.0.0.0/x', '10.0.0.0/33', '::1/129', '10.0.0.0/-1', 'localhost', ''] as $range) {
            try {
                $opts->trusted = [$range];
                $this->fail("'$range' was taken");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
