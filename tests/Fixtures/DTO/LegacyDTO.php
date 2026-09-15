<?php

namespace Waypoint\Tests\Fixtures\DTO;

/**
 * The union-typed property is the point: OpenAPIGenerator must not fatal
 * trying to call ->getName() on it (only ReflectionNamedType has that).
 */
class LegacyDTO
{
    public string|int $identifier = '';

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}
