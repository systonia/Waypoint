<?php

namespace Waypoint\Exceptions;

use Exception;
use Throwable;

/**
 * Base class for every exception that should map to a structured
 * RFC 9457 ("Problem Details for HTTP APIs") error response -- see
 * App::registerDefaultExceptionHandlers()'s HttpException::class handler,
 * which builds an application/problem+json response directly from these
 * fields for any subclass not caught by a more specific
 * useExceptionHandler() registration first.
 *
 * Deliberately still extends the plain \Exception, not some new
 * interface: getMessage()/getCode() keep meaning exactly what they
 * already did in this codebase everywhere else (a human-readable summary
 * and the intended HTTP status respectively -- getCode() and
 * getStatusCode() below are always the same value). $detail/$title/
 * $statusCode are that same information, just also available under their
 * RFC 9457 names/shape, so the default handler never has to parse
 * getMessage() to build one.
 */
class HttpException extends Exception
{
    private int $statusCode;
    private string $type;
    private string $title;
    private ?string $detail;
    private ?string $instance;

    /**
     * @param int $statusCode The HTTP status this exception maps to --
     *  becomes both the response's actual status and RFC 9457 'status'.
     * @param string $title A short, human-readable summary of *this kind*
     *  of problem (RFC 9457: "SHOULD NOT change from occurrence to
     *  occurrence"), e.g. "Not Found" -- not the specific reason for one
     *  particular request; that's $detail.
     * @param string|null $detail The specific explanation for *this*
     *  occurrence, e.g. "Widget with id 42 not found". Optional -- a
     *  generic problem (e.g. a bare 403) often has nothing more specific
     *  to say than $title already does, and RFC 9457 treats 'detail' as
     *  genuinely optional for exactly that reason.
     * @param string $type A URI reference identifying the problem type,
     *  ideally one a client could dereference for documentation.
     *  'about:blank' (RFC 9457's own default) when this problem has no
     *  more specific type of its own -- meaning "$title/$statusCode
     *  already say everything there is to say about it".
     * @param string|null $instance A URI reference to *this specific*
     *  occurrence (e.g. the request path). Left null unless a caller sets
     *  one -- App's default handler doesn't fill this in automatically
     *  (see its own doc for why).
     * @param Throwable|null $previous
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
     * The RFC 9457 Problem Details members for this exception, as a plain
     * array ready for json_encode() -- App's default HttpException
     * handler calls this directly to build the response body. A subclass
     * carrying extra structured data of its own (e.g.
     * ValidationException's field-level errors) overrides this to add
     * its own extension members alongside the five standard ones, which
     * RFC 9457 explicitly allows.
     *
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
