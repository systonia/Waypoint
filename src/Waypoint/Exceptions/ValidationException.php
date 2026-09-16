<?php

namespace Waypoint\Exceptions;

use Throwable;
use Waypoint\Enums\Message;

/** HTTP 422: field-level validation errors, exposed as the RFC 9457 extension member 'errors'. */
class ValidationException extends HttpException
{
    /** @var array<string, mixed> field => message; for a #[Body(of: ...)] collection, index => {field => message}. */
    protected array $errors = [];

    /**
     * @param string|Message $message
     * @param array<string, mixed> $errors
     */
    public function __construct(
        $message = Message::ValidationFailed->value,
        array $errors = [],
        int $code = 422,
        ?Throwable $previous = null
    ) {
        if ($message instanceof Message) {
            $message = $message->value;
        }
        parent::__construct(
            statusCode: $code,
            title: Message::ValidationFailed->value,
            detail: $message,
            previous: $previous
        );
        $this->errors = $errors;
    }

    /** @return array<string, mixed> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function toProblemDetails(): array
    {
        return [...parent::toProblemDetails(), 'errors' => $this->errors];
    }
}
