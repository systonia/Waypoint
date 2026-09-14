<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * Marks a property or constructor parameter to be resolved from the DI
 * container. Declared for TARGET_PROPERTY/TARGET_PARAMETER because that's
 * how it's actually used throughout the framework (Router::collectPropertyInjections,
 * App::discoverAllClasses); it was previously declared for TARGET_CLASS/TARGET_METHOD,
 * which meant PHP would fatal on ->newInstance() for every real usage.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Inject
{
}
