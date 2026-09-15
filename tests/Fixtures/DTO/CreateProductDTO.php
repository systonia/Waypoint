<?php

namespace Waypoint\Tests\Fixtures\DTO;

use Waypoint\Attributes\{NotBlank, Email, Length, Regex};

class CreateProductDTO
{
    #[NotBlank]
    public string $name = '';

    #[Email]
    public ?string $contactEmail = null;

    #[Length(min: 2, max: 12)]
    #[Regex(pattern: '/^[A-Z0-9\-]+$/')]
    public string $sku = '';

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}
