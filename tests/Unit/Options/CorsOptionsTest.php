<?php

namespace Waypoint\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use Waypoint\Options\CorsOptions;

final class CorsOptionsTest extends TestCase
{
    public function testToHeadersIsEmptyWhenUnconfigured(): void
    {
        $this->assertSame([], (new CorsOptions())->toHeaders());
    }

    public function testToHeadersOnlyIncludesConfiguredHeaders(): void
    {
        $opts = new CorsOptions();
        $opts->allowOrigin = 'https://example.com';

        $this->assertSame(
            ['Access-Control-Allow-Origin' => 'https://example.com'],
            $opts->toHeaders()
        );
    }

    public function testToHeadersIncludesEveryConfiguredHeader(): void
    {
        $opts = new CorsOptions();
        $opts->allowOrigin = 'https://example.com';
        $opts->allowMethods = 'GET, POST';
        $opts->allowHeaders = 'Content-Type';
        $opts->exposeHeaders = 'ETag';
        $opts->maxAge = 86400;
        $opts->allowCredentials = true;

        $this->assertSame(
            [
                'Access-Control-Allow-Origin' => 'https://example.com',
                'Access-Control-Allow-Methods' => 'GET, POST',
                'Access-Control-Allow-Headers' => 'Content-Type',
                'Access-Control-Expose-Headers' => 'ETag',
                'Access-Control-Max-Age' => '86400',
                'Access-Control-Allow-Credentials' => 'true',
            ],
            $opts->toHeaders()
        );
    }

    public function testAllowCredentialsDefaultsToFalseAndProducesNoHeader(): void
    {
        $opts = new CorsOptions();
        $this->assertFalse($opts->allowCredentials);
        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials', $opts->toHeaders());
    }

    public function testAllowCredentialsFalseNeverProducesABlankOrFalseValuedHeader(): void
    {
        // Regression guard: the original array-based useCors() cast
        // isset($options['allowCredentials']) ? (string) false : ... which
        // sent an empty-valued header when explicitly false, instead of
        // omitting it -- allowCredentials being a plain bool makes that
        // shape unrepresentable.
        $opts = new CorsOptions();
        $opts->allowCredentials = false;

        $this->assertArrayNotHasKey('Access-Control-Allow-Credentials', $opts->toHeaders());
    }

    public function testMaxAgeIsStringified(): void
    {
        $opts = new CorsOptions();
        $opts->maxAge = 0;

        $this->assertSame('0', $opts->toHeaders()['Access-Control-Max-Age']);
    }
}
