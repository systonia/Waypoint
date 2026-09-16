<?php

namespace Waypoint\Exceptions;

use Throwable;
use Waypoint\Enums\Message;

/**
 * Thrown when input validation fails. Extends HttpException like every
 * other HTTP-facing exception in this codebase -- title fixed at
 * "Validation failed", $message below becomes 'detail'. Adds one RFC 9457
 * extension member on top of the five standard ones (see
 * toProblemDetails()): 'errors', the same field-level map getErrors()
 * already exposed before HttpException existed.
 */
class ValidationException extends HttpException
{
    /**
     * Field name => error message, except for a collection body
     * (#[Body(of: ...)]), where a field's value is itself a nested
     * array<string,string> of per-index errors -- see Router::
     * buildMethodArguments()'s 'BodyCollection' case.
     *
     * @var array<string, mixed>
     */
    protected array $errors = [];

    /**
     * @param string|Message $message
     * @param array<string, mixed> $errors
     * @param int $code
     * @param Throwable|null $previous
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

    /**
     * Returns validation error details.
     *
     * @return array<string, mixed>
     */
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
