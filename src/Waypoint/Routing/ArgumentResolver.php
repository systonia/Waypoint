<?php

namespace Waypoint\Routing;

use Waypoint\Enums\Message;
use Waypoint\Exceptions\{HttpException, UnauthorizedException, ValidationException};
use Waypoint\Http\{Request, Response};
use Waypoint\Plugin\ArgumentBinder;
use Waypoint\FromArray;
use Waypoint\Validator;

/** Turns a compiled argPlan (see RouteCompiler::buildArgPlan()) into the actual argument list for a route method call. */
final class ArgumentResolver
{
    /** @param array<string, ArgumentBinder> $binders plugin name => binder, for 'plugin' entries */
    public function __construct(private array $binders = [])
    {
    }

    /**
     * @param list<array<string, mixed>> $argPlan
     * @param array<string, string> $params Route placeholder values.
     * @return list<mixed>
     */
    public function resolve(array $argPlan, Request $req, Response $res, array $params): array
    {
        $args = [];
        foreach ($argPlan as $arg) {
            $name = $arg['name'] ?? '';
            $name = is_string($name) ? $name : '';
            $args[] = match ($arg['inject'] ?? null) {
                'Request' => $req,
                'Response' => $res,
                'Route' => self::typed($arg, $name, $params[$name] ?? null),
                'Query' => self::typed($arg, $name, $req->query($name)),
                'Body' => self::body($arg, self::parsed($req)),
                'BodyCollection' => self::bodyCollection($arg, self::parsed($req)),
                'BodyArray' => self::parsed($req)->body,
                'Claims' => self::claims($arg, $req),
                'Scalar' => self::scalar($arg, $name, $req, $params),
                'plugin' => $this->plugin($arg, $req, $res, $params),
                default => null,
            };
        }
        return $args;
    }

    /**
     * @param array<string, mixed> $arg
     * @param array<string, string> $params
     */
    private function plugin(array $arg, Request $req, Response $res, array $params): mixed
    {
        $plugin = $arg['plugin'] ?? null;
        $binder = is_string($plugin) ? ($this->binders[$plugin] ?? null) : null;
        // A cache compiled with a plugin that is no longer registered binds null, like any unknown parameter.
        return $binder?->resolve($arg, $req, $res, $params);
    }

    /** $req, unless its JSON body did not parse: a 400, not a DTO built from nothing. */
    private static function parsed(Request $req): Request
    {
        if ($req->bodyError !== null) {
            throw new HttpException(400, 'Bad Request', "Malformed JSON body: {$req->bodyError}.");
        }
        return $req;
    }

    /**
     * The JWT claims as the parameter's DTO; a 401 without a token, or when its claims don't fill the DTO, unless the
     * parameter is nullable.
     * @param array<string, mixed> $arg
     */
    private static function claims(array $arg, Request $req): ?object
    {
        $class = $arg['class'] ?? null;
        $optional = ($arg['nullable'] ?? false) === true;
        if ($req->jwt === null || !is_string($class) || !class_exists($class)) {
            return $optional ? null : throw new UnauthorizedException();
        }
        $claims = new $class($req->jwt);
        if (self::errors($claims, new Validator()) !== []) {
            return $optional ? null : throw new UnauthorizedException();
        }
        return $claims;
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
            $errors = self::errors($dto, new Validator());
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
        $body = $req->body();
        if (!array_is_list($body)) {
            throw new ValidationException(Message::ValidationFailed, ['body' => 'Expected a list.']);
        }
        $validator = ($arg['validate'] ?? false) ? new Validator() : null;
        $items = [];
        $errors = [];
        foreach ($body as $index => $item) {
            if (!is_array($item)) {
                $errors["$index"] = ['item' => 'Expected an object.'];
                continue;
            }
            $dto = new $class($item);
            if ($validator !== null) {
                $itemErrors = self::errors($dto, $validator);
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
     * A FromArray DTO also knows what didn't fit its types. Any other DTO's own $errors is no verdict: it may be a
     * default or a value the client sent, and the rules would never run.
     * @internal Router asks it too, for a View answering an invalid #[Body(validate: false)] DTO.
     * @return array<array-key, mixed>
     */
    public static function errors(object $dto, Validator $validator = new Validator()): array
    {
        $traits = array_merge(class_uses($dto), ...array_map(class_uses(...), array_values(class_parents($dto))));
        return in_array(FromArray::class, $traits, true) && property_exists($dto, 'errors') && is_array($dto->errors) ? $dto->errors : $validator->validate($dto);
    }

    /**
     * An unattributed scalar parameter: route placeholder first, then query string, cast to the declared type.
     * @param array<string, mixed> $arg
     * @param array<string, string> $params
     */
    private static function scalar(array $arg, string $name, Request $req, array $params): mixed
    {
        return self::typed($arg, $name, $params[$name] ?? $req->query($name));
    }

    /**
     * A value from the URL for a scalar parameter: converted to its type, a 422 if it is none. Missing: the
     * parameter's default, null if it takes null, otherwise the type's empty value. Without a scalar type (an
     * untyped #[Query], or an older cache) the value as it came.
     * @param array<string, mixed> $arg
     */
    private static function typed(array $arg, string $name, mixed $value): mixed
    {
        $type = $arg['type'] ?? null;
        if (!is_string($type)) {
            return $value;
        }
        if ($value === null) {
            if (array_key_exists('default', $arg)) {
                return $arg['default'];
            }
            if ($arg['nullable'] ?? false) {
                return null;
            }
            if (isset($arg['enum'])) {
                throw new ValidationException(Message::ValidationFailed, [$name => 'This value is required.']);
            }
            settype($value, $type);
            return $value;
        }
        $backing = $arg['enum'] ?? null;
        if (is_string($backing)) {
            return self::enumCase($type, $backing, $name, $value);
        }
        $converted = is_string($value) ? self::convertScalar($value, $type) : null;
        if ($converted === null) {
            throw new ValidationException(Message::ValidationFailed, [$name => "Expected a value of type {$type}."]);
        }
        return $converted;
    }

    /** The case of backed enum $type whose value $value names; a 422 listing the values otherwise. */
    private static function enumCase(string $type, string $backing, string $name, mixed $value): \BackedEnum
    {
        if (!is_subclass_of($type, \BackedEnum::class)) {
            // @codeCoverageIgnoreStart
            // RouteCompiler only sets 'enum' for a backed enum.
            throw new ValidationException(Message::ValidationFailed, [$name => "Expected a value of type {$type}."]);
            // @codeCoverageIgnoreEnd
        }
        $raw = is_string($value) ? self::convertScalar($value, $backing) : null;
        $case = is_int($raw) || is_string($raw) ? $type::tryFrom($raw) : null;
        if ($case === null) {
            $values = implode(', ', array_map(static fn (\BackedEnum $c): string => (string) $c->value, $type::cases()));
            throw new ValidationException(Message::ValidationFailed, [$name => "This value should be one of: {$values}."]);
        }
        return $case;
    }

    /** A text from the URL as int, float, bool or string; null when it isn't one ("false" is false, "abc" is no int). */
    private static function convertScalar(string $value, string $type): int|float|bool|string|null
    {
        return match ($type) {
            'int' => filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            default => $value,
        };
    }
}
