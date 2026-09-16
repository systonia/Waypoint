<?php

namespace Waypoint;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Waypoint\Options\LoggerOptions;

/**
 * PSR-3 facade that fans out to every logger registered via
 * App::configure(function (LoggerOptions $opts) { $opts->add(...); }).
 *
 * Stateless on purpose: each call resolves the live LoggerOptions from the
 * container, so a `new Logger()` (what Container auto-instantiation and
 * #[Inject] produce) always sees whatever configure() set up.
 */
class Logger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @param mixed $level PSR-3 level string (anything else is ignored).
     * @param mixed[] $context 'request_id' is stamped in automatically (see RequestContext) unless already present.
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!is_int($level) && !is_string($level)) {
            // @codeCoverageIgnoreStart
            return;
            // @codeCoverageIgnoreEnd
        }
        if (!array_key_exists('request_id', $context)) {
            $requestId = Waypoint::getConfig(RequestContext::class)->getRequestId();
            if ($requestId !== null) {
                $context = ['request_id' => $requestId] + $context;
            }
        }
        foreach (Waypoint::getConfig(LoggerOptions::class)->getLoggers() as $entry) {
            if (isset($entry['levels'][$level])) {
                $entry['logger']->log($level, $message, $context);
            }
        }
    }
}
