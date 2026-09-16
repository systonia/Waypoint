<?php

namespace Waypoint\Http;

use Waypoint\Options\CompressionOptions;

/** The outgoing response, built up by middleware/controllers and sent exactly once by App::handleHttp(). */
class Response
{
    private int $status = 200;

    /** Set via disableGzip() for a #[NoGzip] route. */
    private bool $gzipDisabled = false;

    /** @var array<string, string> */
    private array $headers = [];

    private string $body = '';

    /** @var string[] One raw Set-Cookie value each; kept apart from $headers so several cookies can stack. */
    private array $cookies = [];

    /** Makes send() idempotent: a custom exception handler may call it itself, and handleHttp() always calls it afterwards. */
    private bool $sent = false;

    public function __construct(private CompressionOptions $compressionOptions = new CompressionOptions())
    {
    }

    public function disableGzip(): self
    {
        $this->gzipDisabled = true;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** Case-insensitive -- lets an after() middleware fill in a header only if the controller didn't set it. */
    public function hasHeader(string $name): bool
    {
        foreach ($this->headers as $existing => $value) {
            if (strcasecmp($existing, $name) === 0) {
                return true;
            }
        }
        return false;
    }

    /** The value queued for $name (case-insensitive), or null -- for an after() middleware or Plugin\ResponseHook that must inspect what the controller produced. */
    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $existing => $value) {
            if (strcasecmp($existing, $name) === 0) {
                return $value;
            }
        }
        return null;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /** Echoes Request::$id back as X-Request-ID. */
    public function withRequestId(string $id): self
    {
        return $this->withHeader('X-Request-ID', $id);
    }

    /**
     * Queues a Set-Cookie. HttpOnly and SameSite=Lax by default -- the right defaults for a session cookie.
     * @param int|null $maxAge Seconds; null makes it a session cookie.
     * @param string|null $sameSite 'Lax', 'Strict', 'None', or null to omit the attribute.
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
        $parts = [rawurlencode($name) . '=' . rawurlencode($value)];
        if ($path !== '') {
            $parts[] = "Path=$path";
        }
        if ($domain !== null) {
            $parts[] = "Domain=$domain";
        }
        if ($maxAge !== null) {
            $parts[] = "Max-Age=$maxAge";
        }
        if ($secure) {
            $parts[] = 'Secure';
        }
        if ($httpOnly) {
            $parts[] = 'HttpOnly';
        }
        if ($sameSite !== null) {
            $parts[] = "SameSite=$sameSite";
        }
        $this->cookies[] = implode('; ', $parts);
        return $this;
    }

    /** Expires $name immediately (Max-Age=0); $path/$domain must match how it was set. */
    public function withoutCookie(string $name, string $path = '/', ?string $domain = null): self
    {
        return $this->withCookie($name, '', maxAge: 0, path: $path, domain: $domain);
    }

    public function status(int $code): self
    {
        $this->status = $code;
        return $this;
    }

    public function write(string $data): self
    {
        $this->body .= $data;
        return $this;
    }

    /** Sends status, headers, cookies and body. A second call does nothing. */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }
        $this->sent = true;

        $body = $this->maybeCompress($this->body);

        if (!headers_sent()) {
            http_response_code($this->status);
            // Content-Length is always recomputed from the body actually sent (compression may have shrunk it).
            foreach ($this->headers as $name => $value) {
                if (strcasecmp($name, 'Content-Length') !== 0) {
                    header("{$name}: {$value}");
                }
            }
            header('Content-Length: ' . strlen($body));
            foreach ($this->cookies as $cookie) {
                header('Set-Cookie: ' . $cookie, false);
            }
        }

        echo $body;
    }

    /** gzip when: not disabled, enabled in options, the client accepts it, the body is big enough, and it isn't already encoded. */
    private function maybeCompress(string $body): string
    {
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        if (
            $this->gzipDisabled
            || !$this->compressionOptions->enabled
            || strlen($body) < $this->compressionOptions->minBytes
            || isset($this->headers['Content-Encoding'])
            || !is_string($acceptEncoding)
            || !str_contains($acceptEncoding, 'gzip')
        ) {
            return $body;
        }

        $compressed = gzencode($body, 6);
        if ($compressed === false) {
            // @codeCoverageIgnoreStart
            // a zlib-level failure, unreachable with an ordinary string.
            return $body;
            // @codeCoverageIgnoreEnd
        }

        $this->headers['Content-Encoding'] = 'gzip';
        $this->headers['Vary'] = isset($this->headers['Vary']) ? $this->headers['Vary'] . ', Accept-Encoding' : 'Accept-Encoding';
        return $compressed;
    }
}
