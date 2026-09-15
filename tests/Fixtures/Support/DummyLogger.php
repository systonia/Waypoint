<?php

namespace Waypoint\Tests\Fixtures\Support;

use Psr\Log\LoggerInterface;
use Stringable;

class DummyLogger implements LoggerInterface
{
    /** @var array<int, array{0: string, 1: string, 2: array}> */
    public array $logs = [];

    public function log($level, $message, array $context = []): void
    {
        $this->logs[] = [$level, $message, $context];
    }

    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }
    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }
    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }
    public function error(Stringable|string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }
    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }
    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }
    public function info(Stringable|string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }
    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }
}
