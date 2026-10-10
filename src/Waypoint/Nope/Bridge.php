<?php

namespace Waypoint\Nope;

use Nope\Http\{Request as NopeRequest, Response as NopeResponse};
use Waypoint\App;
use Waypoint\Http\{Request, Response};
use Waypoint\Support\FiberLocal;

/**
 * Translates between nope (PHP with a Node-style event loop) and Waypoint for App::__invoke(), which makes
 * an app nope's request handler: `Nope\Http\serve(3000, $app)`. One process then serves many requests,
 * each in a Fiber of its own, interleaved while they wait on I/O. Only used under nope; Waypoint has no
 * dependency on it.
 */
final class Bridge
{
    private static bool $storageInstalled = false;

    /** One request: nope's Request in, through App::handle(), nope's Response out. */
    public static function respond(App $app, NopeRequest $request): NopeResponse
    {
        if (!self::$storageInstalled) {
            // Request id and CSRF token follow a request into the tasks it starts
            FiberLocal::useStorage(fn () => new AsyncLocalStorage());
            self::$storageInstalled = true;
        }
        return self::toNope($app->handle(self::toWaypoint($request)));
    }

    public static function toWaypoint(NopeRequest $request): Request
    {
        $type = $request->header('content-type') ?? '';
        $cookies = array_filter($request->cookies(), fn (mixed $value): bool => is_string($value));
        return Request::fromParts(
            $request->method,
            $request->uri,
            $request->headers,
            $request->body,
            $cookies,
            // nope speaks plain HTTP; TLS ends at a proxy, whose X-Forwarded-Proto isn't trusted
            false,
            // multipart is parsed by nope, a urlencoded or JSON body by fromParts() itself
            stripos($type, 'multipart/form-data') === 0 ? $request->form() : null,
        );
    }

    public static function toNope(Response $response): NopeResponse
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $value) {
            $headers[strtolower($name)] = $value;
        }
        $cookies = $response->getCookies();
        if ($cookies !== []) {
            $headers['set-cookie'] = $cookies;
        }
        return new NopeResponse($response->getBody(), $response->getStatus(), $headers);
    }
}
