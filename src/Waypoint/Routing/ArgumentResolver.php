<?php

namespace Waypoint\Routing;

use Waypoint\Exceptions\ValidationException;
use Waypoint\Http\{Request, Response};
use Waypoint\Validator;

/** Turns a compiled argPlan (see RouteCompiler::buildArgPlan()) into the actual argument list for a route method call. */
final class ArgumentResolver
{
    /**
     * @param list<array<string, mixed>> $argPlan
     * @param array<string, string> $params Route placeholder values.
     * @return list<mixed>
     */
    public static function resolve(array $argPlan, Request $req, Response $res, array $params): array
    {
        $args = [];
        foreach ($argPlan as $arg) {
            $name = $arg['name'] ?? '';
            $name = is_string($name) ? $name : '';
            $args[] = match ($arg['inject'] ?? null) {
                'Request' => $req,
                'Response' => $res,
                'Route' => $params[$name] ?? null,
                'Query' => $req->query($name),
                'Body' => self::body($arg, $req),
                'BodyCollection' => self::bodyCollection($arg, $req),
                'Scalar' => self::scalar($arg, $name, $req, $params),
                default => null,
            };
        }
        return $args;
    }

    /** @param array<string, mixed> $arg */
    private static function body(array $arg, Request $req): ?object
    {
        $class = $arg['class'] ?? null;
        if (!is_string($class) || !class_exists($class)) {
            // @codeCoverageIgnoreStart
            // RouteCompiler only emits 'Body' after its own class_exists() check.
            return null;
            // @codeCoverageIgnoreEnd
        }
        $dto = new $class($req->body());
        $req->bodyDto = $dto;
        if ($arg['validate'] ?? false) {
            $errors = (new Validator())->validate($dto);
            if ($errors) {
                throw new ValidationException('Validation failed', $errors);
            }
        }
        return $dto;
    }

    /**
     * @param array<string, mixed> $arg
     * @return list<object>
     */
    private static function bodyCollection(array $arg, Request $req): array
    {
        $class = $arg['class'] ?? null;
        if (!is_string($class) || !class_exists($class)) {
            // @codeCoverageIgnoreStart
            // same guarantee as body().
            return [];
            // @codeCoverageIgnoreEnd
        }
        $validator = ($arg['validate'] ?? false) ? new Validator() : null;
        $items = [];
        $errors = [];
        foreach ($req->body() as $index => $item) {
            $dto = new $class($item);
            if ($validator !== null) {
                $itemErrors = $validator->validate($dto);
                if ($itemErrors) {
                    $errors["$index"] = $itemErrors;
                }
            }
            $items[] = $dto;
        }
        if ($errors) {
            throw new ValidationException('Validation failed', $errors);
        }
        return $items;
    }

    /**
     * An unattributed scalar parameter: route placeholder first, then query string, cast to the declared type.
     * @param array<string, mixed> $arg
     * @param array<string, string> $params
     */
    private static function scalar(array $arg, string $name, Request $req, array $params): mixed
    {
        $value = $params[$name] ?? $req->query($name);
        $type = $arg['type'] ?? null;
        if (is_string($type)) {
            settype($value, $type);
        }
        return $value;
    }
}
