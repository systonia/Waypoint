<?php

namespace Waypoint\Options;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Waypoint\Support\{Arr, ErrorLogLogger};

/**
 * The PSR-3 loggers Waypoint\Logger fans out to, each with the levels it
 * accepts: `$opts->add($logger, [LogLevel::ERROR])`.
 */
class LoggerOptions
{
    private const ALL_LEVELS = [
        LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR,
        LogLevel::WARNING, LogLevel::NOTICE, LogLevel::INFO, LogLevel::DEBUG,
    ];

    /** @var array<int, array{logger: LoggerInterface, levels: array<string, bool>}> */
    private array $loggers = [];

    /**
     * @param LoggerInterface|array<int, mixed> $logger One logger or a list (non-loggers are skipped).
     * @param string|array<mixed>|null $levels One level or a list of them; none for every level.
     */
    public function add(LoggerInterface|array $logger, string|array|null $levels = null): self
    {
        $levelMap = array_fill_keys(Arr::stringList((array) $levels) ?: self::ALL_LEVELS, true);

        foreach (is_array($logger) ? $logger : [$logger] as $lg) {
            if ($lg instanceof LoggerInterface) {
                $this->loggers[] = ['logger' => $lg, 'levels' => $levelMap];
            }
        }
        return $this;
    }

    /**
     * Adds a Monolog logger for $channel with $handler, if Monolog is installed.
     * @param string[] $levels
     */
    public function addMono(string $channel, mixed $handler, array $levels = []): self
    {
        if (class_exists('\Monolog\Logger')) {
            $logger = new \Monolog\Logger($channel);
            if ($handler instanceof \Monolog\Handler\HandlerInterface) {
                $logger->pushHandler($handler);
            }
            $this->add($logger, $levels);
        }
        return $this;
    }

    /**
     * @return array<int, array{logger: LoggerInterface, levels: array<string, bool>}> When none were
     *  added: PHP's error_log for errors and worse, so an error never vanishes unseen; `add(new NullLogger())` silences it.
     */
    public function getLoggers(): array
    {
        return $this->loggers ?: [[
            'logger' => new ErrorLogLogger(),
            'levels' => array_fill_keys([LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR], true),
        ]];
    }
}
