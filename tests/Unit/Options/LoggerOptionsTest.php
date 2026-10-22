<?php

namespace Waypoint\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Monolog\Handler\TestHandler;
use Waypoint\Options\LoggerOptions;
use Waypoint\Tests\Fixtures\Support\DummyLogger;

final class LoggerOptionsTest extends TestCase
{
    private function dispatch(LoggerOptions $opts, string $level, string $message, array $context = []): void
    {
        foreach ($opts->getLoggers() as $entry) {
            if (isset($entry['levels'][$level])) {
                $entry['logger']->log($level, $message, $context);
            }
        }
    }

    public function testLoggerOnlyReceivesItsSubscribedLevels(): void
    {
        $logger = new DummyLogger();
        $opts = (new LoggerOptions())->add($logger, [LogLevel::ERROR, LogLevel::WARNING]);

        $this->dispatch($opts, LogLevel::ERROR, 'error msg', ['err' => 1]);
        $this->dispatch($opts, LogLevel::WARNING, 'warning msg');
        $this->dispatch($opts, LogLevel::INFO, 'info msg');
        $this->dispatch($opts, LogLevel::DEBUG, 'debug msg');

        $this->assertCount(2, $logger->logs);
        $this->assertEquals([LogLevel::ERROR, 'error msg', ['err' => 1]], $logger->logs[0]);
        $this->assertEquals([LogLevel::WARNING, 'warning msg', []], $logger->logs[1]);
    }

    public function testLoggerAcceptsAllLevelsByDefault(): void
    {
        $logger = new DummyLogger();
        $opts = (new LoggerOptions())->add($logger); // No levels specified => accepts all

        $this->dispatch($opts, LogLevel::INFO, 'info');
        $this->dispatch($opts, LogLevel::DEBUG, 'debug');

        $this->assertCount(2, $logger->logs);
        $this->assertEquals(LogLevel::INFO, $logger->logs[0][0]);
        $this->assertEquals(LogLevel::DEBUG, $logger->logs[1][0]);
    }

    public function testAddAcceptsAStringAsASingleLevel(): void
    {
        $logger = new DummyLogger();
        $opts = (new LoggerOptions())->add($logger, LogLevel::CRITICAL);

        $this->dispatch($opts, LogLevel::CRITICAL, 'boom');
        $this->dispatch($opts, LogLevel::INFO, 'ignored');

        $this->assertCount(1, $logger->logs);
        $this->assertSame(LogLevel::CRITICAL, $logger->logs[0][0]);
    }

    public function testAddMonoRegistersAMonologLoggerWithLevelFiltering(): void
    {
        $handler = new TestHandler();
        $opts = (new LoggerOptions())->addMono('app', $handler, [LogLevel::ERROR]);

        $this->dispatch($opts, LogLevel::ERROR, 'db is down');
        $this->dispatch($opts, LogLevel::INFO, 'ignored');

        $this->assertTrue($handler->hasErrorRecords());
        $this->assertFalse($handler->hasInfoRecords());
    }

    public function testAddMonoAcceptsAllLevelsByDefault(): void
    {
        $handler = new TestHandler();
        $opts = (new LoggerOptions())->addMono('app', $handler);

        $this->dispatch($opts, LogLevel::INFO, 'shows up');

        $this->assertTrue($handler->hasInfoRecords());
    }

    public function testAddAcceptsAnArrayOfLoggers(): void
    {
        $first = new DummyLogger();
        $second = new DummyLogger();
        $opts = (new LoggerOptions())->add([$first, $second]);

        $this->dispatch($opts, LogLevel::INFO, 'to both');

        $this->assertCount(1, $first->logs);
        $this->assertCount(1, $second->logs);
    }

    public function testAddIsChainable(): void
    {
        $opts = new LoggerOptions();
        $this->assertSame($opts, $opts->add(new DummyLogger()));
    }

    public function testAddMonoIsChainable(): void
    {
        $opts = new LoggerOptions();
        $this->assertSame($opts, $opts->addMono('app', new TestHandler()));
    }

    public function testGetLoggersFallsBackToTheErrorLogForErrorsWhenNoneAreRegistered(): void
    {
        $loggers = (new LoggerOptions())->getLoggers();

        $this->assertCount(1, $loggers);
        $this->assertInstanceOf(\Waypoint\Support\ErrorLogLogger::class, $loggers[0]['logger']);
        $this->assertSame([LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR], array_keys($loggers[0]['levels']));
    }
}
