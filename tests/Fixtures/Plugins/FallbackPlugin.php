<?php

namespace Waypoint\Tests\Fixtures\Plugins;

use Waypoint\Http\{Request, Response};
use Waypoint\Plugin\{Fallback, PluginBase, ResponseHook};

/** Answers /pages/... when no route did, and marks every response its hook sees. */
final class FallbackPlugin extends PluginBase implements Fallback, ResponseHook
{
    public function name(): string
    {
        return 'fallback';
    }

    public function hooks(): array
    {
        return [$this];
    }

    public function serve(string $method, string $path, Request $req, Response $res): bool
    {
        if (!str_starts_with($path, '/pages/')) {
            return false;
        }
        $res->withHeader('Content-Type', 'text/plain')->write("page $path");
        return true;
    }

    public function after(array $plan, Request $req, Response $res): void
    {
        $res->withHeader('X-Hooked', 'yes');
    }
}
