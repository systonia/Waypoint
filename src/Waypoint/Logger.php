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
            // @codeCoverageIgnoreStart
            // Every real caller (emergency()/alert()/.../debug() below) is
            // typed to pass a PSR-3 LogLevel string; this only guards
            // Logger::log() itself being called directly with something
            // else, which no code in this codebase does.
            return;
            // @codeCoverageIgnoreEnd
        }
        $context = self::withRequestId($context);
        foreach (Waypoint::getConfig(LoggerOptions::class)->getLoggers() as $entry) {
            if (isset($entry['levels'][$level])) {
                $entry['logger']->log($level, $message, $context);
            }
        }
    }

    /**
     * Context processor: stamps the current request's id (RequestContext,
     * set by App::handleHttp()) into 'request_id', unless the caller
     * already passed that key explicitly -- an explicit value always
     * wins, and outside of a request (a CLI task, or no request has run
     * yet) there's no id to stamp at all.
     *
     * @param mixed[] $context
     * @return mixed[]
     */
    private static function withRequestId(array $context): array
    {
        if (array_key_exists('request_id', $context)) {
            return $context;
        }
        $requestId = Waypoint::getConfig(RequestContext::class)->getRequestId();
        if ($requestId === null) {
            return $context;
        }
        return ['request_id' => $requestId] + $context;
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
