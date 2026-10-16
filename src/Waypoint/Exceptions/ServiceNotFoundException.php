<?php

namespace Waypoint\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

/** Thrown by Container::get() for a service it has not registered and cannot construct: a logged 500, never a 404 naming the class. */
class ServiceNotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
