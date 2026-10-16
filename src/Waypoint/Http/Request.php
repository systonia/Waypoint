<?php

namespace Waypoint\Http;

use Waypoint\Support\{Arr, Uuid};

/**
 * The incoming HTTP request. capture() reads it from the superglobals (FPM, php -S); fromParts() builds it
 * from plain values, for a runtime that hands over requests itself instead of filling the superglobals.
 * Nothing after that reads the superglobals again.
 */
class Request
{
    public string $method;

    /**
     * The path the router matches, and so what middleware should check: slashes at either end trimmed, each
     * segment percent-decoded (/my%20file.txt is "/my file.txt", /%C3%BCber is "/über"). A segment that decodes to
     * a slash or a NUL leaves the whole path encoded, so an encoded slash never makes a new segment.
     */
    public string $path;

    /** Whether $path stayed percent-encoded (an encoded slash or NUL in it): the router decodes its placeholders then. */
    public bool $pathEncoded = false;

    /** @var array<string, string> Header names normalized to "X-Some-Header" casing. */
    public array $headers = [];

    /** @var array<string, mixed> */
    public array $query = [];

    /** @var array<array-key, mixed> A JSON object/form body (string keys) or a JSON list body (int keys, see #[Body(of: ...)]). */
    public array $body = [];

    /** Why a JSON body could not be decoded (json_last_error_msg()), null when it could or there was none: #[Body] answers 400 then. */
    public ?string $bodyError = null;

    /** @var array<string, string> The request cookies, name => value. */
    public array $cookies = [];

    /**
     * Uploaded files of a multipart/form-data request, nested as the field names say (avatar, photos[], doc[a]).
     * A file input left empty is not in here.
     *
     * @var array<array-key, mixed> UploadedFile or arrays of them
     */
    public array $files = [];

    /** Whether the request arrived over HTTPS; behind a proxy listed in ProxyOptions::$trusted, what its X-Forwarded-Proto says. */
    public bool $https = false;

    /** The client's address: the connection's ('' if the runtime doesn't say), or behind a proxy listed in ProxyOptions::$trusted, what its X-Forwarded-For says. */
    public string $ip = '';

    /** @var array<string, mixed>|null The decoded JWT payload, set by useJwt() when the request carried a valid token; #[Claims] binds it as a DTO. */
    public ?array $jwt = null;

    /** Whether $jwt came from the cookie (JWTOptions::$cookieName), which the browser sends on its own: a #[SkipCsrf] route ignores it. */
    public bool $jwtFromCookie = false;

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
     * @param array<string, string> $headers as sent, any casing (an underscore is not a dash)
     * @param array<string, string> $cookies
     * @param array<array-key, mixed>|null $form
     * @param array<array-key, mixed> $files UploadedFile, nested as the field names say
     * @param string $ip The address the connection came from
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
        string $ip = '',
    ): self {
        $req = new self();
        $req->method = strtoupper($method);
        // The part before ? and #: parse_url() gives up on valid paths such as /a:1 or //x
        $target = strstr($uri, '#', true) ?: $uri;
        $path = strstr($target, '?', true);
        $path = $path === false ? $target : $path;
        if (preg_match('#^[a-z][a-z0-9+.-]*://[^/]*(/.*)?$#i', $path, $absolute) === 1) {
            $path = $absolute[1] ?? '/';
        }
        $path = '/' . trim($path, '/');
        $decoded = self::decodedPath($path);
        $req->path = $decoded ?? $path;
        $req->pathEncoded = $decoded === null;
        foreach ($headers as $name => $value) {
            $req->headers[self::normalizeHeaderName($name)] = $value;
        }
        $req->id = self::resolveRequestId($req->headers);
        $queryString = strstr($target, '?');
        parse_str($queryString === false ? '' : substr($queryString, 1), $query);
        $req->query = Arr::stringKeyed($query);
        $req->cookies = $cookies;
        $req->https = $https;
        $req->files = $files;
        $req->ip = $ip;

        // JSON only when the client says so: a cross-site <form> can send a JSON-looking text/plain body
        // without a preflight, and that must not be read as a JSON request.
        $contentType = $req->headers['Content-Type'] ?? '';
        if (self::isJson($contentType)) {
            if ($body !== '') {
                $data = json_decode($body, true);
                if (is_array($data)) {
                    $req->body = $data;
                } elseif (json_last_error() !== JSON_ERROR_NONE) {
                    $req->bodyError = json_last_error_msg();
                }
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
                // CGI spells X-Forwarded-For as HTTP_X_FORWARDED_FOR
                $headers[str_replace('_', '-', substr($key, 5))] = $value;
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
            is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '',
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
            // A list stays a list when an empty input in the middle is left out, as under Tenma
            return array_is_list($name) ? array_values($nested) : $nested;
        }
        $error = is_numeric($error) ? (int) $error : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $tmpPath = is_string($tmpPath) ? $tmpPath : '';
        return new UploadedFile(
            is_string($name) ? $name : '',
            is_string($type) ? $type : '',
            is_numeric($size) ? (int) $size : 0,
            static fn (): string => (string) file_get_contents($tmpPath),
            static function (string $path) use ($tmpPath): void {
                if (!move_uploaded_file($tmpPath, $path)) {
                    throw new \RuntimeException("Could not move the upload to '$path'");
                }
            },
            $error,
        );
    }

    /** See $path; null where it stays encoded. */
    private static function decodedPath(string $path): ?string
    {
        $segments = array_map('rawurldecode', explode('/', $path));
        foreach ($segments as $segment) {
            if (str_contains($segment, '/') || str_contains($segment, "\0")) {
                return null;
            }
        }
        return implode('/', $segments);
    }

    /**
     * "content-type" -> "Content-Type". An underscore stays: X_Forwarded_For is another header than X-Forwarded-For,
     * and a client must not reach the one a proxy sets through it.
     */
    private static function normalizeHeaderName(string $name): string
    {
        return str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', $name))));
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
        return Uuid::v4();
    }

    /** A query string value, or $default when the key is missing. */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * The whole decoded body (JSON or form fields) without $key, else one field of it or $default.
     * @return ($key is null ? array<array-key, mixed> : mixed)
     */
    public function body(?string $key = null, mixed $default = null): mixed
    {
        return $key === null ? $this->body : ($this->body[$key] ?? $default);
    }

    /** The uploaded file of a field, named as in the form: "avatar", "doc[a]", "photos[1]"; null when there is none. */
    public function file(string $name): ?UploadedFile
    {
        $file = $this->files;
        foreach (self::fieldPath($name) ?? [''] as $key) {
            $file = is_array($file) && $key !== '' ? $file[$key] ?? null : null;
        }
        return $file instanceof UploadedFile ? $file : null;
    }

    /**
     * @internal A form field's name as keys, the way PHP nests it: "doc[a][]" is ['doc', 'a', ''], '' appending.
     * @return list<string>|null null for a name PHP wouldn't nest this way
     */
    public static function fieldPath(string $name): ?array
    {
        if (preg_match('/^([^\[\]]+)((?:\[[^\[\]]*\])*)$/', $name, $match) !== 1) {
            return null;
        }
        preg_match_all('/\[([^\[\]]*)\]/', $match[2], $keys);
        return [$match[1], ...$keys[1]];
    }

    /** Alias of body($key, $default). */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body($key, $default);
    }
}
