<?php

namespace Waypoint;

use Psr\Log\LogLevel;
use Waypoint\Options\LoggerOptions;

/**
 * Dispatches to whichever loggers the app registered via
 * App::configure(function (LoggerOptions $opts) { $opts->add(...); }).
 *
 * Deliberately stateless -- every call resolves the live LoggerOptions
 * the container currently holds (Waypoint::getConfig()) rather than capturing
 * one in its own constructor. That's what makes Logger freely
 * #[Inject]-able/container-resolvable: Container::get()'s auto-
 * instantiation path (see Container::isAutoInstantiable()) always calls
 * `new Logger()` with no arguments, so a constructor-captured LoggerOptions
 * would silently pin every injected Logger to a throwaway empty instance
 * instead of whatever configure() set up.
 */
class Logger
{
    /**
     * Generic log dispatch to all loggers accepting this level
     *
     * @param mixed $level Untyped to match Psr\Log\LoggerInterface::log()
     *  exactly -- PSR-3 itself never constrains it beyond "mixed".
     * @param string|\Stringable $message
     * @param mixed[] $context
     * @return void
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!is_int($level) && !is_string($level)) {
            return;
        }
        foreach (Waypoint::getConfig(LoggerOptions::class)->getLoggers() as $entry) {
            if (isset($entry['levels'][$level])) {
                $entry['logger']->log($level, $message, $context);
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     * @return void
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     * @return void
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     * @return void
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     * @return void
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     * @return void
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     * @return void
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     * @return void
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Undocumented function
     *
     * @param string|\Stringable $message
     * @param mixed[] $context
     * @return void
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }
}
