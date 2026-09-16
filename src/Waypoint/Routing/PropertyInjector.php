<?php

namespace Waypoint\Routing;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use Waypoint\Attributes\Inject;
use Waypoint\Container;
use Waypoint\Http\{Request, Response};
use Waypoint\Router;
use Waypoint\Support\Arr;

/**
 * Sets #[Inject] properties. Two sources: a compiled propInject plan
 * (controllers, #[Middleware] classes, task managers -- reflected once by
 * RouteCompiler), or live reflection for objects user code constructs
 * itself (a View, an $app->use()d middleware, a discovered service).
 */
final class PropertyInjector
{
    /** Supplied per request by Router, never container-managed services. */
    public const PER_REQUEST = [Request::class, Response::class, Router::class];

    public function __construct(private ?Container $container, private ?Router $router = null)
    {
    }

    /** Wires a compiled {name, type} plan. Without $req/$res (a CLI task) Request/Response entries are skipped. */
    public function injectPlanned(object $target, mixed $propInject, ?Request $req = null, ?Response $res = null): void
    {
        foreach (Arr::listOfStringKeyed($propInject) as $entry) {
            $name = $entry['name'] ?? null;
            $type = $entry['type'] ?? null;
            if (!is_string($name) || $name === '' || !is_string($type)) {
                continue;
            }
            $value = match ($type) {
                Request::class => $req,
                Response::class => $res,
                Router::class => $this->router,
                default => $this->container !== null && class_exists($type) ? $this->container->get($type) : null,
            };
            if ($value !== null) {
                (new ReflectionProperty($target, $name))->setValue($target, $value);
            }
        }
    }

    /** Wires every #[Inject] property found by reflection, skipping per-request types and anything the container can't provide. */
    public function injectReflected(object $target): void
    {
        if ($this->container === null) {
            return;
        }
        foreach ((new ReflectionClass($target))->getProperties() as $prop) {
            if ($prop->getAttributes(Inject::class) === []) {
                continue;
            }
            $type = self::namedType($prop);
            if ($type === null || in_array($type, self::PER_REQUEST, true) || !class_exists($type) || !$this->container->has($type)) {
                continue;
            }
            $prop->setValue($target, $this->container->get($type));
        }
    }

    /** The declared single class/scalar type name, or null for untyped/union/intersection (nothing #[Inject] could resolve). */
    public static function namedType(ReflectionProperty|ReflectionParameter $member): ?string
    {
        $type = $member->getType();
        return $type instanceof ReflectionNamedType ? $type->getName() : null;
    }
}
