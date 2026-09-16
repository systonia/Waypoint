<?php

namespace Waypoint;

use Waypoint\Options\JWTOptions;

/**
 * Undocumented class
 */
class JWT
{
    /**
     * Undocumented function
     *
     * @param array<string, mixed> $payload
     * @param integer|null $ttl
     * @return string
     */
    #[\NoDiscard('The generated token is the entire point of calling encode() -- discarding it is always a bug.')]
    public static function encode(array $payload, ?int $ttl = null): string
    {
        $opts = Waypoint::getConfig(JWTOptions::class);
        $header = ['alg' => $opts->alg, 'typ' => 'JWT'];
        $payload['exp'] = time() + ($ttl ?? $opts->ttl);

        $encodedHeader = json_encode($header);
        $encodedPayload = json_encode($payload);
        $h = rtrim(strtr(base64_encode($encodedHeader !== false ? $encodedHeader : '{}'), '+/', '-_'), '=');
        $p = rtrim(strtr(base64_encode($encodedPayload !== false ? $encodedPayload : '{}'), '+/', '-_'), '=');
        $sig = hash_hmac('sha256', "$h.$p", $opts->secret, true);
        $s = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        return "$h.$p.$s";
    }

    /**
     * Undocumented function
     *
     * @param string $jwt
     * @return array<string, mixed>|null
     */
    #[\NoDiscard('Ignoring the result (payload, or null for an invalid/expired token) silently skips checking whether the token actually verified.')]
    public static function decode(string $jwt): ?array
    {
        $opts = Waypoint::getConfig(JWTOptions::class);
        if (substr_count($jwt, '.') !== 2) {
            return null;
        }
        [$h, $p, $s] = explode('.', $jwt);
        $sig = hash_hmac('sha256', "$h.$p", $opts->secret, true);
        $validSig = rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');
        if (!hash_equals($validSig, $s)) {
            return null;
        }
        $decoded = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
        if (!is_array($decoded)) {
            // @codeCoverageIgnoreStart
            // Every real token encode() itself produces has a JSON-object
            // payload; reaching here needs a validly-*signed* token whose
            // payload segment was swapped for something that isn't one,
            // which isn't practically forgeable without the secret.
            return null;
            // @codeCoverageIgnoreEnd
        }
        $payload = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $payload[$key] = $value;
            }
        }
        $exp = $payload['exp'] ?? null;
        if (is_int($exp) && $exp < time()) {
            return null;
        }
        return $payload;
    }

    /**
     * Parses the JWT from the given HTTP headers (according to configured header and token type).
     * Returns payload or null.
     *
     * @param array<string, string|string[]> $headers
     * @return array<string, mixed>|null
     */
    #[\NoDiscard('Same reason as decode() -- ignoring the result silently skips checking whether the request was actually authenticated.')]
    public static function fromRequestHeaders(array $headers): ?array
    {
        $opts = Waypoint::getConfig(JWTOptions::class);
        $headerName = $opts->header;
        $value = null;
        foreach ($headers as $k => $v) {
            if (strcasecmp($k, $headerName) === 0) {
                $value = is_array($v) ? $v[0] : $v;
                break;
            }
        }
        if (!$value) {
            return null;
        }
        $prefix = $opts->tokenType . ' ';
        if (strncasecmp($value, $prefix, strlen($prefix)) === 0) {
            $jwt = trim(substr($value, strlen($prefix)));
            return self::decode($jwt);
        }
        return null;
    }
}
