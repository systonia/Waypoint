<?php

namespace Waypoint\Exceptions;

use Throwable;
use Psr\Container\NotFoundExceptionInterface;
use Waypoint\Enums\Message;

/**
 * HTTP 404 -- see HttpException for the RFC 9457 Problem Details fields
 * this maps to (title fixed at "Not Found", $message below becomes
 * 'detail'). Constructor signature unchanged from before HttpException
 * existed: $code still overrides the default 404 status per throw site,
 * exactly like $message still overrides the default title-only detail.
 *
 * Still implements Psr\Container\NotFoundExceptionInterface too --
 * Container::get() throws this same class for "service not found" as
 * well as every genuinely HTTP-facing "resource not found" throw site
 * elsewhere in this codebase; that dual use predates HttpException and
 * is unrelated to it.
 */
class NotFoundException extends HttpException implements NotFoundExceptionInterface
{
    /**
     * @param string|null $message The specific reason for this particular
     *  404 (RFC 9457 'detail'); omit for a bare "Not Found" with nothing
     *  more specific to add.
     * @param int $code Overrides the default 404 status, if this throw
     *  site needs a different one.
     * @param Throwable|null $previous
     */
    public function __construct(?string $message = null, int $code = 404, ?Throwable $previous = null)
    {
        parent::__construct(
            statusCode: $code,
            title: Message::NotFound->value,
            detail: $message,
            previous: $previous
        );
    }
}
