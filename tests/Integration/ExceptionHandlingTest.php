<?php

namespace Waypoint\Tests\Integration;

use RuntimeException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LogLevel;
use Waypoint\Waypoint;
use Waypoint\Http\{Request, Response};
use Waypoint\Exceptions\ForbiddenException;
use Waypoint\Options\{LoggerOptions, EnvironmentOptions};
use Waypoint\Tests\Fixtures\Controllers\GuardedController;
use Waypoint\Tests\Fixtures\Support\DummyLogger;

final class ExceptionHandlingTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Waypoint::create()->attach([GuardedController::class]);
    }

    public function testForbiddenExceptionMapsTo403(): void
    {
        $output = $this->dispatch('GET', '/guarded/forbidden');
        $this->assertSame(['error' => 'Forbidden'], json_decode($output, true));
    }

    public function testUnauthorizedExceptionMapsTo401(): void
    {
        $output = $this->dispatch('GET', '/guarded/unauthorized');
        $this->assertSame(['error' => 'Unauthorized'], json_decode($output, true));
    }

    public function testSubclassOfARegisteredExceptionFallsBackToItsParentsHandler(): void
    {
        // No handler is registered for SpecificForbiddenException itself;
        // resolveExceptionHandler() must walk up to ForbiddenException.
        $output = $this->dispatch('GET', '/guarded/forbidden-subclass');
        $this->assertSame(['error' => 'Forbidden'], json_decode($output, true));
    }

    public function testExceptionMatchingARegisteredInterfaceUsesThatHandler(): void
    {
        // No default handler is registered under the interface itself (only
        // under the concrete NotFoundException class), so register one here
        // to exercise resolveExceptionHandler()'s interface-matching branch:
        // CustomNotFoundInterfaceException implements the interface without
        // extending NotFoundException, so only that branch can find it.
        Waypoint::create()->useExceptionHandler(
            NotFoundExceptionInterface::class,
            function (NotFoundExceptionInterface $e, Request $req, Response $res) {
                $res->status(404)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode(['error' => $e->getMessage()]))
                    ->send();
            }
        );

        $output = $this->dispatch('GET', '/guarded/not-found-interface');
        $this->assertSame(['error' => 'via interface'], json_decode($output, true));
    }

    public function testNotFoundExceptionMapsTo404WithItsOwnMessage(): void
    {
        $output = $this->dispatch('GET', '/guarded/missing');
        $this->assertSame(['error' => 'Widget not found'], json_decode($output, true));
    }

    public function testUnexpectedExceptionHidesItsMessageOutsideDevelopment(): void
    {
        // EnvironmentOptions::load() (triggered lazily on first use) defaults
        // APP_ENV to "development" under the CLI SAPI -- which is what this
        // whole suite runs under -- so we force "production" explicitly to
        // exercise the message-hiding branch.
        Waypoint::create()->configure(function (EnvironmentOptions $opts) {
            $opts->set('APP_ENV', 'production');
        });

        $output = $this->dispatch('GET', '/guarded/boom');
        $decoded = json_decode($output, true);

        $this->assertSame('Internal Server Error', $decoded['error']);
        $this->assertStringNotContainsString('unexpected failure', $output);
    }

    public function testUnexpectedExceptionExposesItsMessageInDevelopment(): void
    {
        Waypoint::create()->configure(function (EnvironmentOptions $opts) {
            $opts->set('APP_ENV', 'development');
        });

        $output = $this->dispatch('GET', '/guarded/boom');
        $decoded = json_decode($output, true);

        $this->assertSame('unexpected failure', $decoded['error']);
    }

    public function testUnexpectedExceptionIsLoggedThroughTheConfiguredLogger(): void
    {
        // Regression test: the default Throwable handler resolves Logger
        // via the container (Container::get(Logger::class)), rather than
        // calling a static Logger::error() -- must still reach whatever
        // LoggerOptions the app configured.
        $logger = new DummyLogger();
        Waypoint::create()->configure(function (LoggerOptions $opts) use ($logger) {
            $opts->add($logger, [LogLevel::ERROR]);
        });

        $this->dispatch('GET', '/guarded/boom');

        $this->assertCount(1, $logger->logs);
        $this->assertSame(LogLevel::ERROR, $logger->logs[0][0]);
        $this->assertSame('unexpected failure', $logger->logs[0][1]);
    }

    public function testCustomHandlerOverridesTheDefaultForThatExceptionType(): void
    {
        Waypoint::create()->useExceptionHandler(
            ForbiddenException::class,
            function (ForbiddenException $e, Request $req, Response $res) {
                $res->status(451)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode(['blocked' => true]))
                    ->send();
            }
        );

        $output = $this->dispatch('GET', '/guarded/forbidden');

        $this->assertSame(['blocked' => true], json_decode($output, true));
    }

    public function testCustomHandlerCanBeRegisteredForAnArbitraryExceptionClass(): void
    {
        Waypoint::create()->useExceptionHandler(
            RuntimeException::class,
            function (RuntimeException $e, Request $req, Response $res) {
                $res->status(418)
                    ->withHeader('Content-Type', 'application/json')
                    ->write(json_encode(['error' => $e->getMessage()]))
                    ->send();
            }
        );

        $output = $this->dispatch('GET', '/guarded/boom');

        $this->assertSame(['error' => 'unexpected failure'], json_decode($output, true));
    }
}
