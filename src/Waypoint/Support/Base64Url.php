<?php

namespace Waypoint\Support;

/** RFC 4648 §5 base64url (no padding) plus the HMAC-SHA256 signing JWT and Csrf tokens share. */
final class Base64Url
{
    public static function encode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function decode(string $encoded): string
    {
        return base64_decode(strtr($encoded, '-_', '+/'));
    }

    /** base64url(HMAC-SHA256($data, $secret)) -- compare with hash_equals(). */
    public static function sign(string $data, string $secret): string
    {
        return self::encode(hash_hmac('sha256', $data, $secret, true));
    }

    /** base64url(json_encode($value)), '{}' if it can't be encoded. */
    public static function encodeJson(mixed $value): string
    {
        $json = json_encode($value);
        return self::encode($json !== false ? $json : '{}');
    }
}
