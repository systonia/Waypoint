<?php

namespace Waypoint\Tests\Fixtures\Plugins;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Waypoint\Container;
use Waypoint\Exceptions\ForbiddenException;
use Waypoint\Http\{Request, Response};
use Waypoint\Plugin\{ArgumentBinder, ClientAsset, Endpoint, Guard, PluginBase, Renderer, ResponseHook, RouteAttributeCompiler, ViewHelper};
use Waypoint\Tests\Fixtures\Support\CallTracker;

/** Exercises every hook type once. Routes carrying #[Tagged('x')] get plan['plugins']['echo'] = ['tag' => 'x']. */
final class EchoPlugin extends PluginBase implements Endpoint, RouteAttributeCompiler, Guard, ResponseHook, ArgumentBinder, Renderer, ViewHelper, ClientAsset
{
    public bool $booted = false;

    public function name(): string
    {
        return 'echo';
    }

    public function classes(): array
    {
        return [EchoPluginController::class];
    }

    public function middlewares(): array
    {
        return [function (Request $req, Response $res, callable $next): mixed {
            $res->withHeader('X-Echo-Middleware', 'yes');
            return $next($req, $res);
        }];
    }

    public function hooks(): array
    {
        return [$this];
    }

    public function cacheInputs(): array
    {
        return [__FILE__ => filemtime(__FILE__) ?: 0];
    }

    public function boot(Container $container): void
    {
        $this->booted = true;
    }

    public function serve(string $method, string $path, Request $req, Response $res): bool
    {
        if ($path !== '/echo/ping') {
            return false;
        }
        $res->withHeader('Content-Type', 'text/plain')->write("pong $method");
        return true;
    }

    public function compile(ReflectionClass $controller, ReflectionMethod $method): array
    {
        $attr = $method->getAttributes(Tagged::class)[0] ?? null;
        return $attr === null ? [] : ['tag' => $attr->newInstance()->tag];
    }

    public function check(array $plan, Request $req, Response $res): void
    {
        CallTracker::record('echo.guard:' . ($plan['tag'] ?? '-'));
        if (($plan['tag'] ?? null) === 'forbidden') {
            throw new ForbiddenException('tagged forbidden');
        }
    }

    public function after(array $plan, Request $req, Response $res): void
    {
        $res->withHeader('X-Echo-Tag', is_string($plan['tag'] ?? null) ? $plan['tag'] : 'none');
    }

    public function plan(ReflectionParameter $parameter): ?array
    {
        return $parameter->getAttributes(Shout::class) !== [] ? ['name' => $parameter->getName()] : null;
    }

    public function resolve(array $plan, Request $req, Response $res, array $routeParams): mixed
    {
        $name = is_string($plan['name'] ?? null) ? $plan['name'] : '';
        return strtoupper((string) $req->query($name));
    }

    public function supports(mixed $result): bool
    {
        return $result instanceof EchoResult;
    }

    public function render(mixed $result, Request $req, Response $res): void
    {
        $res->withHeader('Content-Type', 'text/echo')->write($result instanceof EchoResult ? $result->text : '');
    }

    public function helpers(): array
    {
        return ['shout' => fn(string $text): string => strtoupper($text)];
    }

    public function assets(): array
    {
        return ['echo.css' => __DIR__ . '/echo.css'];
    }
}
