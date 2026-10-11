<?php

namespace Waypoint\Testing;

use Waypoint\{Csrf, JWT, Waypoint};
use Waypoint\Http\{Request, UploadedFile};
use Waypoint\Options\{CsrfOptions, JWTOptions};
use Waypoint\Support\Arr;

/**
 * Builds one request and runs it through App::handle() via Request::fromParts(): no superglobals, no
 * header()/echo. Cookies a test put into $_COOKIE and $_SERVER['HTTPS'] still apply, as defaults.
 * Every with*() returns $this.
 */
final class TestRequest
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @var array<array-key, mixed> */
    private array $body = [];

    private ?string $rawBody = null;

    /** @var array<string, string> */
    private array $cookies = [];

    private bool $csrf = false;

    private ?bool $https = null;

    /** @var array<array-key, mixed> */
    private array $files = [];

    public function __construct(private string $method, private string $uri)
    {
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** @param array<string, string> $headers */
    public function withHeaders(array $headers): self
    {
        $this->headers = [...$this->headers, ...$headers];
        return $this;
    }

    /**
     * Form fields ($_POST).
     * @param array<array-key, mixed> $body
     */
    public function withBody(array $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * A raw JSON body plus Content-Type: application/json.
     * @param array<array-key, mixed> $data
     */
    public function withJson(array $data): self
    {
        $this->rawBody = json_encode($data) ?: '{}';
        return $this->withHeader('Content-Type', 'application/json');
    }

    /** An uploaded file for the field $field ("photos[]" appends to a list); the body becomes the form's fields. */
    public function withFile(string $field, string $name, string $contents, string $type = 'application/octet-stream'): self
    {
        $file = UploadedFile::fromString($name, $type, $contents);
        if (str_ends_with($field, '[]')) {
            $list = $this->files[substr($field, 0, -2)] ?? [];
            $this->files[substr($field, 0, -2)] = [...(is_array($list) ? $list : []), $file];
        } else {
            $this->files[$field] = $file;
        }
        return $this->withHeader('Content-Type', 'multipart/form-data; boundary=waypoint-test');
    }

    public function withCookie(string $name, string $value): self
    {
        $this->cookies[$name] = $value;
        return $this;
    }

    /**
     * Sends a JWT for $payload as a bearer token (JWTOptions must be configured).
     * @param array<string, mixed> $payload
     */
    public function withJwt(array $payload): self
    {
        return $this->withHeader('Authorization', Waypoint::getConfig(JWTOptions::class)->tokenType . ' ' . JWT::encode(Arr::stringKeyed($payload)));
    }

    /** Carries a valid CSRF cookie + header pair (resolved at send(), after the app is configured); a no-op when CSRF isn't configured. */
    public function withCsrf(): self
    {
        $this->csrf = true;
        return $this;
    }

    /** A request that arrived over HTTPS (or, with false, explicitly not). */
    public function withHttps(bool $https = true): self
    {
        $this->https = $https;
        return $this;
    }

    /** What a browser sends for a top-level navigation (typed URL, link, reload). */
    public function asNavigation(): self
    {
        return $this->withHeader('Sec-Fetch-Mode', 'navigate');
    }

    public function send(): TestResponse
    {
        // Cookies a test set in $_COOKIE itself apply to every request; the builder's own to this one only
        $cookies = [...array_filter($_COOKIE, fn (mixed $value, mixed $name): bool => is_string($name) && is_string($value), ARRAY_FILTER_USE_BOTH), ...$this->cookies];
        $headers = $this->headers;
        if ($this->csrf) {
            $opts = Waypoint::getConfig(CsrfOptions::class);
            if (isset($opts->secret)) {
                // Minted after the cookies are in place: the token is bound to the session cookie this request carries
                $sessionCookie = Waypoint::getConfig(JWTOptions::class)->cookieName;
                $token = Csrf::generateToken($sessionCookie !== null ? $cookies[$sessionCookie] ?? '' : '');
                $cookies[$opts->cookieName] = $token;
                $headers[$opts->headerName] = $token;
            }
        }
        $https = $_SERVER['HTTPS'] ?? null;
        $request = Request::fromParts(
            $this->method,
            $this->uri,
            $headers,
            $this->rawBody ?? '',
            $cookies,
            $this->https ?? (is_string($https) && $https !== '' && strtolower($https) !== 'off'),
            $this->body,
            $this->files,
        );

        // Output a controller echoed itself lands in front of the body, as it would under a SAPI
        ob_start();
        try {
            $response = Waypoint::create()->handle($request);
        } finally {
            $stray = (string) ob_get_clean();
        }

        $lines = [];
        foreach ($response->getHeaders() as $name => $value) {
            $lines[] = "$name: $value";
        }
        foreach ($response->getCookies() as $cookie) {
            $lines[] = "Set-Cookie: $cookie";
        }
        return new TestResponse($response->getStatus(), $lines, $stray . $response->getBody());
    }
}
