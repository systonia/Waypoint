<?php

namespace Waypoint\Tests\EndToEnd;

/**
 * The whole live suite once more under Tenma, but through PSR-15: Tenma's request becomes a PSR-7 one
 * (tenma/psr), Waypoint\Psr\Handler turns it into Waypoint's and back. WebSocket and stream included.
 */
final class TenmaPsrLiveTest extends TenmaLiveTest
{
    public static function setUpBeforeClass(): void
    {
        putenv('E2E_PSR=1');
        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        putenv('E2E_PSR');
    }
}
