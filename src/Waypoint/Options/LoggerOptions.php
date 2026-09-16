<?php

namespace Waypoint\Options;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Log\LogLevel;
use Waypoint\Support\Arr;

/**
 * The PSR-3 loggers Waypoint\Logger fans out to, each with the levels it
 * accepts: `$opts->add($logger, [LogLevel::ERROR])`. An instance also serves
 * as the small {levels, name} descriptor add() accepts as $options.
 */
class LoggerOptions
{
    private const ALL_LEVELS = [
        LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR,
        LogLevel::WARNING, LogLevel::NOTICE, LogLevel::INFO, LogLevel::DEBUG,
    ];

    /** @var array<int, array{logger: LoggerInterface, levels: array<string, bool>, name: string|null}> */
    private array $loggers = [];

    /** @var string[] Accepted levels when used as a descriptor; empty means all. */
    public array $levels = [];

    /** Channel name when used as a descriptor. */
    public ?string $name = null;

    /** @param string[] $levels */
    public function __construct(array $levels = [], ?string $name = null)
    {
        $this->levels = $levels;
        $this->name = $name;
    }

    /**
     * @param LoggerInterface|array<int, mixed> $logger One logger or a list (non-loggers are skipped).
     * @param LoggerOptions|string|array<int|string, mixed>|null $options A descriptor, one level, a list of levels, or {levels, name}.
     */
    public function add(LoggerInterface|array $logger, $options = null): self
    {
        $opts = match (true) {
            $options instanceof self => $options,
            is_string($options) => new self([$options]),
            is_array($options) && array_is_list($options) => new self(Arr::stringList($options)),
            is_array($options) => new self(Arr::stringList($options['levels'] ?? []), is_string($options['name'] ?? null) ? $options['name'] : null),
            default => new self(),
        };
        $levelMap = array_fill_keys($opts->levels ?: self::ALL_LEVELS, true);

        foreach (is_array($logger) ? $logger : [$logger] as $lg) {
            if ($lg instanceof LoggerInterface) {
                $this->loggers[] = ['logger' => $lg, 'levels' => $levelMap, 'name' => $opts->name];
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
            $this->add($logger, new self($levels, $channel));
        }
        return $this;
    }

    /** @return array<int, array{logger: LoggerInterface, levels: array<string, bool>, name: string|null}> A NullLogger accepting everything when none were added. */
    public function getLoggers(): array
    {
        return $this->loggers ?: [['logger' => new NullLogger(), 'levels' => array_fill_keys(self::ALL_LEVELS, true), 'name' => null]];
    }
}
