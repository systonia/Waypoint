<?php

namespace Waypoint\Exceptions;

use Throwable;
use Waypoint\Enums\Message;

/** HTTP 401 "Unauthorized": $message becomes the RFC 9457 'detail', $code overrides the status. */
class UnauthorizedException extends HttpException
{
    public function __construct(?string $message = null, int $code = 401, ?Throwable $previous = null)
    {
        parent::__construct(
            statusCode: $code,
            title: Message::Unauthorized->value,
            detail: $message,
            previous: $previous
        );
    }
}
