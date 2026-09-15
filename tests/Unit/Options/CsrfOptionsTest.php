<?php

namespace Waypoint\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use Error;
use Waypoint\Options\CsrfOptions;

final class CsrfOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $opts = new CsrfOptions();
        $this->assertSame(3600, $opts->ttl);
        $this->assertSame('csrf_token', $opts->cookieName);
        $this->assertSame('X-CSRF-Token', $opts->headerName);
        $this->assertSame('_csrf', $opts->fieldName);
        $this->assertTrue($opts->cookieSecure);
        $this->assertSame('Lax', $opts->cookieSameSite);
    }

    public function testSecretHasNoDefaultAndMustBeConfiguredBeforeUse(): void
    {
        $opts = new CsrfOptions();

        $this->expectException(Error::class);
        // Reading an uninitialized typed property throws in PHP; this is
        // deliberate so a forgotten secret fails loudly instead of silently
        // signing tokens with a guessable default.
        $opts->secret;
    }

    public function testSecretCanBeAssigned(): void
    {
        $opts = new CsrfOptions();
        $opts->secret = 'super-secret';
        $this->assertSame('super-secret', $opts->secret);
    }
}
