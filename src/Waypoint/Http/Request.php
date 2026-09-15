<?php

namespace Waypoint\Http;

/**
 * Undocumented class
 */
class Request
{
    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $method;

    /**
     * Undocumented variable
     *
     * @var string
     */
    public string $path;

    /**
     * @var array<string, string>
     */
    public array $headers = [];

    /**
     * @var array<string, mixed>
     */
    public array $query = [];

    /**
     * Keyed by string for a JSON object/form body, or by int for a JSON
     * list body (see #[Body(of: ...)] array-typed parameters, which bind a
     * top-level list) -- both are real, intentionally supported shapes.
     *
     * @var array<array-key, mixed>
     */
    public array $body = [];

    /**
     * Undocumented variable
     *
     * @var mixed
     */
    public mixed $jwt = null;

    /**
     * True if the client accepts a partial response (AJAX navigation)
     *
     * @var bool
     */
    public bool $acceptPartial = false;

    /**
     * Correlation id for this request: the incoming X-Request-Id header if
     * the client/upstream sent one (X-Correlation-Id as a fallback name),
     * otherwise a freshly generated UUID v4. Set once in capture() and
     * never modified afterwards (not declared `readonly` only because
     * __construct() is private and parameterless like every other
     * property here -- PHPStan requires a readonly property to be
     * assigned from the constructor itself, not a separate factory
     * method) -- Response::withRequestId() sends the same value back as
     * X-Request-ID, and Logger::log() (via RequestContext) stamps it onto
     * every log line for the duration of this request, so no call site
     * has to pass it around by hand.
     *
     * @var string
     */
    public string $id;

    /**
     * Undocumented function
     */
    private function __construct()
    {
    }

    /**
     * Capture the current HTTP request from globals.
     *
     * @param string|null $rawBody Override for the raw request body instead
     *  of reading php://input -- mainly so tests can exercise JSON body
     *  parsing, since php://input is always empty under a CLI test runner.
     * @return self
     */
    public static function capture(?string $rawBody = null): self
    {
        $req = new self();

        // $_SERVER values are typed mixed by PHPStan (it can't know what a
        // given SAPI actually put there) -- narrow explicitly rather than
        // assuming string, the same pattern as Response::maybeCompress().
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $req->method = is_string($method) ? $method : 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = is_string($uri) ? $uri : '/';
        $req->path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Headers (SAPI-agnostic)
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && str_starts_with($key, 'HTTP_')) {
                $name = str_replace(
                    ' ',
                    '-',
                    ucwords(strtolower(str_replace('_', ' ', substr($key, 5))))
                );
                $req->headers[$name] = $value;
            }
        }

        $req->id = self::resolveRequestId($req->headers);

        $req->query = self::toStringKeyedArray($_GET);

        // Parse JSON or form data, prefer JSON if present -- $data can be a
        // JSON object (string keys) or a JSON list (int keys), both valid
        // (see $body's own docblock), so unlike $query/$headers above this
        // isn't filtered down to string keys only.
        $raw = $rawBody ?? file_get_contents('php://input');
        $req->body = [];
        if ($raw && is_array($data = json_decode($raw, true))) {
            $req->body = $data;
        } elseif ($_POST) {
            $req->body = $_POST;
        }

        return $req;
    }

    /**
     * X-Request-Id if the client/upstream sent one, else X-Correlation-Id
     * as a fallback name, taken verbatim with no format validation -- an
     * upstream proxy's own (possibly non-UUID) id is exactly as valid a
     * correlation id as one we'd generate ourselves. Only when neither
     * header is present does this generate a fresh UUID v4.
     *
     * @param array<string, string> $headers Already-normalized (see the
     *  loop above), so both names are checked in their exact normalized
     *  casing.
     */
    private static function resolveRequestId(array $headers): string
    {
        foreach (['X-Request-Id', 'X-Correlation-Id'] as $name) {
            $value = $headers[$name] ?? '';
            if ($value !== '') {
                return $value;
            }
        }
        return self::generateUuidV4();
    }

    /** A random RFC 4122 version-4 UUID, e.g. "b3f1c2a0-....-....-....-............". */
    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant 10xx

        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /**
     * Narrows an arbitrary decoded/superglobal value to a string-keyed
     * array, dropping any non-string key -- $_GET/$_POST/json_decode()
     * are all typed with unknown key types by PHPStan even though a real
     * HTTP request's keys are always strings.
     *
     * @return array<string, mixed>
     */
    private static function toStringKeyedArray(mixed $value): array
    {
        if (!is_array($value)) {
            // @codeCoverageIgnoreStart
            // The only real caller passes $_GET, which PHP itself
            // guarantees is always an array.
            return [];
            // @codeCoverageIgnoreEnd
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }
        return $result;
    }

    /**
     * Undocumented function
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function query(string $key, $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Undocumented function
     *
     * @param string|null $key
     * @param mixed $default
     * @return ($key is null ? array<array-key, mixed> : mixed)
     */
    public function body(?string $key = null, $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    /**
     * Undocumented function
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, $default = null): mixed
    {
        return $this->body($key, $default);
    }
}
