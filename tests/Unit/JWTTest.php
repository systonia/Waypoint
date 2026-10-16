<?php

namespace Waypoint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Waypoint\{Waypoint, JWT};
use Waypoint\Http\{Request, Response};
use Waypoint\Options\JWTOptions;

final class JWTTest extends TestCase
{
    protected function setUp(): void
    {
        Waypoint::reset();
        Waypoint::create()->configure(function (JWTOptions $opts) {
            $opts->secret = 'test-secret-long-enough-for-hmac-32';
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

    public function testSignInSetsTheSessionCookieAndSignOutRemovesIt(): void
    {
        Waypoint::getConfig(JWTOptions::class)->cookieName = 'session';
        Waypoint::getConfig(JWTOptions::class)->ttl = 600;
        $res = (new Response())->forRequest(Request::fromParts('POST', '/login', https: true));

        $token = JWT::signIn($res, ['sub' => 7]);

        $this->assertSame(7, JWT::decode($token)['sub'] ?? null);
        $this->assertSame("session=$token; Path=/; Max-Age=600; Secure; HttpOnly; SameSite=Lax", $res->getCookies()[0]);

        JWT::signOut($res);
        $this->assertSame('session=; Path=/; Max-Age=0; Secure; HttpOnly; SameSite=Lax', $res->getCookies()[1]);
    }

    public function testSignInNeedsACookieName(): void
    {
        $this->expectException(\LogicException::class);
        JWT::signIn(new Response(), ['sub' => 1]);
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

    /** A validly signed token with any header and payload, as someone holding the secret (or a buggy issuer) could write it. */
    private static function signed(array $header, array $payload): string
    {
        $h = \Waypoint\Support\Base64Url::encodeJson($header);
        $p = \Waypoint\Support\Base64Url::encodeJson($payload);
        return "$h.$p." . \Waypoint\Support\Base64Url::sign("$h.$p", 'test-secret-long-enough-for-hmac-32');
    }

    public function testDecodeRejectsATokenThatNeverExpires(): void
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $this->assertNull(JWT::decode(self::signed($header, ['sub' => '1'])), 'no exp');
        $this->assertNull(JWT::decode(self::signed($header, ['sub' => '1', 'exp' => 'never'])), 'text exp');
        $this->assertNull(JWT::decode(self::signed($header, ['sub' => '1', 'exp' => time() - 0.5])), 'fractional past exp');
        $this->assertNull(JWT::decode(self::signed($header, ['sub' => '1', 'exp' => time()])), 'exp is now');
        $this->assertSame('1', JWT::decode(self::signed($header, ['sub' => '1', 'exp' => time() + 60.5]))['sub'] ?? null);
    }

    public function testDecodeAcceptsOnlyTheHs256ItSigns(): void
    {
        $payload = ['sub' => '1', 'exp' => time() + 60];

        $this->assertNull(JWT::decode(self::signed(['alg' => 'none', 'typ' => 'JWT'], $payload)));
        $this->assertNull(JWT::decode(self::signed(['alg' => 'RS256', 'typ' => 'JWT'], $payload)));
        $this->assertNull(JWT::decode(self::signed(['typ' => 'JWT'], $payload)));
        $this->assertNotNull(JWT::decode(self::signed(['alg' => 'HS256', 'typ' => 'JWT'], $payload)));
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

    public function testAShortSecretIsRefusedInsteadOfSigningWithIt(): void
    {
        $this->expectExceptionMessage('needs at least 32 bytes');
        \Waypoint\Support\Base64Url::sign('data', '');
    }
}
