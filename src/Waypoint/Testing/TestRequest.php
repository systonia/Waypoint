<?php

namespace Waypoint\Testing;

use Waypoint\{Csrf, JWT, Waypoint};
use Waypoint\Options\{CsrfOptions, JWTOptions};
use Waypoint\Support\Arr;

/**
 * Builds one request and runs it through App::handleHttp() via the
 * superglobals, exactly as a SAPI would. Every with*() returns $this.
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

    /** What a browser sends for a top-level navigation (typed URL, link, reload). */
    public function asNavigation(): self
    {
        return $this->withHeader('Sec-Fetch-Mode', 'navigate');
    }

    public function send(): TestResponse
    {
        $_SERVER['REQUEST_METHOD'] = strtoupper($this->method);
        $_SERVER['REQUEST_URI'] = $this->uri;
        parse_str((string) parse_url($this->uri, PHP_URL_QUERY), $_GET);
        $_POST = $this->body;
        // Cookies a test set in $_COOKIE itself stay for every request; the builder's own apply to this one only.
        $testCookies = $_COOKIE;
        $_COOKIE = [...$testCookies, ...$this->cookies];
        if ($this->csrf) {
            $opts = Waypoint::getConfig(CsrfOptions::class);
            if (isset($opts->secret)) {
                // Minted after the cookies are in place: the token is bound to the session cookie this request carries
                $token = Csrf::generateToken();
                $_COOKIE[$opts->cookieName] = $token;
                $this->headers[$opts->headerName] = $token;
            }
        }
        foreach (array_keys($_SERVER) as $key) {
            if (str_starts_with($key, 'HTTP_')) {
                unset($_SERVER[$key]);
            }
        }
        unset($_SERVER['CONTENT_TYPE'], $_SERVER['CONTENT_LENGTH']);
        foreach ($this->headers as $name => $value) {
            // Like a real server: Content-Type/-Length go into $_SERVER without the HTTP_ prefix
            $key = strtoupper(str_replace('-', '_', $name));
            $_SERVER[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : "HTTP_$key"] = $value;
        }

        // PHP tracks sent headers process-wide under the CLI too; start clean so the response only reports its own.
        header_remove();
        ob_start();
        Waypoint::create()->handleHttp($this->rawBody);
        $body = (string) ob_get_clean();
        $_COOKIE = $testCookies;

        $raw = Arr::stringList(function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list());
        $status = http_response_code();
        return new TestResponse(is_int($status) ? $status : 200, $raw, $body);
    }
}
