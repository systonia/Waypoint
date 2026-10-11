<?php

namespace Waypoint\Http;

/** @internal What ProxyOptions::$trusted lets a request say about its client: App::handle() applies it. */
final class TrustedProxies
{
    /** @param list<string> $trusted addresses or CIDR ranges */
    public static function apply(Request $req, array $trusted): void
    {
        if ($trusted === [] || !self::matches($req->ip, $trusted)) {
            return;
        }
        // From the right: each trusted proxy appended the address it got the request from
        $hops = array_reverse(array_map('trim', explode(',', $req->headers['X-Forwarded-For'] ?? '')));
        foreach ($hops as $hop) {
            if (filter_var($hop, FILTER_VALIDATE_IP) === false) {
                break;
            }
            $req->ip = $hop;
            if (!self::matches($hop, $trusted)) {
                break;
            }
        }
        $proto = strtolower(trim(explode(',', $req->headers['X-Forwarded-Proto'] ?? '')[0]));
        if ($proto === 'https' || $proto === 'http') {
            $req->https = $proto === 'https';
        }
    }

    /** @param list<string> $ranges */
    private static function matches(string $ip, array $ranges): bool
    {
        $address = @inet_pton($ip);
        if ($address === false) {
            return false;
        }
        foreach ($ranges as $range) {
            $parts = explode('/', $range, 2);
            $network = $parts[0];
            $bits = $parts[1] ?? null;
            $net = @inet_pton($network);
            if ($net === false || strlen($net) !== strlen($address)) {
                continue;
            }
            $bits = $bits === null ? strlen($net) * 8 : (int) $bits;
            $bytes = intdiv($bits, 8);
            $rest = $bits % 8;
            if (substr($address, 0, $bytes) !== substr($net, 0, $bytes)) {
                continue;
            }
            if ($rest === 0 || ((ord($address[$bytes]) ^ ord($net[$bytes])) & (0xff << (8 - $rest)) & 0xff) === 0) {
                return true;
            }
        }
        return false;
    }
}
