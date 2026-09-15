<?php

namespace Waypoint\Exceptions;

use Exception;
use Throwable;
use Waypoint\Enums\Message;

/**
 * Thrown when input validation fails.
 */
class ValidationException extends Exception
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
        parent::__construct($message, $code, $previous);
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
}
