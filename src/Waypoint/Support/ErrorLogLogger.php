<?php

namespace Waypoint\Support;

use Psr\Log\AbstractLogger;
use Throwable;

/**
 * Where Waypoint's errors go while the app configured no logger: PHP's error_log, so stderr under Tenma and
 * php -S, the server's error log under FPM. One line per entry, the exception's class, message and place with it.
 */
final class ErrorLogLogger extends AbstractLogger
{
    /** @param mixed[] $context */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $line = '[' . (is_string($level) ? $level : 'log') . '] ' . $message;
        $exception = $context['exception'] ?? null;
        unset($context['exception']);
        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        if ($exception instanceof Throwable) {
            $line .= ' | ' . get_class($exception) . ': ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine();
        }
        error_log($line);
    }
}
