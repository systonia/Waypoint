<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Marks a route as scheduled for removal on a given date, e.g.
 * #[Sunset(date: '2026-12-31')] -- RouteCompiler turns $date into the
 * RFC 8594 `Sunset` response header (an HTTP-date) once at compile time.
 * Usable on a controller class (default for every route on it) and/or a
 * route method (overrides the class's date for that one route) -- same
 * "method wins over class" override RouteCompiler already applies to
 * #[Version]. Carries no opinion about deprecation itself -- pair it with
 * PHP's own native #[\Deprecated] to also send the `Deprecation` header.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Sunset
{
    /**
     * @var string
     */
    public string $date;

    /**
     * @param string $date 'YYYY-MM-DD'.
     */
    public function __construct(string $date)
    {
        $this->date = $date;
    }
}
