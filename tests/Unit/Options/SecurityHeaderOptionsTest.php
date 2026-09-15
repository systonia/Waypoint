<?php

namespace Waypoint\Tests\Unit\Options;

use PHPUnit\Framework\TestCase;
use Waypoint\Options\SecurityHeaderOptions;

final class SecurityHeaderOptionsTest extends TestCase
{
    public function testDefaults(): void
    {
        $opts = new SecurityHeaderOptions();

        $this->assertTrue($opts->contentTypeOptionsEnabled);
        $this->assertSame('nosniff', $opts->contentTypeOptions);

        $this->assertTrue($opts->frameOptionsEnabled);
        $this->assertSame('DENY', $opts->frameOptions);

        $this->assertTrue($opts->referrerPolicyEnabled);
        $this->assertSame('strict-origin-when-cross-origin', $opts->referrerPolicy);

        $this->assertTrue($opts->hstsEnabled);
        $this->assertSame('max-age=31536000; includeSubDomains', $opts->hsts);

        // CSP is the one header with no safe universal default -- opt-in only.
        $this->assertFalse($opts->cspEnabled);
        $this->assertNull($opts->csp);
    }

    public function testEveryFieldIsFreelyAssignable(): void
    {
        $opts = new SecurityHeaderOptions();

        $opts->frameOptionsEnabled = false;
        $opts->frameOptions = 'SAMEORIGIN';
        $opts->cspEnabled = true;
        $opts->csp = "default-src 'self'";

        $this->assertFalse($opts->frameOptionsEnabled);
        $this->assertSame('SAMEORIGIN', $opts->frameOptions);
        $this->assertTrue($opts->cspEnabled);
        $this->assertSame("default-src 'self'", $opts->csp);
    }
}
