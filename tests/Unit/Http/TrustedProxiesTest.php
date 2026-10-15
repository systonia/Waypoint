<?php

namespace Waypoint\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Waypoint\Http\{Request, TrustedProxies};

final class TrustedProxiesTest extends TestCase
{
    private static function request(string $ip, string $forwardedFor, string $proto = ''): Request
    {
        return Request::fromParts('GET', '/', array_filter(['X-Forwarded-For' => $forwardedFor, 'X-Forwarded-Proto' => $proto]), ip: $ip);
    }

    public function testATrustedProxyNamesTheClientAndTheScheme(): void
    {
        $req = self::request('10.1.2.3', '203.0.113.9, 10.0.0.5', 'https');
        TrustedProxies::apply($req, ['10.0.0.0/8']);

        $this->assertSame('203.0.113.9', $req->ip);
        $this->assertTrue($req->https);
    }

    public function testTheSchemeIsTheOneTheNearestProxySawNotTheClientsOwn(): void
    {
        // The client sent "https" itself over plain HTTP, the proxy appended what it really saw
        $req = self::request('10.1.2.3', '203.0.113.9', 'https, http');
        TrustedProxies::apply($req, ['10.0.0.0/8']);

        $this->assertFalse($req->https);
    }

    public function testAnyoneElseIsIgnored(): void
    {
        $req = self::request('198.51.100.7', '127.0.0.1', 'https');
        TrustedProxies::apply($req, ['10.0.0.0/8', '127.0.0.1']);

        $this->assertSame('198.51.100.7', $req->ip);
        $this->assertFalse($req->https);
    }

    public function testAForgedHopBeforeTheTrustedOnesIsTheClient(): void
    {
        // The client wrote "1.1.1.1" itself; the proxy appended the address it really came from
        $req = self::request('127.0.0.1', '1.1.1.1, 203.0.113.9');
        TrustedProxies::apply($req, ['127.0.0.1']);

        $this->assertSame('203.0.113.9', $req->ip);
    }

    public function testIpv6RangesAndGarbage(): void
    {
        $req = self::request('::1', '2001:db8::7, fd00::1');
        TrustedProxies::apply($req, ['::1', 'fd00::/8']);
        $this->assertSame('2001:db8::7', $req->ip);

        $req = self::request('::1', 'not-an-ip');
        TrustedProxies::apply($req, ['::1']);
        $this->assertSame('::1', $req->ip);
    }
}
