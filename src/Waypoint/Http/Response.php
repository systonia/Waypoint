<?php

namespace Waypoint\Http;

use Waypoint\Options\CompressionOptions;

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
     * Set by Router::dispatch() when the matched route's #[NoGzip]
     * (class- or method-level) says this response must never be
     * compressed, regardless of what CompressionOptions/Accept-Encoding
     * would otherwise allow -- see maybeCompress().
     *
     * @var bool
     */
    private bool $gzipDisabled = false;

    /**
     * @var array<string, string>
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

    public function __construct(
        // private (not final private -- PHPStan rejects that combination
        // outright, since a private property has no override surface for
        // final to protect in the first place): a subclass could still
        // declare its own $compressionOptions, which would just shadow
        // this one rather than "override" it in any way that matters --
        // every method here always reads/writes this exact private slot
        // regardless.
        private CompressionOptions $compressionOptions = new CompressionOptions()
    ) {
    }

    /**
     * Opts this one response out of gzip compression -- see $gzipDisabled.
     * Called by Router::dispatch() for a route carrying #[NoGzip]; nothing
     * else in this class needs to call it directly.
     */
    public function disableGzip(): self
    {
        $this->gzipDisabled = true;
        return $this;
    }

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
        $body = $this->maybeCompress($this->body);

        if (!headers_sent()) {
            // Set HTTP status code
            http_response_code($this->status);

            // Send all custom headers except Content-Length -- always set
            // fresh below instead, from whatever body actually ends up
            // being sent: maybeCompress() can shrink it, so any
            // Content-Length a caller already set here (e.g. from the
            // uncompressed content) would otherwise win and corrupt the
            // response.
            foreach ($this->headers as $name => $value) {
                if (strcasecmp($name, 'Content-Length') === 0) {
                    continue;
                }
                header("{$name}: {$value}");
            }
            header('Content-Length: ' . strlen($body));

            // Each cookie as its own "Set-Cookie" header line -- false
            // (don't replace) so a second withCookie() call adds another
            // header instead of overwriting the first, the same way
            // multiple real Set-Cookie headers are meant to stack.
            foreach ($this->cookies as $cookie) {
                header('Set-Cookie: ' . $cookie, false);
            }
        }

        // Output the body
        echo $body;
    }

    /**
     * gzip-compresses $body and returns it, or returns $body unchanged, all
     * gated behind (in order): $gzipDisabled (#[NoGzip] on the matched
     * route), CompressionOptions::$enabled, the client actually advertising
     * gzip support via Accept-Encoding, the body meeting
     * CompressionOptions::$minBytes (compressing a small body is a net loss
     * once gzip's own framing overhead is counted), and the response not
     * already carrying its own Content-Encoding (e.g. a file/proxy result
     * that's already compressed -- never double-encode).
     */
    private function maybeCompress(string $body): string
    {
        // $_SERVER values are typed mixed (PHPStan has no way to know what
        // a given SAPI actually put there) -- a real header value is
        // always a string in practice, but this narrows explicitly rather
        // than assuming it.
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        $acceptEncoding = is_string($acceptEncoding) ? $acceptEncoding : '';

        if (
            $this->gzipDisabled
            || !$this->compressionOptions->enabled
            || strlen($body) < $this->compressionOptions->minBytes
            || isset($this->headers['Content-Encoding'])
            || !str_contains($acceptEncoding, 'gzip')
        ) {
            return $body;
        }

        $compressed = gzencode($body, 6);
        // @codeCoverageIgnoreStart
        // gzencode() only fails on a genuine zlib-level failure, not
        // reachable by feeding it any ordinary string -- same category of
        // guard as FileSystem::readViewAssetFile()'s file_get_contents()
        // false-branch.
        if ($compressed === false) {
            return $body;
        }
        // @codeCoverageIgnoreEnd

        $this->headers['Content-Encoding'] = 'gzip';
        // A compressed response varies by what the client sent in
        // Accept-Encoding -- tells any cache sitting in front of this
        // (CDN, browser disk cache) not to serve a gzip response to a
        // client that never asked for one, or vice versa.
        $this->headers['Vary'] = isset($this->headers['Vary'])
            ? $this->headers['Vary'] . ', Accept-Encoding'
            : 'Accept-Encoding';

        return $compressed;
    }
}
