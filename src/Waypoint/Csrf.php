<?php

namespace Waypoint;

use Waypoint\Http\{Request, Response};
use Waypoint\Options\{CsrfOptions, JWTOptions};
use Waypoint\Support\{Base64Url, FiberLocal};

/**
 * Stateless double-submit-cookie CSRF protection. A token is a self-contained
 * HMAC-signed {exp, nonce} pair (same shape as JWT, minus the header segment);
 * it is issued as a readable cookie and must be repeated back by the client
 * in CsrfOptions::$headerName (AJAX) or $fieldName (plain form). A forged
 * cross-site request rides the cookie jar but can't read the cookie to
 * repeat it, so the two values never match.
 *
 * The signature also covers the session cookie (JWTOptions::$cookieName): a
 * token someone fetched for themselves and planted in a victim's cookie jar
 * (from a sibling subdomain, say) doesn't verify against the victim's session.
 * A login or logout changes the session, so the next rendered view mints a new token.
 *
 * A container singleton: $currentToken is per-request state set by
 * issueFor() (Router::renderView) and read by token()/field() from inside
 * a template via `$this->csrf`; App::handle() reset()s it afterwards. It is
 * kept per Fiber, so requests running interleaved in Fibers keep their own.
 *
 * Every check is a no-op when CsrfOptions::$secret was never configured:
 * an app that never opted in must keep rendering views and accepting POSTs.
 */
class Csrf
{
    /** @var FiberLocal<string> */
    private FiberLocal $currentToken;

    public function __construct()
    {
        $this->currentToken = new FiberLocal();
    }

    /** Reuses the request's still-valid cookie token, or mints a fresh one and sets it (not HttpOnly: JS must be able to read it). */
    public function issueFor(Request $req, Response $res): void
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        if (!isset($opts->secret)) {
            return;
        }

        $existing = $req->cookies[$opts->cookieName] ?? null;
        if (self::isValidToken($existing, self::session($req))) {
            $this->currentToken->set($existing);
            return;
        }

        $this->issue(self::session($req), $res, $opts);
    }

    /**
     * A response that signs someone in or out (it sets JWTOptions::$cookieName) also gets a CSRF token for that new
     * session: the old one is bound to the old session, and a fetch() right after a login would otherwise get a 403.
     * ponytail: a page rendered into that same response still embeds the old token; a login answers with a redirect.
     */
    public function followSession(Response $res): void
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        $sessionCookie = Waypoint::getConfig(JWTOptions::class)->cookieName;
        if (!isset($opts->secret) || $sessionCookie === null) {
            return;
        }
        $session = $res->queuedCookie($sessionCookie);
        if ($session === null) {
            return;
        }
        $this->issue($session, $res, $opts);
    }

    /** A fresh token for $session, for this request and as the cookie (not HttpOnly: JS must be able to read it). */
    private function issue(string $session, Response $res, CsrfOptions $opts): void
    {
        $token = self::generateToken($session);
        $this->currentToken->set($token);
        $res->withCookie(
            $opts->cookieName,
            $token,
            maxAge: $opts->ttl,
            secure: $opts->cookieSecure,
            httpOnly: false,
            sameSite: $opts->cookieSameSite,
        );
    }

    /** The current request's token, or '' if issueFor() never ran. */
    public function token(): string
    {
        return $this->currentToken->get() ?? '';
    }

    public function reset(): void
    {
        $this->currentToken->set(null);
    }

    /** `<input type="hidden" name="{fieldName}" value="{token}">` for a plain `<form>`, as Html a view prints unescaped. */
    public function field(): Html
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        return new Html(sprintf('<input type="hidden" name="%s" value="%s">', htmlspecialchars($opts->fieldName, ENT_QUOTES), htmlspecialchars($this->token(), ENT_QUOTES)));
    }

    /** True if the cookie token is valid and the client repeated exactly it back via header or body field. */
    #[\NoDiscard('Ignoring the result silently skips checking whether the request actually carried a valid CSRF token.')]
    public function verify(Request $req): bool
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        if (!isset($opts->secret)) {
            return true;
        }

        $cookieToken = $req->cookies[$opts->cookieName] ?? '';
        $submitted = self::submittedToken($req, $opts);
        if ($cookieToken === '' || $submitted === null) {
            return false;
        }
        return hash_equals($cookieToken, $submitted) && self::isValidToken($cookieToken, self::session($req));
    }

    /** The session cookie (JWTOptions::$cookieName) $req carries, '' without one: what its token is bound to. */
    private static function session(Request $req): string
    {
        $sessionCookie = Waypoint::getConfig(JWTOptions::class)->cookieName;
        return $sessionCookie !== null ? ($req->cookies[$sessionCookie] ?? '') : '';
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

    /**
     * A fresh signed token for $session (the value of the session cookie, '' without one); the nonce only keeps
     * two tokens minted in the same second distinct.
     */
    #[\NoDiscard('The generated token is the entire point of calling generateToken().')]
    public static function generateToken(string $session = ''): string
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        $p = Base64Url::encodeJson(['exp' => time() + $opts->ttl, 'nonce' => bin2hex(random_bytes(16))]);
        return "$p." . Base64Url::sign(self::signedContent($p, $session), $opts->secret);
    }

    /** The payload plus the session it belongs to. '|' never occurs in base64url, so the two can't be shifted into each other. */
    private static function signedContent(string $payload, string $session): string
    {
        return $payload . '|' . $session;
    }

    /** True if $token was signed against the current secret for $session (see generateToken()) and its 'exp' hasn't passed. */
    #[\NoDiscard('Ignoring the result silently skips checking whether the token actually verified.')]
    public static function isValidToken(?string $token, string $session = ''): bool
    {
        if ($token === null || substr_count($token, '.') !== 1) {
            return false;
        }
        [$p, $s] = explode('.', $token);
        if (!hash_equals(Base64Url::sign(self::signedContent($p, $session), Waypoint::getConfig(CsrfOptions::class)->secret), $s)) {
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
