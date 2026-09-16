<?php

namespace Waypoint\Exceptions;

use Throwable;
use Psr\Container\NotFoundExceptionInterface;
use Waypoint\Enums\Message;

/** HTTP 404 "Not Found": $message becomes the RFC 9457 'detail', $code overrides the status. Also thrown by Container::get() for a missing service, hence the PSR NotFoundExceptionInterface. */
class NotFoundException extends HttpException implements NotFoundExceptionInterface
{
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
