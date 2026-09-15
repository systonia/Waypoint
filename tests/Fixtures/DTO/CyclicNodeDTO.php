<?php

namespace Waypoint\Tests\Fixtures\DTO;

/**
 * Self-referencing on purpose -- proves OpenAPIGenerator::generateModelSchema()
 * doesn't infinite-loop/stack-overflow on a cyclic model graph.
 */
class CyclicNodeDTO
{
    public string $label = '';

    public ?CyclicNodeDTO $parent = null;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}
