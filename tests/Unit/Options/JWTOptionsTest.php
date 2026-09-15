<?php

namespace Waypoint\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use Error;
use Waypoint\Options\JWTOptions;

final class JWTOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $opts = new JWTOptions();
        $this->assertSame('HS256', $opts->alg);
        $this->assertSame(3600, $opts->ttl);
        $this->assertSame('Bearer', $opts->tokenType);
        $this->assertSame('Authorization', $opts->header);
        $this->assertNull($opts->cookieName);
    }

    public function testSecretHasNoDefaultAndMustBeConfiguredBeforeUse(): void
    {
        $opts = new JWTOptions();

        $this->expectException(Error::class);
        // Reading an uninitialized typed property throws in PHP; this is
        // deliberate so a forgotten secret fails loudly instead of silently
        // signing tokens with a guessable default.
        $opts->secret;
    }

    public function testSecretCanBeAssigned(): void
    {
        $opts = new JWTOptions();
        $opts->secret = 'super-secret';
        $this->assertSame('super-secret', $opts->secret);
    }
}
