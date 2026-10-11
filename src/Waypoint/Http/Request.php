<?php

namespace Waypoint\Http;

use Waypoint\Support\Arr;

/**
 * The incoming HTTP request. capture() reads it from the superglobals (FPM, php -S); fromParts() builds it
 * from plain values, for a runtime that hands over requests itself instead of filling the superglobals.
 * Nothing after that reads the superglobals again.
 */
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

    /** @var array<string, string> The request cookies, name => value. */
    public array $cookies = [];

    /**
     * Uploaded files of a multipart/form-data request, nested as the field names say (avatar, photos[], doc[a]).
     * A file input left empty is not in here.
     *
     * @var array<array-key, mixed> UploadedFile or arrays of them
     */
    public array $files = [];

    /** Whether the request itself arrived over HTTPS; a reverse proxy's X-Forwarded-Proto is deliberately not trusted. */
    public bool $https = false;

    /** The decoded JWT payload, set by useJwt() when the request carried a valid token. */
    public mixed $jwt = null;

    /** The #[Body] DTO the matched route built (set before validation runs, so present even when validation fails); null until routing has run. */
    public ?object $bodyDto = null;


    /** Correlation id: the incoming X-Request-Id (or X-Correlation-Id), else a fresh UUID v4. Echoed back as X-Request-ID and stamped onto log lines. */
    public string $id;

    private function __construct()
    {
    }

    /**
     * A request from plain values. The query comes from $uri; the body is decoded as JSON when Content-Type
     * says so, else as a urlencoded form, unless $form already holds the parsed form fields (multipart).
     *
     * @param array<string, string> $headers any casing
     * @param array<string, string> $cookies
     * @param array<array-key, mixed>|null $form
     * @param array<array-key, mixed> $files UploadedFile, nested as the field names say
     */
    public static function fromParts(
        string $method,
        string $uri,
        array $headers = [],
        string $body = '',
        array $cookies = [],
        bool $https = false,
        ?array $form = null,
        array $files = [],
    ): self {
        $req = new self();
        $req->method = strtoupper($method);
        $req->path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($headers as $name => $value) {
            $req->headers[self::normalizeHeaderName($name)] = $value;
        }
        $req->id = self::resolveRequestId($req->headers);
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        $req->query = Arr::stringKeyed($query);
        $req->cookies = $cookies;
        $req->https = $https;
        $req->files = $files;

        // JSON only when the client says so: a cross-site <form> can send a JSON-looking text/plain body
        // without a preflight, and that must not be read as a JSON request.
        $contentType = $req->headers['Content-Type'] ?? '';
        if (self::isJson($contentType)) {
            if ($body !== '' && is_array($data = json_decode($body, true))) {
                $req->body = $data;
            }
        } elseif ($form !== null) {
            $req->body = $form;
        } elseif (preg_match('#^application/x-www-form-urlencoded\s*(;|$)#i', trim($contentType)) === 1) {
            parse_str($body, $fields);
            $req->body = $fields;
        }
        return $req;
    }

    /** @param string|null $rawBody Overrides php://input (which is always empty under a CLI test runner). */
    public static function capture(?string $rawBody = null): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && str_starts_with($key, 'HTTP_')) {
                $headers[substr($key, 5)] = $value;
            }
        }
        // The two request headers PHP puts into $_SERVER without the HTTP_ prefix
        foreach (['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'] as $key => $name) {
            $value = $_SERVER[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $headers[$name] = $value;
            }
        }
        // Some servers hand the Authorization header over only as $_SERVER['AUTHORIZATION']
        $authorization = $_SERVER['AUTHORIZATION'] ?? null;
        if (is_string($authorization) && !isset($headers['AUTHORIZATION'])) {
            $headers['Authorization'] = $authorization;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $https = $_SERVER['HTTPS'] ?? null;
        // php://input only for JSON: a form's fields are already in $_POST
        $body = self::isJson($headers['Content-Type'] ?? '') ? ($rawBody ?? (string) file_get_contents('php://input')) : '';
        $cookies = array_filter($_COOKIE, fn (mixed $value, mixed $name): bool => is_string($name) && is_string($value), ARRAY_FILTER_USE_BOTH);

        $req = self::fromParts(
            is_string($method) ? $method : 'GET',
            is_string($uri) ? $uri : '/',
            $headers,
            $body,
            $cookies,
            // IIS sets the literal 'off'
            is_string($https) && $https !== '' && strtolower($https) !== 'off',
            $_POST,
            self::uploads($_FILES),
        );
        // $_GET, not the query string again: a test or front controller may have set it on its own
        $req->query = Arr::stringKeyed($_GET);
        return $req;
    }

    /**
     * $_FILES turned around: PHP keeps name/type/tmp_name/error/size side by side, nested per field below each
     * of them; here every file is one UploadedFile at the field's own path. Empty file inputs are left out.
     *
     * @param array<array-key, mixed> $files
     * @return array<array-key, mixed>
     */
    private static function uploads(array $files): array
    {
        $uploads = [];
        foreach ($files as $field => $file) {
            if (is_array($file) && isset($file['name'], $file['type'], $file['tmp_name'], $file['error'], $file['size'])) {
                $upload = self::upload($file['name'], $file['type'], $file['tmp_name'], $file['error'], $file['size']);
                if ($upload !== null && $upload !== []) {
                    $uploads[$field] = $upload;
                }
            }
        }
        return $uploads;
    }

    /** @return UploadedFile|array<array-key, mixed>|null */
    private static function upload(mixed $name, mixed $type, mixed $tmpPath, mixed $error, mixed $size): UploadedFile|array|null
    {
        if (is_array($name)) {
            $nested = [];
            foreach ($name as $key => $value) {
                $upload = self::upload($value, is_array($type) ? $type[$key] ?? '' : '', is_array($tmpPath) ? $tmpPath[$key] ?? '' : '', is_array($error) ? $error[$key] ?? UPLOAD_ERR_NO_FILE : UPLOAD_ERR_NO_FILE, is_array($size) ? $size[$key] ?? 0 : 0);
                if ($upload !== null && $upload !== []) {
                    $nested[$key] = $upload;
                }
            }
            return $nested;
        }
        $error = is_numeric($error) ? (int) $error : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return UploadedFile::fromPath(is_string($name) ? $name : '', is_string($type) ? $type : '', is_numeric($size) ? (int) $size : 0, is_string($tmpPath) ? $tmpPath : '', $error);
    }

    /** "CONTENT_TYPE" or "content-type" -> "Content-Type". */
    private static function normalizeHeaderName(string $name): string
    {
        return str_replace(' ', '-', ucwords(strtolower(str_replace(['_', '-'], ' ', $name))));
    }

    /** application/json or any application/*+json, parameters (charset) allowed. */
    private static function isJson(string $contentType): bool
    {
        return preg_match('#^application/([a-z0-9.+-]+\+)?json\s*(;|$)#i', trim($contentType)) === 1;
    }

    /** @param array<string, string> $headers */
    private static function resolveRequestId(array $headers): string
    {
        foreach (['X-Request-Id', 'X-Correlation-Id'] as $name) {
            // Comes from anyone: only a short id of plain characters goes into logs and the response header
            if (preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $headers[$name] ?? '') === 1) {
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

    /** The uploaded file of a field ("avatar", or "doc.a" for doc[a]); null when there is none or it is a list. */
    public function file(string $name): ?UploadedFile
    {
        $file = $this->files;
        foreach (explode('.', $name) as $key) {
            $file = is_array($file) ? $file[$key] ?? null : null;
        }
        return $file instanceof UploadedFile ? $file : null;
    }

    /** Alias of body($key, $default). */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body($key, $default);
    }
}
