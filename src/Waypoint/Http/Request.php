<?php

namespace Waypoint\Http;

use Waypoint\Support\Arr;

/** The incoming HTTP request, captured once from the superglobals by App::handleHttp(). */
class Request
{
    public string $method;
    public string $path;

    /** @var array<string, string> Header names normalized to "X-Some-Header" casing. */
    public array $headers = [];

    /** @var array<string, mixed> */
    public array $query = [];

    /** @var array<array-key, mixed> A JSON object/form body (string keys) or a JSON list body (int keys, see #[Body(of: ...)]). */
    public array $body = [];

    /** The decoded JWT payload, set by useJwt() when the request carried a valid token. */
    public mixed $jwt = null;

    /** The #[Body] DTO the matched route built (set before validation runs, so present even when validation fails); null until routing has run. */
    public ?object $bodyDto = null;

    /** True when the client sent `X-Waypoint-Accept: partial` (a waypoint.js navigation): render the view without its layout. */
    public bool $acceptPartial = false;

    /** Correlation id: the incoming X-Request-Id (or X-Correlation-Id), else a fresh UUID v4. Echoed back as X-Request-ID and stamped onto log lines. */
    public string $id;

    private function __construct()
    {
    }

    /** @param string|null $rawBody Overrides php://input (which is always empty under a CLI test runner). */
    public static function capture(?string $rawBody = null): self
    {
        $req = new self();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $req->method = is_string($method) ? $method : 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $req->path = parse_url(is_string($uri) ? $uri : '/', PHP_URL_PATH) ?: '/';

        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $req->headers[$name] = $value;
            }
        }

        $req->id = self::resolveRequestId($req->headers);
        $req->acceptPartial = strcasecmp($req->headers['X-Waypoint-Accept'] ?? '', 'partial') === 0;
        $req->query = Arr::stringKeyed($_GET);

        // JSON wins over form data when both could apply.
        $raw = $rawBody ?? file_get_contents('php://input');
        if ($raw && is_array($data = json_decode($raw, true))) {
            $req->body = $data;
        } elseif ($_POST) {
            $req->body = $_POST;
        }

        return $req;
    }

    /** @param array<string, string> $headers */
    private static function resolveRequestId(array $headers): string
    {
        foreach (['X-Request-Id', 'X-Correlation-Id'] as $name) {
            if (($headers[$name] ?? '') !== '') {
                return $headers[$name];
            }
        }
        return self::generateUuidV4();
    }

    private static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return ($key is null ? array<array-key, mixed> : mixed) */
    public function body(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->body : ($this->body[$key] ?? $default);
    }

    /** Alias of body($key, $default). */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body($key, $default);
    }
}
