<?php

namespace Waypoint;

use Waypoint\Http\{Request, Response};
use Waypoint\Options\CsrfOptions;
use Waypoint\Support\Base64Url;

/**
 * Stateless double-submit-cookie CSRF protection. A token is a self-contained
 * HMAC-signed {exp, nonce} pair (same shape as JWT, minus the header segment);
 * it is issued as a readable cookie and must be repeated back by the client
 * in CsrfOptions::$headerName (AJAX) or $fieldName (plain form). A forged
 * cross-site request rides the cookie jar but can't read the cookie to
 * repeat it, so the two values never match.
 *
 * A container singleton: $currentToken is per-request state set by
 * issueFor() (Router::renderView) and read by token()/field() from inside
 * a template via `$this->csrf`; App::handleHttp() reset()s it afterwards.
 *
 * Every check is a no-op when CsrfOptions::$secret was never configured --
 * an app that never opted in must keep rendering views and accepting POSTs.
 */
class Csrf
{
    private ?string $currentToken = null;

    /** Reuses the request's still-valid cookie token, or mints a fresh one and sets it (not HttpOnly: JS must be able to read it). */
    public function issueFor(Request $req, Response $res): void
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        if (!isset($opts->secret)) {
            return;
        }

        $existing = $_COOKIE[$opts->cookieName] ?? null;
        if (is_string($existing) && self::isValidToken($existing)) {
            $this->currentToken = $existing;
            return;
        }

        $this->currentToken = self::generateToken();
        $res->withCookie($opts->cookieName, $this->currentToken, maxAge: $opts->ttl, secure: $opts->cookieSecure, httpOnly: false, sameSite: $opts->cookieSameSite);
    }

    /** The current request's token, or '' if issueFor() never ran. */
    public function token(): string
    {
        return $this->currentToken ?? '';
    }

    public function reset(): void
    {
        $this->currentToken = null;
    }

    /** `<input type="hidden" name="{fieldName}" value="{token}">` for a plain <form>. */
    public function field(): string
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        return sprintf('<input type="hidden" name="%s" value="%s">', htmlspecialchars($opts->fieldName, ENT_QUOTES), htmlspecialchars($this->token(), ENT_QUOTES));
    }

    /** True if the cookie token is valid and the client repeated exactly it back via header or body field. */
    #[\NoDiscard('Ignoring the result silently skips checking whether the request actually carried a valid CSRF token.')]
    public function verify(Request $req): bool
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        if (!isset($opts->secret)) {
            return true;
        }

        $cookieToken = $_COOKIE[$opts->cookieName] ?? null;
        $submitted = self::submittedToken($req, $opts);
        if (!is_string($cookieToken) || $cookieToken === '' || $submitted === null) {
            return false;
        }
        return hash_equals($cookieToken, $submitted) && self::isValidToken($cookieToken);
    }

    private static function submittedToken(Request $req, CsrfOptions $opts): ?string
    {
        foreach ($req->headers as $name => $value) {
            if (strcasecmp($name, $opts->headerName) === 0 && $value !== '') {
                return $value;
            }
        }
        $fromBody = $req->body($opts->fieldName);
        return is_string($fromBody) && $fromBody !== '' ? $fromBody : null;
    }

    /** A fresh signed token; the nonce only keeps two tokens minted in the same second distinct. */
    #[\NoDiscard('The generated token is the entire point of calling generateToken().')]
    public static function generateToken(): string
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        $p = Base64Url::encodeJson(['exp' => time() + $opts->ttl, 'nonce' => bin2hex(random_bytes(16))]);
        return "$p." . Base64Url::sign($p, $opts->secret);
    }

    /** True if $token was signed against the current secret and its 'exp' hasn't passed. */
    #[\NoDiscard('Ignoring the result silently skips checking whether the token actually verified.')]
    public static function isValidToken(?string $token): bool
    {
        if ($token === null || substr_count($token, '.') !== 1) {
            return false;
        }
        [$p, $s] = explode('.', $token);
        if (!hash_equals(Base64Url::sign($p, Waypoint::getConfig(CsrfOptions::class)->secret), $s)) {
            return false;
        }
        $decoded = json_decode(Base64Url::decode($p), true);
        if (!is_array($decoded)) {
            // @codeCoverageIgnoreStart
            // a validly signed token always carries the JSON object generateToken() wrote.
            return false;
            // @codeCoverageIgnoreEnd
        }
        $exp = $decoded['exp'] ?? null;
        return is_int($exp) && $exp >= time();
    }
}
