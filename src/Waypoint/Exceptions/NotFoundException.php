<?php

namespace Waypoint\Exceptions;

use Throwable;
use Psr\Container\NotFoundExceptionInterface;
use Waypoint\Enums\Message;

/** HTTP 404 "Not Found": $message becomes the RFC 9457 'detail', $code overrides the status. It implements PSR-11's NotFoundExceptionInterface for handlers registered on that interface; Container::get() throws ServiceNotFoundException. */
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
