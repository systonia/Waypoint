<?php

namespace Waypoint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waypoint\{Waypoint, JWT};
use Waypoint\Options\JWTOptions;

final class JWTTest extends TestCase
{
    protected function setUp(): void
    {
        Waypoint::reset();
        Waypoint::create()->configure(function (JWTOptions $opts) {
            $opts->secret = 'test-secret';
        });
    }

    protected function tearDown(): void
    {
        Waypoint::reset();
    }

    public function testEncodeThenDecodeRoundTripsThePayload(): void
    {
        $token = JWT::encode(['sub' => '123', 'name' => 'Ada']);
        $payload = JWT::decode($token);

        $this->assertSame('123', $payload['sub']);
        $this->assertSame('Ada', $payload['name']);
        $this->assertArrayHasKey('exp', $payload);
    }

    public function testEncodeProducesThreeBase64UrlSegments(): void
    {
        $token = JWT::encode(['sub' => '1']);
        $segments = explode('.', $token);

        $this->assertCount(3, $segments);
        foreach ($segments as $segment) {
            $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $segment);
        }
    }

    public function testDecodeRejectsATamperedSignature(): void
    {
        $token = JWT::encode(['sub' => '1']);
        [$header, $payload] = explode('.', $token);
        $tampered = $header . '.' . $payload . '.' . 'not-a-valid-signature';

        $this->assertNull(JWT::decode($tampered));
    }

    public function testDecodeRejectsATamperedPayload(): void
    {
        $token = JWT::encode(['sub' => '1', 'admin' => false]);
        [$header, , $signature] = explode('.', $token);

        $forgedPayload = rtrim(strtr(base64_encode(json_encode(['sub' => '1', 'admin' => true])), '+/', '-_'), '=');
        $forged = "$header.$forgedPayload.$signature";

        $this->assertNull(JWT::decode($forged));
    }

    public function testDecodeRejectsMalformedTokens(): void
    {
        $this->assertNull(JWT::decode('not-a-jwt'));
        $this->assertNull(JWT::decode('only.two'));
        $this->assertNull(JWT::decode('way.too.many.dots'));
    }

    public function testDecodeRejectsAnExpiredToken(): void
    {
        $token = JWT::encode(['sub' => '1'], ttl: -10);

        $this->assertNull(JWT::decode($token));
    }

    public function testEncodeTtlOverridesTheConfiguredDefault(): void
    {
        $token = JWT::encode(['sub' => '1'], ttl: 3600);
        $payload = JWT::decode($token);

        $this->assertGreaterThan(time() + 3000, $payload['exp']);
    }

    public function testFromRequestHeadersExtractsBearerTokenCaseInsensitively(): void
    {
        $token = JWT::encode(['sub' => '42']);

        $payload = JWT::fromRequestHeaders(['authorization' => "Bearer $token"]);

        $this->assertSame('42', $payload['sub']);
    }

    public function testFromRequestHeadersReturnsNullWhenHeaderMissing(): void
    {
        $this->assertNull(JWT::fromRequestHeaders([]));
    }

    public function testFromRequestHeadersReturnsNullWhenPrefixDoesNotMatch(): void
    {
        $token = JWT::encode(['sub' => '1']);

        $this->assertNull(JWT::fromRequestHeaders(['Authorization' => "Basic $token"]));
    }

    public function testFromRequestHeadersHandlesArrayHeaderValues(): void
    {
        $token = JWT::encode(['sub' => '7']);

        $payload = JWT::fromRequestHeaders(['Authorization' => ["Bearer $token"]]);

        $this->assertSame('7', $payload['sub']);
    }
}
