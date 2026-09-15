<?php

namespace Waypoint\Tests\Integration;

use RuntimeException;
use Waypoint\Waypoint;
use Waypoint\Tests\Fixtures\Managers\{MaintenanceManager, CleanupManager};
use Waypoint\Tests\Fixtures\Services\ExampleService;

final class TaskExecutionTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Waypoint::create()->attach([MaintenanceManager::class]);
    }

    public function testExecutesATaskByItsPrefixedFullName(): void
    {
        $result = Waypoint::create()->getRouter()->executeTask('maintenance:ping', ['ping'], 1);

        $this->assertSame('pong:1', $result);
    }

    public function testExecutesATaskByItsShortNameWhenUnambiguous(): void
    {
        $result = Waypoint::create()->getRouter()->executeTask('ping', ['ping'], 1);

        $this->assertSame('pong:1', $result);
    }

    public function testTaskManagerPropertiesAreInjected(): void
    {
        $result = Waypoint::create()->getRouter()->executeTask('maintenance:inject-check');

        $this->assertSame(ExampleService::class, $result);
    }

    public function testUnknownTaskNameThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Task 'does-not-exist' not found.");

        Waypoint::create()->getRouter()->executeTask('does-not-exist');
    }

    public function testAmbiguousShortTaskNameAcrossManagersThrows(): void
    {
        Waypoint::create()->attach([MaintenanceManager::class, CleanupManager::class]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Ambiguous task name 'ping'.");

        Waypoint::create()->getRouter()->executeTask('ping');
    }
}
