<?php

namespace Waypoint\Psr;

use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface, StreamFactoryInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Tenma\Http\{StreamedResponse, WebSocketResponse};
use Tenma\Psr\Upgrade;
use Waypoint\App;
use Waypoint\Http\{Request, Response};
use Waypoint\Support\Arr;
use Waypoint\Tenma\Bridge;

/**
 * The app as a PSR-15 handler, for any server that speaks PSR-7: `new Handler($app, new Psr17Factory())`.
 * Under Tenma through tenma/psr, under RoadRunner, under FrankenPHP with nyholm/psr7-server, or
 * behind PSR-15 middlewares of other packages. `Tenma::serve(3000, $app)` stays the direct way under Tenma.
 *
 * Response::upgrade() needs Tenma and tenma/psr: it goes out as Tenma\Psr\Upgrade.
 */
final class Handler implements RequestHandlerInterface
{
    public function __construct(private readonly App $app, private readonly ResponseFactoryInterface&StreamFactoryInterface $factory)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (class_exists(\Tenma::class, false)) {
            Bridge::installStorage();
        }
        $response = $this->app->handle(self::toWaypoint($request));
        $psr = $this->toPsr($response);
        if ($response->getUpgrade() === null) {
            return $psr;
        }
        // Bridge wraps the handler with the request's context and gives a stream the app's headers
        $upgrade = Bridge::toTenma($response);
        if (!class_exists(Upgrade::class) || !($upgrade instanceof WebSocketResponse || $upgrade instanceof StreamedResponse)) {
            throw new \LogicException('Response::upgrade() through PSR-15 needs Tenma and tenma/psr');
        }
        return new Upgrade($psr, $upgrade);
    }

    public static function toWaypoint(ServerRequestInterface $request): Request
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[(string) $name] = implode(', ', $values);
        }
        $uri = $request->getUri();
        $query = $uri->getQuery();
        $cookies = array_filter($request->getCookieParams(), fn (mixed $value, mixed $name): bool => is_string($name) && is_string($value), ARRAY_FILTER_USE_BOTH);
        $parsed = $request->getParsedBody();
        $req = Request::fromParts(
            $request->getMethod(),
            ($uri->getPath() !== '' ? $uri->getPath() : '/') . ($query !== '' ? "?$query" : ''),
            $headers,
            (string) $request->getBody(),
            $cookies,
            // Whatever the server put into the URI; a proxy's X-Forwarded-Proto it only trusts if told to
            $uri->getScheme() === 'https',
            is_array($parsed) ? $parsed : null,
        );
        // The query params, not the query string again: a middleware may have changed them
        $req->query = Arr::stringKeyed($request->getQueryParams());
        return $req;
    }

    private function toPsr(Response $response): ResponseInterface
    {
        $psr = $this->factory->createResponse($response->getStatus());
        foreach ($response->getHeaders() as $name => $value) {
            $psr = $psr->withHeader($name, $value);
        }
        foreach ($response->getCookies() as $cookie) {
            $psr = $psr->withAddedHeader('Set-Cookie', $cookie);
        }
        return $psr->withBody($this->factory->createStream($response->getBody()));
    }
}
