<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Csrf;
use Waypoint\Options\{RendererOptions, CsrfOptions};
use Waypoint\Tests\Fixtures\Controllers\CsrfFormController;

/**
 * End-to-end CSRF protection through a real dispatch(): token issuance +
 * cookie on a rendered View (Csrf::issueFor(), View::$csrf), verification
 * on state-changing requests (Router::dispatch(), Csrf::verify()), the
 * #[SkipCsrf] opt-out, and the "never configured at all" pass-through.
 */
final class CsrfProtectionTest extends IntegrationTestCase
{
    private function bootApp(): void
    {
        $app = Waypoint::create();
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/../Fixtures/Views';
        });
        $app->configure(function (CsrfOptions $opts) {
            $opts->secret = 'test-secret';
        });
        $app->attach([CsrfFormController::class]);
    }

    public function testRenderedFormExposesATokenAndSetsANonHttpOnlyCookie(): void
    {
        $this->bootApp();

        $output = $this->dispatch('GET', '/csrf/form');

        $this->assertMatchesRegularExpression('/Token: \S+\.\S+/', $output);
        $this->assertStringContainsString('type="hidden"', $output);
        $this->assertStringContainsString('name="_csrf"', $output);

        $cookieLine = $this->sentCookieLine('csrf_token');
        $this->assertNotNull($cookieLine);
        $this->assertStringNotContainsString('HttpOnly', $cookieLine);
    }

    public function testPostWithMatchingCookieAndBodyFieldSucceeds(): void
    {
        $this->bootApp();
        $token = Csrf::generateToken();

        $_COOKIE['csrf_token'] = $token;
        try {
            $output = $this->dispatch('POST', '/csrf/submit', [], ['_csrf' => $token]);
        } finally {
            unset($_COOKIE['csrf_token']);
        }

        $this->assertSame('"submitted"', $output);
        $this->assertSame(200, http_response_code());
    }

    public function testPostWithMatchingCookieAndHeaderSucceeds(): void
    {
        $this->bootApp();
        $token = Csrf::generateToken();

        $_COOKIE['csrf_token'] = $token;
        try {
            $output = $this->dispatch('POST', '/csrf/submit', ['X-CSRF-Token' => $token]);
        } finally {
            unset($_COOKIE['csrf_token']);
        }

        $this->assertSame('"submitted"', $output);
        $this->assertSame(200, http_response_code());
    }

    public function testPostWithoutAnyCsrfTokenIsRejectedWith403(): void
    {
        $this->bootApp();

        $output = $this->dispatch('POST', '/csrf/submit');

        $this->assertSame(403, http_response_code());
        $this->assertSame(['error' => 'Invalid or missing CSRF token'], json_decode($output, true));
    }

    public function testPostWithOnlyACookieAndNoSubmittedValueIsRejectedWith403(): void
    {
        $this->bootApp();
        $token = Csrf::generateToken();

        $_COOKIE['csrf_token'] = $token;
        try {
            $output = $this->dispatch('POST', '/csrf/submit');
        } finally {
            unset($_COOKIE['csrf_token']);
        }

        $this->assertSame(403, http_response_code());
        $this->assertSame(['error' => 'Invalid or missing CSRF token'], json_decode($output, true));
    }

    public function testPostWithAMismatchedCookieAndBodyFieldIsRejectedWith403(): void
    {
        $this->bootApp();

        $_COOKIE['csrf_token'] = Csrf::generateToken();
        try {
            $output = $this->dispatch('POST', '/csrf/submit', [], ['_csrf' => Csrf::generateToken()]);
        } finally {
            unset($_COOKIE['csrf_token']);
        }

        $this->assertSame(403, http_response_code());
        $this->assertSame(['error' => 'Invalid or missing CSRF token'], json_decode($output, true));
    }

    public function testPostWithAnExpiredTokenIsRejectedWith403(): void
    {
        $this->bootApp();
        $opts = Waypoint::getConfig(CsrfOptions::class);
        $opts->ttl = -10;
        $expired = Csrf::generateToken();
        $opts->ttl = 3600;

        $_COOKIE['csrf_token'] = $expired;
        try {
            $output = $this->dispatch('POST', '/csrf/submit', [], ['_csrf' => $expired]);
        } finally {
            unset($_COOKIE['csrf_token']);
        }

        $this->assertSame(403, http_response_code());
    }

    public function testSkipCsrfRouteAcceptsAPostWithNoTokenAtAll(): void
    {
        $this->bootApp();

        $output = $this->dispatch('POST', '/csrf/submit-unprotected');

        $this->assertSame('"submitted without csrf"', $output);
        $this->assertSame(200, http_response_code());
    }

    public function testCsrfIsNotEnforcedWhenCsrfOptionsWasNeverConfigured(): void
    {
        $app = Waypoint::create();
        $app->attach([CsrfFormController::class]);
        // No CsrfOptions configure() call at all.

        $output = $this->dispatch('POST', '/csrf/submit');

        $this->assertSame('"submitted"', $output);
        $this->assertSame(200, http_response_code());
    }

    private function sentCookieLine(string $name): ?string
    {
        $raw = function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list();
        foreach ($raw as $line) {
            if (stripos($line, 'Set-Cookie:') === 0 && str_contains($line, "$name=")) {
                return trim(substr($line, strlen('Set-Cookie:')));
            }
        }
        return null;
    }
}
