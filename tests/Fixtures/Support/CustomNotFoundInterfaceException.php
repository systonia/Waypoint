<?php

namespace Waypoint\Tests\Fixtures\Support;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Implements the same interface Waypoint\Exceptions\NotFoundException does,
 * without extending it or being separately registered -- used to prove
 * App::resolveExceptionHandler() falls back to matching by interface.
 */
class CustomNotFoundInterfaceException extends Exception implements NotFoundExceptionInterface
{
}
