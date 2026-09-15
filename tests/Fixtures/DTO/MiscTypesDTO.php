<?php

namespace Waypoint\Tests\Fixtures\DTO;

class MiscTypesDTO
{
    public float $weight = 0.0;

    public bool $active = true;

    public array $tags = [];

    public mixed $anything = null;

    // Non-public: generateModelSchema() must skip it entirely.
    protected string $internalNote = '';

    // A type name that genuinely doesn't resolve to any class/interface/enum --
    // PHP only checks a property type against a real class when a value is
    // actually assigned, so declaring (and reflecting on) this is legal.
    public GhostClassThatDoesNotExist $ghost;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}
