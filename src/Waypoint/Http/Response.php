<?php

namespace Waypoint\Http;

/**
 * Represents an HTTP response.
 */
class Response
{
    /**
     * Undocumented variable
     *
     * @var integer
     */
    private int $status = 200;

    /**
     * Undocumented variable
     *
     * @var array
     */
    private array $headers = [];

    /**
     * Undocumented variable
     *
     * @var string
     */
    private string $body = '';

    /**
     * Raw "Set-Cookie" header values queued by withCookie()/withoutCookie(),
     * one entry per cookie. Kept separate from $headers (a plain {name =>
     * value} map, one value per name) since a response can set more than
     * one cookie at once -- each is sent as its own header line in send().
     *
     * @var string[]
     */
    private array $cookies = [];

    /**
     * Undocumented function
     *
     * @param string $name
     * @param string $value
     * @return self
     */
    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Queues a "Set-Cookie" header. HttpOnly and SameSite=Lax by default --
     * the safe defaults for an auth/session cookie -- since a plain value
     * you actually want readable from JS or sent cross-site is the
     * exception, not the rule.
     *
     * @param string $name
     * @param string $value
     * @param int|null $maxAge Lifetime in seconds; null (the default) omits
     *  Max-Age entirely, making it a session cookie the browser drops on
     *  its own when closed.
     * @param string $path
     * @param string|null $domain
     * @param bool $secure
     * @param bool $httpOnly
     * @param string|null $sameSite 'Lax', 'Strict', 'None', or null to omit
     *  the attribute entirely.
     * @return self
     */
    public function withCookie(
        string $name,
        string $value,
        ?int $maxAge = null,
        string $path = '/',
        ?string $domain = null,
        bool $secure = false,
        bool $httpOnly = true,
        ?string $sameSite = 'Lax'
    ): self {
        $this->cookies[] = self::formatCookie($name, $value, $maxAge, $path, $domain, $secure, $httpOnly, $sameSite);
        return $this;
    }

    /**
     * Queues a "Set-Cookie" that immediately expires $name (Max-Age=0),
     * clearing it client-side -- $path/$domain must match whatever the
     * cookie was originally set with, or the browser won't recognize it as
     * the same cookie to clear.
     *
     * @param string $name
     * @param string $path
     * @param string|null $domain
     * @return self
     */
    public function withoutCookie(string $name, string $path = '/', ?string $domain = null): self
    {
        return $this->withCookie($name, '', maxAge: 0, path: $path, domain: $domain);
    }

    /**
     * @param string $name
     * @param string $value
     * @param int|null $maxAge
     * @param string $path
     * @param string|null $domain
     * @param bool $secure
     * @param bool $httpOnly
     * @param string|null $sameSite
     * @return string
     */
    private static function formatCookie(
        string $name,
        string $value,
        ?int $maxAge,
        string $path,
        ?string $domain,
        bool $secure,
        bool $httpOnly,
        ?string $sameSite
    ): string {
        $parts = [rawurlencode($name) . '=' . rawurlencode($value)];

        if ($path !== '') {
            $parts[] = 'Path=' . $path;
        }
        if ($domain !== null) {
            $parts[] = 'Domain=' . $domain;
        }
        if ($maxAge !== null) {
            $parts[] = 'Max-Age=' . $maxAge;
        }
        if ($secure) {
            $parts[] = 'Secure';
        }
        if ($httpOnly) {
            $parts[] = 'HttpOnly';
        }
        if ($sameSite !== null) {
            $parts[] = 'SameSite=' . $sameSite;
        }

        return implode('; ', $parts);
    }

    /**
     * Undocumented function
     *
     * @param integer $code
     * @return self
     */
    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    /**
     * Undocumented function
     *
     * @param string $data
     * @return self
     */
    public function write(string $data): self
    {
        $this->body .= $data;
        return $this;
    }

    /**
     * Send headers and body to the client.
     *
     * @return void
     */
    public function send(): void
    {
        if (!headers_sent()) {
            // Set HTTP status code
            http_response_code($this->status);

            // Automatically add Content-Length header if not provided
            if (!isset($this->headers['Content-Length'])) {
                header('Content-Length: ' . strlen($this->body));
            }

            // Send all custom headers
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }

            // Each cookie as its own "Set-Cookie" header line -- false
            // (don't replace) so a second withCookie() call adds another
            // header instead of overwriting the first, the same way
            // multiple real Set-Cookie headers are meant to stack.
            foreach ($this->cookies as $cookie) {
                header('Set-Cookie: ' . $cookie, false);
            }
        }

        // Output the body
        echo $this->body;
    }
}
