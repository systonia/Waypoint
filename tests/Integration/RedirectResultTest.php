<?php

namespace Waypoint\Tests\Integration;

use Waypoint\Waypoint;
use Waypoint\Options\RendererOptions;
use Waypoint\Tests\Fixtures\Controllers\RedirectsController;

/**
 * A route handler returning Waypoint\Http\Redirect -- see
 * Router::renderResult(): status + Location, no body, whatever the
 * handler already queued on the Response kept intact.
 */
final class RedirectResultTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = Waypoint::create();
        $app->configure(function (RendererOptions $opts) {
            $opts->directory = __DIR__ . '/../Fixtures/Views';
        });
        $app->attach([RedirectsController::class]);
    }

    public function testRedirectSendsA302WithTheLocationHeaderAndNoBody(): void
    {
        $output = $this->dispatch('GET', '/redirects/default');

        $this->assertSame('', $output);
        $this->assertSame(302, http_response_code());
        $this->assertSame('/landed', $this->sentHeaders()['location'] ?? null);
    }

    public function testRedirectHonoursAnExplicitStatusCode(): void
    {
        $this->dispatch('GET', '/redirects/permanent');

        $this->assertSame(301, http_response_code());
        $this->assertSame('https://example.com/moved', $this->sentHeaders()['location'] ?? null);
    }

    public function testRedirectKeepsCookiesTheHandlerQueuedOnTheResponse(): void
    {
        $output = $this->dispatch('POST', '/redirects/form');

        $this->assertSame('', $output);
        $this->assertSame(303, http_response_code());
        $this->assertSame('/landed', $this->sentHeaders()['location'] ?? null);
        $this->assertStringStartsWith('session=abc', $this->sentHeaders()['set-cookie'] ?? '');
    }

    public function testTheSameHandlerCanReturnAViewInstead(): void
    {
        $output = $this->dispatch('POST', '/redirects/form?fail=1');

        $this->assertStringContainsString('testview hit', $output);
        $this->assertSame(200, http_response_code());
        $this->assertArrayNotHasKey('location', $this->sentHeaders());
    }

    public function testRedirectIgnoresTheRoutesFormatter(): void
    {
        $output = $this->dispatch('GET', '/redirects/formatted');

        $this->assertSame('', $output);
        $this->assertSame(302, http_response_code());
        $this->assertArrayNotHasKey('content-type', $this->sentHeaders());
    }

}
