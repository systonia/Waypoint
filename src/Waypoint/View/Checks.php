<?php

namespace Waypoint\View;

/**
 * What a package adds to `vendor/bin/waypoint-check`: its own templates, checked without running the app. The
 * package names its class in its composer.json, under `extra.waypoint.checks`; the check creates it with the
 * project's directory:
 *
 *     "extra": { "waypoint": { "checks": "Waypoint\\Live\\TemplateChecks" } }
 */
interface Checks
{
    public function __construct(string $root);

    /**
     * What the package finds in its templates without PHPStan: syntax, names, wiring.
     *
     * @return list<array{file: string, line: int, message: string, severity: 'error'|'warning'}>
     */
    public function problems(): array;

    /**
     * Writes its templates into $directory as the PHP PHPStan reads, each line where the template has it, and
     * leaves out those with an error problems() reports.
     *
     * @return array<string, string> generated file => template file
     */
    public function generate(string $directory): array;
}
