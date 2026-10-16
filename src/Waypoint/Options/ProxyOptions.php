<?php

namespace Waypoint\Options;

use InvalidArgumentException;

/**
 * The reverse proxies in front of the app (nginx, Caddy, a load balancer), by address or CIDR range:
 * `['127.0.0.1', '10.0.0.0/8', '::1']`. A request that comes from one of them is taken at its word:
 * `$req->ip` becomes the client's address from X-Forwarded-For, `$req->https` follows X-Forwarded-Proto.
 * From anyone else both headers are ignored. Empty by default: nothing is trusted.
 */
class ProxyOptions
{
    /**
     * Refused when an entry is no address or range: a typo like '10.0.0.0/' would otherwise read as /0 and trust everyone.
     * @var list<string>
     */
    public array $trusted = [] {
        set(array $value) {
            foreach ($value as $range) {
                $parts = explode('/', $range, 2);
                $address = @inet_pton($parts[0]);
                $bits = $parts[1] ?? null;
                if ($address === false || ($bits !== null && (!ctype_digit($bits) || (int) $bits > strlen($address) * 8))) {
                    throw new InvalidArgumentException("ProxyOptions::\$trusted: '$range' is no IP address or CIDR range.");
                }
            }
            $this->trusted = $value;
        }
    }
}
