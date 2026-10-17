<?php

namespace Waypoint\Attributes;

use Attribute;

/**
 * On a controller or a route method: excluded from the generated OpenAPI spec. On a property (or a promoted
 * constructor parameter): left out of JSON and XML results, out of the property's OpenAPI component schema, and
 * never set from input by FromArray.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Ignore
{
}
