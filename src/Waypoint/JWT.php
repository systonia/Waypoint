<?php

namespace Waypoint;

use Waypoint\Options\JWTOptions;
use Waypoint\Support\{Arr, Base64Url};

/** Minimal HS256 JWT: header.payload.signature, each segment base64url, signed against JWTOptions::$secret. */
class JWT
{
    /** @param array<string, mixed> $payload 'exp' is set here from $ttl (or JWTOptions::$ttl). */
    #[\NoDiscard('The generated token is the entire point of calling encode().')]
    public static function encode(array $payload, ?int $ttl = null): string
    {
        $opts = Waypoint::getConfig(JWTOptions::class);
        $payload['exp'] = time() + ($ttl ?? $opts->ttl);

        $h = Base64Url::encodeJson(['alg' => $opts->alg, 'typ' => 'JWT']);
        $p = Base64Url::encodeJson($payload);
        return "$h.$p." . Base64Url::sign("$h.$p", $opts->secret);
    }

    /** @return array<string, mixed>|null The payload, or null for a malformed, wrongly-signed, or expired token. */
    #[\NoDiscard('Ignoring the result silently skips checking whether the token actually verified.')]
    public static function decode(string $jwt): ?array
    {
        if (substr_count($jwt, '.') !== 2) {
            return null;
        }
        [$h, $p, $s] = explode('.', $jwt);
        if (!hash_equals(Base64Url::sign("$h.$p", Waypoint::getConfig(JWTOptions::class)->secret), $s)) {
            return null;
        }
        $decoded = json_decode(Base64Url::decode($p), true);
        if (!is_array($decoded)) {
            // @codeCoverageIgnoreStart
            // a validly signed token always carries the JSON object encode() wrote.
            return null;
            // @codeCoverageIgnoreEnd
        }
        $payload = Arr::stringKeyed($decoded);
        $exp = $payload['exp'] ?? null;
        return is_int($exp) && $exp < time() ? null : $payload;
    }

    /**
     * The payload of the bearer token in JWTOptions::$header (matched case-insensitively), or null.
     * @param array<string, string|string[]> $headers
     * @return array<string, mixed>|null
     */
    #[\NoDiscard('Ignoring the result silently skips checking whether the request was actually authenticated.')]
    public static function fromRequestHeaders(array $headers): ?array
    {
        $opts = Waypoint::getConfig(JWTOptions::class);
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, $opts->header) !== 0) {
                continue;
            }
            $value = is_array($value) ? $value[0] : $value;
            $prefix = $opts->tokenType . ' ';
            if ($value && strncasecmp($value, $prefix, strlen($prefix)) === 0) {
                return self::decode(trim(substr($value, strlen($prefix))));
            }
            return null;
        }
        return null;
    }
}
