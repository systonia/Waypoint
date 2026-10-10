<?php

namespace Waypoint\Nope;

use Nope\Http\{Request as NopeRequest, Response as NopeResponse};
use Waypoint\App;
use Waypoint\Http\{Request, Response};
use Waypoint\Support\FiberLocal;

/**
 * Runs a Waypoint app under nope (PHP with a Node-style event loop): one process serves many requests,
 * each in a Fiber of its own, interleaved while they wait on I/O. Only loaded under nope; Waypoint has
 * no dependency on it.
 *
 *   if (function_exists('nope_listen')) {
 *       Waypoint\Nope\Server::serve($app, 8080, static: ['/assets' => __DIR__ . '/public/assets']);
 *   } else {
 *       $app->run();
 *   }
 */
final class Server
{
    /**
     * Starts nope's HTTP server for $app. $static is handed to Nope\Http\serve(): files under those URL
     * prefixes are served by nope itself, without PHP.
     *
     * @param string|array<string, string>|null $static
     */
    public static function serve(App $app, int $port, string|array|null $static = null): void
    {
        // Request id and CSRF token follow a request into the tasks it starts
        FiberLocal::useStorage(fn () => new AsyncLocalStorage());
        \Nope\Http\serve($port, fn (NopeRequest $request): NopeResponse => self::respond($app, $request), $static);
    }

    /** One request: nope's Request in, through App::handle(), nope's Response out. */
    public static function respond(App $app, NopeRequest $request): NopeResponse
    {
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
