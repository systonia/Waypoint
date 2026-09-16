<?php

namespace Waypoint\Exceptions;

use Exception;
use Psr\Container\ContainerExceptionInterface;

/** Thrown by Container::get() when a service's constructor fails. */
class ContainerException extends Exception implements ContainerExceptionInterface
{
}
