<?php

namespace Waypoint\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LogLevel;
use Waypoint\{Waypoint, Logger};
use Waypoint\Options\LoggerOptions;
use Waypoint\Tests\Fixtures\Support\DummyLogger;
use Waypoint\Tests\Fixtures\Services\ServiceWithInjectedLogger;

/**
 * Logger is a stateless service: every call resolves the live LoggerOptions
 * the container currently holds (Waypoint::getConfig()), rather than capturing
 * one at construction time -- so, unlike the old static Logger, these tests
 * need a real App/container (Waypoint::create()) to configure LoggerOptions
 * against. Pure add()/addMono()/getLoggers() behavior, which needs none of
 * that, is covered directly by LoggerOptionsTest instead.
 */
final class LoggerTest extends IntegrationTestCase
{
    public function testDispatchesOnlyToLevelsRegisteredOnTheConfiguredLoggerOptions(): void
    {
        $dummy = new DummyLogger();
        Waypoint::create()->configure(function (LoggerOptions $opts) use ($dummy) {
            $opts->add($dummy, [LogLevel::ERROR, LogLevel::WARNING]);
        });

        $logger = new Logger();
        $logger->error('error msg', ['err' => 1]);
        $logger->warning('warning msg');
        $logger->info('info msg');
        $logger->debug('debug msg');

        $this->assertCount(2, $dummy->logs);
        $this->assertEquals([LogLevel::ERROR, 'error msg', ['err' => 1]], $dummy->logs[0]);
        $this->assertEquals([LogLevel::WARNING, 'warning msg', []], $dummy->logs[1]);
    }

    public function testFallsBackToANullLoggerWhenNothingWasConfigured(): void
    {
        Waypoint::create();

        (new Logger())->info('nobody is listening');
        $this->addToAssertionCount(1); // reaching here without throwing is the assertion
    }

    #[DataProvider('psrLevelMethodProvider')]
    public function testEveryPsrConvenienceMethodDispatchesItsOwnLevel(string $method, string $level): void
    {
        $dummy = new DummyLogger();
        Waypoint::create()->configure(function (LoggerOptions $opts) use ($dummy) {
            $opts->add($dummy);
        });

        (new Logger())->{$method}('message');

        $this->assertSame($level, $dummy->logs[0][0]);
    }

    public static function psrLevelMethodProvider(): array
    {
        return [
            'emergency' => ['emergency', LogLevel::EMERGENCY],
            'alert' => ['alert', LogLevel::ALERT],
            'critical' => ['critical', LogLevel::CRITICAL],
            'error' => ['error', LogLevel::ERROR],
            'warning' => ['warning', LogLevel::WARNING],
            'notice' => ['notice', LogLevel::NOTICE],
            'info' => ['info', LogLevel::INFO],
            'debug' => ['debug', LogLevel::DEBUG],
        ];
    }

    public function testLoggerIsInjectableIntoAServiceAndReflectsTheConfiguredLoggerOptions(): void
    {
        $dummy = new DummyLogger();
        $app = Waypoint::create();
        $app->configure(function (LoggerOptions $opts) use ($dummy) {
            $opts->add($dummy, [LogLevel::ERROR]);
        });
        $app->attach([ServiceWithInjectedLogger::class]);

        $service = $app->getContainer()->get(ServiceWithInjectedLogger::class);
        $service->getLogger()?->error('injected and configured');

        $this->assertCount(1, $dummy->logs);
        $this->assertSame('injected and configured', $dummy->logs[0][1]);
    }

    public function testConfiguringLoggerOptionsAfterAttachStillReachesAnAlreadyInjectedLogger(): void
    {
        // Logger never captures LoggerOptions at construction time -- it
        // resolves the live one on every call -- so configure() order
        // relative to attach()/injection doesn't matter.
        $app = Waypoint::create();
        $app->attach([ServiceWithInjectedLogger::class]);

        $dummy = new DummyLogger();
        $app->configure(function (LoggerOptions $opts) use ($dummy) {
            $opts->add($dummy, [LogLevel::INFO]);
        });

        $service = $app->getContainer()->get(ServiceWithInjectedLogger::class);
        $service->getLogger()?->info('configured after the fact');

        $this->assertCount(1, $dummy->logs);
    }
}
