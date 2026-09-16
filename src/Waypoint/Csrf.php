<?php

namespace Waypoint;

use Waypoint\Http\{Request, Response};
use Waypoint\Options\CsrfOptions;

/**
 * Stateless, double-submit-cookie CSRF protection -- no server-side
 * session/store keeping track of which token belongs to which visitor;
 * a token is just an HMAC-signed, self-contained {exp, nonce} pair (see
 * generateToken()/isValidToken(), the same "segments joined by '.',
 * HMAC-SHA256 over the payload segment" shape JWT::encode()/decode() use),
 * verifiable on its own against CsrfOptions::$secret.
 *
 * The double-submit half of the pattern is what actually defeats CSRF:
 * the token is issued as a cookie (see issueFor()) AND must be repeated
 * back by the client itself, either as a hidden form field (fieldName) or
 * a request header (headerName) -- a cross-site attacker's forged request
 * can ride the browser's cookie jar automatically, but can't *read* the
 * cookie's value (same-origin policy) to also put it in the field/header,
 * so a forged request's two values never match.
 *
 * Container-resolved as a shared singleton, the same as RequestContext --
 * $currentToken is per-request state (set once by issueFor(), read by
 * token()/field()), not app-wide config; Router clears it back to null
 * between requests for the same reason RequestContext does. That's also
 * what makes this #[Inject]-able into View exactly like Environment: by
 * the time a template calls $this->csrf->token(), it's reading the same
 * instance issueFor() already populated for this request.
 */
class Csrf
{
    private ?string $currentToken = null;

    /**
     * Ensures a valid CSRF token is available for embedding into a
     * rendered View: reuses $req's existing cookie if it's still a
     * validly-signed, unexpired token (so re-rendering a form after a
     * failed submission -- CSRF-related or not -- doesn't invalidate a
     * token the browser already carries), otherwise mints a fresh one and
     * sets it on $res as a cookie. Either way, the resolved value becomes
     * what token()/field() return for the rest of this request. Called
     * once per request by Router::renderView(), before View's own
     * #[Inject] properties (including this one) are wired up.
     */
    public function issueFor(Request $req, Response $res): void
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        // issueFor() runs automatically for every rendered View (see
        // Router::renderView()), not from an explicit opt-in call the way
        // JWT::encode() is -- unlike JWTOptions::$secret, which only ever
        // matters once an app deliberately calls JWT::encode()/decode()/
        // useJwt() itself, requiring CsrfOptions to be configured just to
        // render *any* view would break every app that doesn't use CSRF
        // protection at all. No secret configured means nothing to issue.
        if (!self::isConfigured($opts)) {
            return;
        }

        $existing = $_COOKIE[$opts->cookieName] ?? null;
        $existing = is_string($existing) && self::isValidToken($existing) ? $existing : null;

        $token = $existing ?? self::generateToken();

        if ($existing === null) {
            // Deliberately NOT HttpOnly: the whole point of a double-
            // submit cookie is that client-side JS can read it (via
            // document.cookie) and mirror it into $opts->headerName
            // itself for the AJAX/partial-HTML path -- an HttpOnly cookie
            // would make that impossible.
            $res->withCookie(
                $opts->cookieName,
                $token,
                maxAge: $opts->ttl,
                secure: $opts->cookieSecure,
                httpOnly: false,
                sameSite: $opts->cookieSameSite
            );
        }

        $this->currentToken = $token;
    }

    /** The current request's resolved token (see issueFor()), or '' if issueFor() never ran -- a View rendered directly, bypassing Router. */
    public function token(): string
    {
        return $this->currentToken ?? '';
    }

    /**
     * Clears the resolved token again -- called by App::handleHttp()'s
     * finally block, the same "reset the shared singleton's per-request
     * state" safety net RequestContext uses, so a stale token can never
     * leak into a later request sharing this same App/container (e.g. a
     * persistent-worker deployment).
     */
    public function reset(): void
    {
        $this->currentToken = null;
    }

    /** Ready-to-embed hidden input for a classic no-JS <form>, named after CsrfOptions::$fieldName -- e.g. `<?= $this->csrf->field() ?>` inside a view template. */
    public function field(): string
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            htmlspecialchars($opts->fieldName, ENT_QUOTES),
            htmlspecialchars($this->token(), ENT_QUOTES)
        );
    }

    /**
     * True only if $req carries a valid CSRF cookie AND the client
     * repeated the same value back via $opts->headerName (checked first
     * -- the AJAX/partial-HTML path) or $opts->fieldName in the body (the
     * classic no-JS <form> fallback) AND that value's own signature/
     * expiry still check out. Doesn't touch $this->currentToken -- purely
     * a read of the incoming request, independent of whether issueFor()
     * has run in this request at all (e.g. a bare POST with no prior
     * View render).
     */
    #[\NoDiscard('Ignoring the result silently skips checking whether the request actually carried a valid CSRF token.')]
    public function verify(Request $req): bool
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        // Same reasoning as issueFor(): Router::dispatch() calls this
        // automatically for every state-changing request, so an app that
        // never configured CsrfOptions at all (never opted into CSRF
        // protection in the first place) must not have every POST/PUT/
        // PATCH/DELETE start failing -- there's nothing configured to
        // check a token against, so there's nothing to reject.
        if (!self::isConfigured($opts)) {
            return true;
        }

        $cookieToken = $_COOKIE[$opts->cookieName] ?? null;
        if (!is_string($cookieToken) || $cookieToken === '') {
            return false;
        }

        $submitted = self::submittedToken($req, $opts);
        if ($submitted === null) {
            return false;
        }

        return hash_equals($cookieToken, $submitted) && self::isValidToken($cookieToken);
    }

    /** $opts->headerName first (case-insensitively, like every other header lookup in this codebase -- see JWT::fromRequestHeaders()), else $opts->fieldName from the body. */
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
     * Whether CsrfOptions::$secret was ever actually set -- isset() on a
     * typed property that was never assigned returns false rather than
     * throwing the way reading it directly would (see the property's own
     * docblock), which is exactly the "was this opted into at all" check
     * issueFor()/verify() need before doing anything HMAC-related.
     */
    private static function isConfigured(CsrfOptions $opts): bool
    {
        return isset($opts->secret);
    }

    /**
     * A fresh {exp, nonce} token, HMAC-signed against CsrfOptions::$secret
     * -- structurally identical to JWT::encode() (base64url(payload) + '.'
     * + base64url(hmac)), just without JWT's separate header segment,
     * since there's no algorithm choice to encode here. $nonce exists so
     * two tokens minted in the same second aren't byte-identical; it
     * plays no other role in verification.
     */
    #[\NoDiscard('The generated token is the entire point of calling generateToken() -- discarding it is always a bug.')]
    public static function generateToken(): string
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        $payload = json_encode([
            'exp' => time() + $opts->ttl,
            'nonce' => bin2hex(random_bytes(16)),
        ]);
        $encodedPayload = $payload !== false ? $payload : '{}';
        $p = rtrim(strtr(base64_encode($encodedPayload), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', $p, $opts->secret, true);
        $s = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        return "$p.$s";
    }

    /** True if $token is a genuine generateToken() output: correctly HMAC-signed against the current CsrfOptions::$secret, and not yet past its own 'exp'. */
    #[\NoDiscard('Ignoring the result silently skips checking whether the token actually verified.')]
    public static function isValidToken(?string $token): bool
    {
        if ($token === null || $token === '' || substr_count($token, '.') !== 1) {
            return false;
        }
        [$p, $s] = explode('.', $token);

        $opts = Waypoint::getConfig(CsrfOptions::class);
        $sig = hash_hmac('sha256', $p, $opts->secret, true);
        $validSig = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        if (!hash_equals($validSig, $s)) {
            return false;
        }

        $decoded = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
        if (!is_array($decoded)) {
            // @codeCoverageIgnoreStart
            // Every real token generateToken() itself produces has a
            // JSON-object payload; reaching here needs a validly-*signed*
            // token whose payload segment was swapped for something that
            // isn't one, which isn't practically forgeable without the
            // secret -- same reasoning as JWT::decode()'s equivalent guard.
            return false;
            // @codeCoverageIgnoreEnd
        }
        $exp = $decoded['exp'] ?? null;
        return is_int($exp) && $exp >= time();
    }
}
