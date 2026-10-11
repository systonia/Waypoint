<?php

namespace Waypoint\Tenma;

use Tenma\Http\{Request as TenmaRequest, Response as TenmaResponse, StreamedResponse, WebSocketResponse};
use Waypoint\App;
use Waypoint\Http\{Request, Response};
use Waypoint\Support\FiberLocal;

/**
 * Translates between Tenma (PHP with a Node-style event loop) and Waypoint for App::__invoke(), which makes
 * an app Tenma's request handler: `Tenma::serve(3000, $app)`. One process then serves many requests,
 * each in a Fiber of its own, interleaved while they wait on I/O. Only used under Tenma; Waypoint has no
 * dependency on it.
 */
final class Bridge
{
    private static bool $storageInstalled = false;

    /** One request: Tenma's Request in, through App::handle(), Tenma's Response out. */
    public static function respond(App $app, TenmaRequest $request): TenmaResponse|WebSocketResponse|StreamedResponse
    {
        if (!self::$storageInstalled) {
            // Request id and CSRF token follow a request into the tasks it starts
            FiberLocal::useStorage(fn () => new AsyncLocalStorage());
            self::$storageInstalled = true;
        }
        return self::toTenma($app->handle(self::toWaypoint($request)));
    }

    public static function toWaypoint(TenmaRequest $request): Request
    {
        $type = $request->header('content-type') ?? '';
        $cookies = array_filter($request->cookies(), fn (mixed $value): bool => is_string($value));
        return Request::fromParts(
            $request->method,
            $request->uri,
            $request->headers,
            $request->body,
            $cookies,
            // Tenma speaks plain HTTP; TLS ends at a proxy, whose X-Forwarded-Proto isn't trusted
            false,
            // multipart is parsed by Tenma, a urlencoded or JSON body by fromParts() itself
            stripos($type, 'multipart/form-data') === 0 ? $request->form() : null,
        );
    }

    /** A response handed over with Response::upgrade() goes to Tenma as it is; anything else is Tenma's plain Response. */
    public static function toTenma(Response $response): TenmaResponse|WebSocketResponse|StreamedResponse
    {
        $upgrade = $response->getUpgrade();
        if ($upgrade instanceof WebSocketResponse || $upgrade instanceof StreamedResponse) {
            return $upgrade;
        }
        if ($upgrade !== null) {
            throw new \LogicException('Response::upgrade() got a ' . get_class($upgrade) . ', Tenma takes only Response::websocket(), stream() or events()');
        }
        $headers = [];
        foreach ($response->getHeaders() as $name => $value) {
            $headers[strtolower($name)] = $value;
        }
        $cookies = $response->getCookies();
        if ($cookies !== []) {
            $headers['set-cookie'] = $cookies;
        }
        return new TenmaResponse($response->getBody(), $response->getStatus(), $headers);
    }
}
