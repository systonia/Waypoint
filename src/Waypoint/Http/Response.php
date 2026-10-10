<?php

namespace Waypoint\Http;

use Waypoint\Options\CompressionOptions;

/**
 * The outgoing response, built up by middleware/controllers. App::handle() returns it finalized (gzip, HEAD,
 * Content-Length) for a runtime that writes it out itself via getBody()/getHeaders()/getCookies();
 * App::handleHttp() sends it exactly once through header()/echo.
 */
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

    /** @var array<string, string> name => value of each queued cookie, the last one per name; '' for a removal. */
    private array $cookieValues = [];

    /** Makes send() idempotent: a custom exception handler may call it itself, and handleHttp() always calls it afterwards. */
    private bool $sent = false;

    /** The request this answers (forRequest()): its method and Accept-Encoding decide HEAD and gzip. */
    private ?Request $request = null;

    /** Set by finalize(): the body is compressed if it will be, Content-Length is fixed. */
    private bool $finalized = false;

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

    /** Adds a request header to `Vary`, keeping the ones already listed: caches then keep one copy per value. */
    public function addVary(string $header): self
    {
        $listed = array_filter(array_map('trim', explode(',', $this->headers['Vary'] ?? '')));
        if (!in_array(strtolower($header), array_map('strtolower', $listed), true)) {
            $listed[] = $header;
        }
        $this->headers['Vary'] = implode(', ', $listed);
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
        $this->cookieValues[$name] = $maxAge === 0 ? '' : $value;
        return $this;
    }

    /** The value this response sets $name to ('' when it removes it), or null when it doesn't touch that cookie. */
    public function queuedCookie(string $name): ?string
    {
        return $this->cookieValues[$name] ?? null;
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

    /**
     * Ties the response to $req: HEAD drops the body, its Accept-Encoding decides gzip. App::handle() does this.
     * Until finalize() a bound response ignores send(): an exception handler calling it itself must not write
     * through header()/echo when the caller of handle() writes the response out on its own.
     */
    public function forRequest(Request $req): self
    {
        $this->request = $req;
        return $this;
    }

    /**
     * Compresses the body if it qualifies and fixes Content-Length to the body actually sent. A second call does
     * nothing. Without forRequest() it falls back to the superglobals (a Response sent on its own under FPM).
     */
    public function finalize(): self
    {
        if ($this->finalized) {
            return $this;
        }
        $this->finalized = true;
        $acceptEncoding = $this->request !== null ? ($this->request->headers['Accept-Encoding'] ?? '') : ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
        $this->body = $this->maybeCompress($this->body, is_string($acceptEncoding) ? $acceptEncoding : '');
        // Content-Length is always recomputed from the body actually sent (compression may have shrunk it).
        foreach (array_keys($this->headers) as $name) {
            if (strcasecmp($name, 'Content-Length') === 0) {
                unset($this->headers[$name]);
            }
        }
        $this->headers['Content-Length'] = (string) strlen($this->body);
        return $this;
    }

    /** The body as it goes out: compressed after finalize(), '' for a HEAD request (whose Content-Length is the GET's). */
    public function getBody(): string
    {
        $method = $this->request !== null ? $this->request->method : ($_SERVER['REQUEST_METHOD'] ?? '');
        return $method === 'HEAD' ? '' : $this->body;
    }

    /** @return array<string, string> Every header but Set-Cookie (see getCookies()). */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** @return list<string> One raw Set-Cookie value each. */
    public function getCookies(): array
    {
        return array_values($this->cookies);
    }

    /** Finalizes, then sends status, headers, cookies and body. A second call does nothing. */
    public function send(): void
    {
        if ($this->sent || ($this->request !== null && !$this->finalized)) {
            return;
        }
        $this->sent = true;
        $this->finalize();

        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
            foreach ($this->cookies as $cookie) {
                header('Set-Cookie: ' . $cookie, false);
            }
        }
        echo $this->getBody();
    }

    /** Whether Accept-Encoding allows gzip: named (or by `*`) with a quality above 0; `gzip;q=0` refuses it outright. */
    private static function acceptsGzip(string $acceptEncoding): bool
    {
        $wildcard = null;
        foreach (explode(',', $acceptEncoding) as $entry) {
            $parts = array_map('trim', explode(';', $entry));
            $coding = strtolower($parts[0]);
            $quality = 1.0;
            foreach (array_slice($parts, 1) as $parameter) {
                if (preg_match('/^q\s*=\s*([0-9.]+)$/i', $parameter, $match) === 1) {
                    $quality = (float) $match[1];
                }
            }
            if ($coding === 'gzip' || $coding === 'x-gzip') {
                return $quality > 0;
            }
            if ($coding === '*') {
                $wildcard = $quality > 0;
            }
        }
        return $wildcard ?? false;
    }

    /** gzip when: not disabled, enabled in options, the client accepts it, the body is big enough, and it isn't already encoded. */
    private function maybeCompress(string $body, string $acceptEncoding): string
    {
        if (
            $this->gzipDisabled
            || !$this->compressionOptions->enabled
            || strlen($body) < $this->compressionOptions->minBytes
            || isset($this->headers['Content-Encoding'])
            || !self::acceptsGzip($acceptEncoding)
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
        $this->addVary('Accept-Encoding');
        return $compressed;
    }
}
