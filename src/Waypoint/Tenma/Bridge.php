<?php

namespace Waypoint\Tenma;

use Tenma\Http\{Request as TenmaRequest, Response as TenmaResponse, Stream, StreamedResponse, UploadedFile as TenmaUploadedFile, WebSocket, WebSocketResponse};
use Waypoint\App;
use Waypoint\Http\{Request, Response, UploadedFile};
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
        self::installStorage();
        return self::toTenma($app->handle(self::toWaypoint($request)));
    }

    /** Request id and CSRF token follow a request into the tasks it starts. Once per process, under Tenma only. */
    public static function installStorage(): void
    {
        if (!self::$storageInstalled) {
            FiberLocal::useStorage(fn () => new AsyncLocalStorage());
            self::$storageInstalled = true;
        }
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
            self::files($request->files()),
        );
    }

    /**
     * Tenma's uploads as Waypoint's; moveTo() stays Tenma's, which writes without blocking the worker.
     *
     * @param array<array-key, mixed> $files
     * @return array<array-key, mixed>
     */
    private static function files(array $files): array
    {
        $uploads = [];
        foreach ($files as $name => $file) {
            if ($file instanceof TenmaUploadedFile) {
                $uploads[$name] = new UploadedFile($file->name, $file->type, $file->size(), $file->contents(...), $file->moveTo(...));
            } elseif (is_array($file)) {
                $uploads[$name] = self::files($file);
            }
        }
        return $uploads;
    }

    /** A response handed over with Response::upgrade() goes to Tenma with the request's context; anything else is Tenma's plain Response. */
    public static function toTenma(Response $response): TenmaResponse|WebSocketResponse|StreamedResponse
    {
        $upgrade = $response->getUpgrade();
        // The handler runs in the request's task after App::handle() reset it: it gets the request's values back first
        $restore = $response->getContext() ?? static function (): void {};
        if ($upgrade instanceof WebSocketResponse) {
            return new WebSocketResponse(static function (WebSocket $socket) use ($upgrade, $restore): void {
                $restore();
                ($upgrade->handler)($socket);
            });
        }
        if ($upgrade instanceof StreamedResponse) {
            // The app's headers (CORS, security headers, X-Request-ID) and cookies go along; the stream's own win,
            // and those describing the body the stream replaces don't
            $headers = array_diff_key(self::headers($response), ['content-length' => true, 'content-type' => true, 'content-encoding' => true]);
            return new StreamedResponse(static function (Stream $stream) use ($upgrade, $restore): void {
                $restore();
                ($upgrade->writer)($stream);
            }, $upgrade->status, [...$headers, ...$upgrade->headers]);
        }
        if ($upgrade !== null) {
            throw new \LogicException('Response::upgrade() got a ' . get_class($upgrade) . ', Tenma takes only Response::websocket(), stream() or events()');
        }
        return new TenmaResponse($response->getBody(), $response->getStatus(), self::headers($response));
    }

    /** @return array<string, string|list<string>> lowercase names, the cookies as set-cookie */
    private static function headers(Response $response): array
    {
        $headers = [];
        foreach ($response->getHeaders() as $name => $value) {
            $headers[strtolower($name)] = $value;
        }
        $cookies = $response->getCookies();
        if ($cookies !== []) {
            $headers['set-cookie'] = $cookies;
        }
        return $headers;
    }
}
