<?php

namespace Waypoint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waypoint\{Waypoint, Csrf};
use Waypoint\Http\{Request, Response};
use Waypoint\Options\CsrfOptions;

final class CsrfTest extends TestCase
{
    private array $serverBackup;
    private array $postBackup;
    private array $cookieBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->postBackup = $_POST;
        $this->cookieBackup = $_COOKIE;

        // PHP tracks "sent" headers process-wide, across the whole test
        // run -- clear them so a test reading back Set-Cookie only ever
        // sees what *it* sent (see IntegrationTestCase::dispatch()'s
        // identical reasoning).
        header_remove();

        Waypoint::reset();
        Waypoint::create()->configure(function (CsrfOptions $opts) {
            $opts->secret = 'test-secret';
        });
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_POST = $this->postBackup;
        $_COOKIE = $this->cookieBackup;
        Waypoint::reset();
    }

    private function captureRequest(?string $rawBody = null): Request
    {
        return Request::capture($rawBody);
    }

    // -- generateToken() / isValidToken() --

    public function testGenerateTokenProducesTwoBase64UrlSegments(): void
    {
        $token = Csrf::generateToken();
        $segments = explode('.', $token);

        $this->assertCount(2, $segments);
        foreach ($segments as $segment) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $segment);
        }
    }

    public function testGenerateTokenIsValid(): void
    {
        $this->assertTrue(Csrf::isValidToken(Csrf::generateToken()));
    }

    public function testGenerateTokenProducesADifferentTokenEachTime(): void
    {
        $this->assertNotSame(Csrf::generateToken(), Csrf::generateToken());
    }

    public function testIsValidTokenRejectsATamperedSignature(): void
    {
        [$payload] = explode('.', Csrf::generateToken());
        $tampered = $payload . '.' . 'not-a-valid-signature';

        $this->assertFalse(Csrf::isValidToken($tampered));
    }

    public function testIsValidTokenRejectsMalformedTokens(): void
    {
        $this->assertFalse(Csrf::isValidToken(null));
        $this->assertFalse(Csrf::isValidToken(''));
        $this->assertFalse(Csrf::isValidToken('not-a-csrf-token'));
        $this->assertFalse(Csrf::isValidToken('way.too.many.dots'));
    }

    public function testIsValidTokenRejectsATokenSignedWithADifferentSecret(): void
    {
        $token = Csrf::generateToken();

        Waypoint::create()->configure(function (CsrfOptions $opts) {
            $opts->secret = 'a-different-secret';
        });

        $this->assertFalse(Csrf::isValidToken($token));
    }

    // -- verify() --

    public function testVerifyAcceptsAMatchingCookieAndHeaderToken(): void
    {
        $token = Csrf::generateToken();
        $_COOKIE['csrf_token'] = $token;
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        $csrf = new Csrf();
        $this->assertTrue($csrf->verify($this->captureRequest()));
    }

    public function testVerifyAcceptsAMatchingCookieAndBodyFieldToken(): void
    {
        $token = Csrf::generateToken();
        $_COOKIE['csrf_token'] = $token;
        $_POST['_csrf'] = $token;

        $csrf = new Csrf();
        $this->assertTrue($csrf->verify($this->captureRequest()));
    }

    public function testVerifyPrefersTheHeaderOverTheBodyFieldWhenBothArePresent(): void
    {
        $token = Csrf::generateToken();
        $_COOKIE['csrf_token'] = $token;
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $_POST['_csrf'] = 'a-completely-different-value';

        $csrf = new Csrf();
        $this->assertTrue($csrf->verify($this->captureRequest()));
    }

    public function testVerifyRejectsWhenNoCookieIsPresent(): void
    {
        $token = Csrf::generateToken();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;

        $csrf = new Csrf();
        $this->assertFalse($csrf->verify($this->captureRequest()));
    }

    public function testVerifyRejectsWhenNeitherHeaderNorBodyFieldIsPresent(): void
    {
        $token = Csrf::generateToken();
        $_COOKIE['csrf_token'] = $token;

        $csrf = new Csrf();
        $this->assertFalse($csrf->verify($this->captureRequest()));
    }

    public function testVerifyRejectsWhenCookieAndSubmittedValueDoNotMatch(): void
    {
        $_COOKIE['csrf_token'] = Csrf::generateToken();
        $_SERVER['HTTP_X_CSRF_TOKEN'] = Csrf::generateToken();

        $csrf = new Csrf();
        $this->assertFalse($csrf->verify($this->captureRequest()));
    }

    public function testVerifyRejectsAnExpiredToken(): void
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        $opts->ttl = -10;
        $expired = Csrf::generateToken();
        $opts->ttl = 3600;

        $_COOKIE['csrf_token'] = $expired;
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $expired;

        $csrf = new Csrf();
        $this->assertFalse($csrf->verify($this->captureRequest()));
    }

    public function testVerifyRejectsAForgedCookieEvenIfItMatchesTheSubmittedValue(): void
    {
        // Same value on both sides, but neither is a genuine
        // isValidToken() -- proves the double-submit *match* alone isn't
        // enough without the signature also checking out.
        $_COOKIE['csrf_token'] = 'forged-value';
        $_SERVER['HTTP_X_CSRF_TOKEN'] = 'forged-value';

        $csrf = new Csrf();
        $this->assertFalse($csrf->verify($this->captureRequest()));
    }

    public function testVerifyReturnsTrueWhenCsrfOptionsWasNeverConfigured(): void
    {
        // Router::dispatch() calls verify() automatically for every
        // state-changing request -- an app that never opted into CSRF
        // protection at all (never configured CsrfOptions) must not have
        // every POST/PUT/PATCH/DELETE start failing.
        Waypoint::reset();
        Waypoint::create();

        $csrf = new Csrf();
        $this->assertTrue($csrf->verify($this->captureRequest()));
    }

    // -- issueFor() / token() / field() --

    public function testIssueForSetsANonHttpOnlyCookieWhenNoneExistsYet(): void
    {
        $csrf = new Csrf();
        $res = new Response();

        $csrf->issueFor($this->captureRequest(), $res);

        ob_start();
        $res->send();
        $cookieLine = $this->firstSetCookieLine();
        ob_end_clean();

        $this->assertNotNull($cookieLine);
        $this->assertStringStartsWith('csrf_token=' . $csrf->token(), $cookieLine);
        $this->assertStringNotContainsString('HttpOnly', $cookieLine);
        $this->assertStringContainsString('Secure', $cookieLine);
        $this->assertStringContainsString('SameSite=Lax', $cookieLine);
        $this->assertStringContainsString('Max-Age=3600', $cookieLine);
    }

    public function testIssueForReusesAnExistingValidCookieInsteadOfRotating(): void
    {
        $existing = Csrf::generateToken();
        $_COOKIE['csrf_token'] = $existing;

        $csrf = new Csrf();
        $res = new Response();
        $csrf->issueFor($this->captureRequest(), $res);

        ob_start();
        $res->send();
        $cookieLine = $this->firstSetCookieLine();
        ob_end_clean();

        // No new Set-Cookie at all -- the browser already has this exact
        // value, nothing to re-issue.
        $this->assertNull($cookieLine);
        $this->assertSame($existing, $csrf->token());
    }

    public function testIssueForMintsAFreshCookieWhenTheExistingOneIsExpired(): void
    {
        $opts = Waypoint::getConfig(CsrfOptions::class);
        $opts->ttl = -10;
        $expired = Csrf::generateToken();
        $opts->ttl = 3600;
        $_COOKIE['csrf_token'] = $expired;

        $csrf = new Csrf();
        $res = new Response();
        $csrf->issueFor($this->captureRequest(), $res);

        ob_start();
        $res->send();
        $cookieLine = $this->firstSetCookieLine();
        ob_end_clean();

        $this->assertNotNull($cookieLine);
        $this->assertNotSame($expired, $csrf->token());
        $this->assertTrue(Csrf::isValidToken($csrf->token()));
    }

    public function testTokenReturnsEmptyStringBeforeIssueForHasRun(): void
    {
        $csrf = new Csrf();
        $this->assertSame('', $csrf->token());
    }

    public function testFieldEmbedsTheTokenAsAnEscapedHiddenInput(): void
    {
        $csrf = new Csrf();
        $csrf->issueFor($this->captureRequest(), new Response());

        $field = $csrf->field();

        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_csrf"', $field);
        $this->assertStringContainsString('value="' . htmlspecialchars($csrf->token(), ENT_QUOTES) . '"', $field);
    }

    public function testResetClearsTheCurrentToken(): void
    {
        $csrf = new Csrf();
        $csrf->issueFor($this->captureRequest(), new Response());
        $this->assertNotSame('', $csrf->token());

        $csrf->reset();

        $this->assertSame('', $csrf->token());
    }

    public function testIssueForDoesNothingWhenCsrfOptionsWasNeverConfigured(): void
    {
        Waypoint::reset();
        Waypoint::create();

        $csrf = new Csrf();
        $res = new Response();
        $csrf->issueFor($this->captureRequest(), $res);

        ob_start();
        $res->send();
        $cookieLine = $this->firstSetCookieLine();
        ob_end_clean();

        $this->assertNull($cookieLine);
        $this->assertSame('', $csrf->token());
    }

    private function firstSetCookieLine(): ?string
    {
        $raw = function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list();
        foreach ($raw as $line) {
            if (stripos($line, 'Set-Cookie:') === 0) {
                return trim(substr($line, strlen('Set-Cookie:')));
            }
        }
        return null;
    }
}
