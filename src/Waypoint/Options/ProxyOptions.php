<?php

namespace Waypoint\Options;

/**
 * The reverse proxies in front of the app (nginx, Caddy, a load balancer), by address or CIDR range:
 * `['127.0.0.1', '10.0.0.0/8', '::1']`. A request that comes from one of them is taken at its word:
 * `$req->ip` becomes the client's address from X-Forwarded-For, `$req->https` follows X-Forwarded-Proto.
 * From anyone else both headers are ignored. Empty by default: nothing is trusted.
 */
class ProxyOptions
{
    /** @var list<string> */
    public array $trusted = [];
}
