---
title: Proxies
description: Trust the reverse proxies in front of the app, so the client's address and HTTPS come from X-Forwarded-For and X-Forwarded-Proto.
order: 80
---

Behind a reverse proxy or load balancer, the connection the app sees comes from the proxy. `$req->ip` is then the proxy's address, and `$req->https` is false even when the client used HTTPS, because TLS ended at the proxy. The proxy reports the original values in `X-Forwarded-For` and `X-Forwarded-Proto`, but any client can send those headers too. Waypoint believes them only from the proxies listed in [`ProxyOptions`](../reference/Waypoint/Options/ProxyOptions.md):

```php
use Waypoint\Options\ProxyOptions;

$app->configure(function (ProxyOptions $proxies) {
    $proxies->trusted = ['127.0.0.1', '::1', '10.0.0.0/8'];
});
```

The list holds IPv4 and IPv6 addresses and CIDR ranges. An entry that is neither (`'localhost'`, a typo like `'10.0.0.0/'`) throws an `InvalidArgumentException` when assigned, instead of quietly trusting too much. Assign the whole list at once; appending with `[]` is not possible. The list is empty by default: nothing is trusted, and both headers are ignored.

## What changes for a trusted proxy

For a request whose connection comes from a trusted address:

- **`$req->ip`**: `X-Forwarded-For` is read from the right, where the nearest proxy appended the address it saw. Trusted addresses are skipped, and the first untrusted one is the client. With `10.0.0.0/8` trusted, a request from `10.0.0.5` carrying `X-Forwarded-For: 203.0.113.7, 10.0.0.9` has `$req->ip === '203.0.113.7'`. A value that is no IP address stops the walk.
- **`$req->https`**: the last value of `X-Forwarded-Proto`, `https` or `http`.

Requests from anywhere else keep the connection's address and scheme, whatever headers they send. This is applied before any middleware runs, so `SecurityHeadersMiddleware` sends HSTS for HTTPS requests behind the proxy, and logs, rate limits and audit trails see the client's address.

A check on the client's address therefore needs the proxy in the list. The bakery's shop sits behind a proxy on `10.0.0.2`, and a route middleware lets the stock be changed only from the bakery's own network:

```php
$app->configure(function (ProxyOptions $proxies) {
    $proxies->trusted = ['10.0.0.2'];
});
```

```php
final class BakeryNetworkOnly extends MiddlewareBase
{
    protected function before(Request $req, Response $res): bool
    {
        // The till's address from X-Forwarded-For, not the proxy's 10.0.0.2
        if (!str_starts_with($req->ip, '192.168.10.')) {
            throw new ForbiddenException('The stock can only be changed in the bakery.');
        }
        return true;
    }
}
```

Without the proxy in the list, every request would come from `10.0.0.2` and fail the check.

## Where the connection address comes from

| Runtime | `$req->ip` before the proxy rules |
|---|---|
| PHP-FPM, `php -S` | `$_SERVER['REMOTE_ADDR']` |
| Tenma | the peer address of the connection |
| PSR-15 handler | the server's `REMOTE_ADDR` server parameter |
| testing kit | empty |

Under Tenma, which speaks plain HTTP, `$req->https` is false unless a trusted proxy says otherwise. Under the PSR-15 handler it follows the scheme of the request URI the server built.

Trust only what really sits in front of the app. A range that also covers clients (a whole office network, `0.0.0.0/0`) lets them choose their own address.

See also: [`ProxyOptions`](../reference/Waypoint/Options/ProxyOptions.md), [`Request`](../reference/Waypoint/Http/Request.md), [Deployment](deployment.md).
