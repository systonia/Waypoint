<?php

namespace Waypoint\Exceptions;

use Exception;
use Throwable;

/**
 * Base of every exception that maps to an RFC 9457 Problem Details response
 * (see App's default handlers). getMessage()/getCode() keep their usual
 * meaning (a summary, the HTTP status); $title/$detail/$statusCode are the
 * same information under their RFC names.
 */
class HttpException extends Exception
{
    private int $statusCode;
    private string $type;
    private string $title;
    private ?string $detail;
    private ?string $instance;

    /**
     * @param string $title What kind of problem this is ("Not Found"), stable across occurrences.
     * @param string|null $detail The specific reason for this occurrence, if any.
     * @param string $type A URI reference identifying the problem type; 'about:blank' when $title says it all.
     * @param string|null $instance A URI reference to this occurrence (e.g. the request path).
     */
    public function __construct(
        int $statusCode,
        string $title,
        ?string $detail = null,
        string $type = 'about:blank',
        ?string $instance = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($detail ?? $title, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->type = $type;
        $this->title = $title;
        $this->detail = $detail;
        $this->instance = $instance;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function getInstance(): ?string
    {
        return $this->instance;
    }

    /**

     * The RFC 9457 members as a json_encode()-ready array; a subclass adds its own extension members (ValidationException's 'errors').

     * @return array<string, mixed>

     */
    public function toProblemDetails(): array
    {
        $problem = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->statusCode,
        ];
        if ($this->detail !== null) {
            $problem['detail'] = $this->detail;
        }
        if ($this->instance !== null) {
            $problem['instance'] = $this->instance;
        }
        return $problem;
    }
}
