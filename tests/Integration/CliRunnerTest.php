<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Tests\Fixtures\Managers\MaintenanceManager;

final class CliRunnerTest extends IntegrationTestCase
{
    private array $argvBackup;
    private int $argcBackup;

    protected function setUp(): void
    {
        parent::setUp();
        global $argv, $argc;
        $this->argvBackup = $argv;
        $this->argcBackup = $argc;
    }

    protected function tearDown(): void
    {
        global $argv, $argc;
        $argv = $this->argvBackup;
        $argc = $this->argcBackup;
        parent::tearDown();
    }

    private function withArgv(array $args): void
    {
        global $argv, $argc;
        $argv = $args;
        $argc = count($args);
    }

    public function testReturnsOneAndPrintsNothingWhenNoCommandGiven(): void
    {
        Waypoint::create()->attach([MaintenanceManager::class]);
        $this->withArgv(['console.php']);

        ob_start();
        $code = Waypoint::create()->runCli();
        $output = ob_get_clean();

        $this->assertSame(1, $code);
        $this->assertSame('', $output);
    }

    public function testExecutesTheNamedTaskAndEchoesItsResult(): void
    {
        Waypoint::create()->attach([MaintenanceManager::class]);
        $this->withArgv(['console.php', 'maintenance:ping', 'extra-arg']);

        ob_start();
        $code = Waypoint::create()->runCli();
        $output = ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertSame('pong:3', $output);
    }

    public function testReturnsOneWhenTheTaskIsUnknown(): void
    {
        Waypoint::create()->attach([MaintenanceManager::class]);
        $this->withArgv(['console.php', 'does-not-exist']);

        ob_start();
        $code = Waypoint::create()->runCli();
        ob_get_clean();

        $this->assertSame(1, $code);
    }
}
