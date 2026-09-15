<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Http\{MiddlewareBase, Request, Response, SecurityHeadersMiddleware};
use Waypoint\Options\SecurityHeaderOptions;
use Waypoint\Tests\Fixtures\Controllers\SecurityHeaderController;
use Waypoint\Tests\Fixtures\Support\CallTracker;

final class SecurityHeadersMiddlewareTest extends IntegrationTestCase
{
    private function bootApp(): void
    {
        $app = Waypoint::create();
        $app->attach([SecurityHeaderController::class]);
        $app->use(new SecurityHeadersMiddleware());
    }

    public function testDefaultHeadersAreSetOnAPlainResponse(): void
    {
        $this->bootApp();

        $this->dispatch('GET', '/security/plain');
        $headers = $this->sentHeaders();

        $this->assertSame('nosniff', $headers['x-content-type-options'] ?? null);
        $this->assertSame('DENY', $headers['x-frame-options'] ?? null);
        $this->assertSame('strict-origin-when-cross-origin', $headers['referrer-policy'] ?? null);
    }

    public function testCspIsAbsentByDefault(): void
    {
        $this->bootApp();

        $this->dispatch('GET', '/security/plain');

        $this->assertArrayNotHasKey('content-security-policy', $this->sentHeaders());
    }

    public function testHstsIsAbsentOnAPlainHttpRequest(): void
    {
        $this->bootApp();

        $this->dispatch('GET', '/security/plain');

        $this->assertArrayNotHasKey('strict-transport-security', $this->sentHeaders());
    }

    public function testHstsIsSentWhenTheRequestArrivedOverHttps(): void
    {
        $this->bootApp();

        $_SERVER['HTTPS'] = 'on';
        try {
            $this->dispatch('GET', '/security/plain');
        } finally {
            unset($_SERVER['HTTPS']);
        }

        $this->assertSame('max-age=31536000; includeSubDomains', $this->sentHeaders()['strict-transport-security'] ?? null);
    }

    public function testHstsIsAbsentWhenServerHttpsIsExplicitlyOff(): void
    {
        // IIS-style: set to the literal string 'off' rather than unset.
        $this->bootApp();

        $_SERVER['HTTPS'] = 'off';
        try {
            $this->dispatch('GET', '/security/plain');
        } finally {
            unset($_SERVER['HTTPS']);
        }

        $this->assertArrayNotHasKey('strict-transport-security', $this->sentHeaders());
    }

    public function testControllerSetHeaderIsNeverOverwritten(): void
    {
        $this->bootApp();

        $this->dispatch('GET', '/security/custom-frame-options');

        $this->assertSame('SAMEORIGIN', $this->sentHeaders()['x-frame-options'] ?? null);
    }

    public function testIndividualHeadersCanBeDisabled(): void
    {
        $app = Waypoint::create();
        $app->attach([SecurityHeaderController::class]);
        $app->configure(function (SecurityHeaderOptions $opts) {
            $opts->frameOptionsEnabled = false;
        });
        $app->use(new SecurityHeadersMiddleware());

        $this->dispatch('GET', '/security/plain');
        $headers = $this->sentHeaders();

        $this->assertArrayNotHasKey('x-frame-options', $headers);
        // The others stay on -- disabling one doesn't disable the rest.
        $this->assertSame('nosniff', $headers['x-content-type-options'] ?? null);
    }

    public function testHeaderValuesAreOverridable(): void
    {
        $app = Waypoint::create();
        $app->attach([SecurityHeaderController::class]);
        $app->configure(function (SecurityHeaderOptions $opts) {
            $opts->frameOptions = 'SAMEORIGIN';
        });
        $app->use(new SecurityHeadersMiddleware());

        $this->dispatch('GET', '/security/plain');

        $this->assertSame('SAMEORIGIN', $this->sentHeaders()['x-frame-options'] ?? null);
    }

    public function testCspIsSentOnceOptedIntoWithAValue(): void
    {
        $app = Waypoint::create();
        $app->attach([SecurityHeaderController::class]);
        $app->configure(function (SecurityHeaderOptions $opts) {
            $opts->cspEnabled = true;
            $opts->csp = "default-src 'self'";
        });
        $app->use(new SecurityHeadersMiddleware());

        $this->dispatch('GET', '/security/plain');

        $this->assertSame("default-src 'self'", $this->sentHeaders()['content-security-policy'] ?? null);
    }

    public function testMultipleUseRegisteredMiddlewaresRunAfterHooksInReverseRegistrationOrder(): void
    {
        // Confirms the "onion" execution model: App::handleHttp() nests
        // $app->use() middlewares LIFO, so before() runs in registration
        // order (First, Second) but after() runs in the *reverse* order
        // (Second, First) -- the last-registered middleware is the
        // innermost wrap, closest to the controller.
        $app = Waypoint::create();
        $app->attach([SecurityHeaderController::class]);
        $app->use(new RecordingMiddleware('First'));
        $app->use(new RecordingMiddleware('Second'));

        $this->dispatch('GET', '/security/plain');

        $this->assertSame(
            ['First:before', 'Second:before', 'Second:after', 'First:after'],
            CallTracker::$calls
        );
    }

    /** @return array<string, string> */
    private function sentHeaders(): array
    {
        $raw = function_exists('xdebug_get_headers') ? xdebug_get_headers() : headers_list();
        $headers = [];
        foreach ($raw as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2) + [1 => '']);
            $headers[strtolower($name)] = $value;
        }
        return $headers;
    }
}

/** Records before()/after() order without touching response headers, so it can't be confused with SecurityHeadersMiddleware's own effects in the ordering test above. */
final class RecordingMiddleware extends MiddlewareBase
{
    public function __construct(private string $label)
    {
    }

    protected function before(Request $req, Response $res): bool
    {
        CallTracker::record("{$this->label}:before");
        return true;
    }

    protected function after(Request $req, Response $res): void
    {
        CallTracker::record("{$this->label}:after");
    }
}
