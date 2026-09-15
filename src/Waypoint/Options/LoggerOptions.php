<?php

namespace Waypoint\Options;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Log\LogLevel;

/**
 * The set of PSR-3 loggers Waypoint's Logger dispatches to. Resolved through
 * the container like any other Options class -- configure it via
 * App::configure():
 *
 *   $app->configure(function (LoggerOptions $opts) {
 *       $opts->add($myPsr3Logger, [LogLevel::ERROR, LogLevel::CRITICAL]);
 *       $opts->addMono('app', new StreamHandler('php://stdout'), [LogLevel::INFO]);
 *   });
 *
 * A LoggerOptions instance also doubles as the small {levels, name}
 * descriptor add() itself accepts as its $options argument -- that's what
 * $levels/$name and the constructor below are for; they play no part in
 * the app-wide $loggers list a configured instance accumulates.
 */
class LoggerOptions
{
    /**
     * @var array<int, array{logger: LoggerInterface, levels: array<string, bool>, name: string|null}>
     */
    private array $loggers = [];

    /**
     * List of PSR-3 log levels accepted by this logger, e.g. ['info','error']
     *
     * @var string[]
     */
    public array $levels = [];

    /**
     * Channel or name (optional)
     *
     * @var string|null
     */
    public ?string $name = null;

    /**
     * @param string[] $levels
     * @param string|null $name
     */
    public function __construct(array $levels = [], ?string $name = null)
    {
        $this->levels = $levels;
        $this->name = $name;
    }

    /**
     * Add one or more loggers (LoggerInterface), with flexible options:
     * - options can be LoggerOptions, string (level), array (levels), or assoc array.
     *
     * @param LoggerInterface|array<int, mixed> $logger A single logger, or
     *  a plain array of them -- element type left as `mixed`, since
     *  nothing actually constrains an array's element types; any element
     *  that isn't really a LoggerInterface is silently skipped below (the
     *  instanceof check), not assumed away.
     * @param LoggerOptions|string|array<int|string, mixed>|null $options
     * @return self
     */
    public function add(LoggerInterface|array $logger, $options = null): self
    {
        $loggers = is_array($logger) ? $logger : [$logger];

        // Normalize options to a LoggerOptions describing *this* logger
        if ($options instanceof self) {
            $opts = $options;
        } elseif (is_string($options)) {
            $opts = new self([$options]);
        } elseif (is_array($options) && array_keys($options) === range(0, count($options) - 1)) {
            // Numeric array = list of levels
            $opts = new self(self::toStringList($options));
        } elseif (is_array($options)) {
            // Assoc array
            $levels = self::toStringList($options['levels'] ?? []);
            $name = $options['name'] ?? null;
            $opts = new self($levels, is_string($name) ? $name : null);
        } else {
            $opts = new self();
        }

        // Convert levels to associative map for O(1) lookup
        $levelMap = [];
        foreach (
            $opts->levels ?: [
                LogLevel::EMERGENCY,
                LogLevel::ALERT,
                LogLevel::CRITICAL,
                LogLevel::ERROR,
                LogLevel::WARNING,
                LogLevel::NOTICE,
                LogLevel::INFO,
                LogLevel::DEBUG
            ] as $lvl
        ) {
            $levelMap[$lvl] = true;
        }

        foreach ($loggers as $lg) {
            if ($lg instanceof LoggerInterface) {
                $this->loggers[] = [
                    'logger' => $lg,
                    'levels' => $levelMap,
                    'name' => $opts->name,
                ];
            }
        }

        return $this;
    }

    /**
     * Narrows an arbitrary value to a list of strings, dropping any
     * non-string entry -- $options is fundamentally untyped in add() (see
     * its own docblock), but $levels is always meant to be PSR-3 level
     * strings.
     *
     * @return string[]
     */
    private static function toStringList(mixed $value): array
    {
        if (!is_array($value)) {
            // @codeCoverageIgnoreStart
            // No real or tested caller passes a non-array 'levels'.
            return [];
            // @codeCoverageIgnoreEnd
        }
        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * Add a Monolog logger for a channel and handler, with level filtering.
     *
     * @param string $channel
     * @param mixed $handler
     * @param string[] $levels Accept only these log levels for this logger (default: all)
     * @return self
     */
    public function addMono(string $channel, $handler, array $levels = []): self
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

    /**
     * Return all registered loggers. If none, a NullLogger accepting every
     * level, so Logger::log() always has something to dispatch to.
     *
     * @return array<int, array{logger: LoggerInterface, levels: array<string, bool>, name: string|null}>
     */
    public function getLoggers(): array
    {
        if (empty($this->loggers)) {
            return [
                [
                    'logger' => new NullLogger(),
                    'levels' => [
                        LogLevel::EMERGENCY => true,
                        LogLevel::ALERT => true,
                        LogLevel::CRITICAL => true,
                        LogLevel::ERROR => true,
                        LogLevel::WARNING => true,
                        LogLevel::NOTICE => true,
                        LogLevel::INFO => true,
                        LogLevel::DEBUG => true,
                    ],
                    'name' => null
                ]
            ];
        }
        return $this->loggers;
    }
}
