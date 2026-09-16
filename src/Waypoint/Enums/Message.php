<?php

namespace Waypoint\Enums;

// phpcs:disable Generic.Files.LineLength
enum Message: string
{
    /**
     * Replaces each {key} placeholder with the matching $vars value.
     * @param array<array-key, mixed> $vars
     */
    public function format(array $vars = []): string
    {
        $result = $this->value;
        foreach ($vars as $key => $value) {
            $replacement = match (true) {
                is_scalar($value) => (string) $value,
                // @codeCoverageIgnoreStart
                $value instanceof \Stringable => (string) $value,
                default => '',
                // @codeCoverageIgnoreEnd
            };
            $result = str_replace('{' . $key . '}', $replacement, $result);
        }
        return $result;
    }

    /**
     * format() with named arguments: Message::X->interpolate(method: $m, path: $p).
     * @param mixed ...$vars
     */
    public function interpolate(...$vars): string
    {
        return $this->format($vars);
    }

    #region Exceptions
    case Forbidden = "Forbidden";
    case NotFound = "Not Found";
    case Unauthorized = "Unauthorized";
    case ValidationFailed = "Validation failed";
    case CsrfTokenInvalid = "Invalid or missing CSRF token";
    #endregion

    #region OpenAPI
    case GeneratorPropertyDoesNotExist = 'OpenAPIGenerator: Property type "{property}" does not exist (property "{name}" in class "{rc}")';
    case GeneratorPropertyHasNoType = 'OpenAPIGenerator: Property "{name}" in class "{rc}" has no type.';
    case GeneratorClassDoesNotExist = 'OpenAPIGenerator: generateModelSchema - class "{fqcn}" does not exist.';
    case GeneratorPropertyDoesNotExist2 = 'OpenAPIGenerator: Parameter type "{type}" does not exist in method {method}.';
    #endregion

    #region Route Versioning
    case RouteUnversioned = 'RouteCompiler: {method} {path} has no #[Version] attribute -- served without a version prefix.';
    case RouteInvalidSunsetDate = 'RouteCompiler: {method} {path} has an invalid #[Sunset] date "{date}" (expected YYYY-MM-DD) -- no Sunset header will be sent for it.';
    #endregion
}
// phpcs:enable Generic.Files.LineLength