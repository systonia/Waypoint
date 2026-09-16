<?php

namespace Waypoint\Exceptions;

use Throwable;
use Waypoint\Enums\Message;

/**
 * HTTP 403 -- see HttpException for the RFC 9457 Problem Details fields
 * this maps to (title fixed at "Forbidden", $message below becomes
 * 'detail'). Constructor signature unchanged from before HttpException
 * existed: $code still overrides the default 403 status per throw site,
 * exactly like $message still overrides the default title-only detail.
 */
class ForbiddenException extends HttpException
{
    /**
     * @param string|null $message The specific reason for this particular
     *  403 (RFC 9457 'detail'); omit for a bare "Forbidden" with nothing
     *  more specific to add.
     * @param int $code Overrides the default 403 status, if this throw
     *  site needs a different one.
     * @param Throwable|null $previous
     */
    public function __construct(?string $message = null, int $code = 403, ?Throwable $previous = null)
    {
        parent::__construct(
            statusCode: $code,
            title: Message::Forbidden->value,
            detail: $message,
            previous: $previous
        );
    }
}
