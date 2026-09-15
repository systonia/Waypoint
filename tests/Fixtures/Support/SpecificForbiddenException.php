<?php

namespace Waypoint\Tests\Fixtures\Support;

use Waypoint\Exceptions\ForbiddenException;

/**
 * A subclass of a registered exception type, never registered itself --
 * used to prove App::resolveExceptionHandler() falls back to matching by
 * parent class when there's no exact match.
 */
class SpecificForbiddenException extends ForbiddenException
{
}
