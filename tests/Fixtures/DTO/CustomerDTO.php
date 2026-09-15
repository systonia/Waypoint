<?php

namespace Waypoint\Tests\Fixtures\DTO;

use Waypoint\Attributes\{Schema, Property};

#[Schema('Customer')]
class CustomerDTO
{
    #[Property(description: 'Full name', example: 'Ada Lovelace')]
    public string $name = '';

    // Nullable and defaulted properties must NOT end up in `required`.
    public ?string $nickname = null;

    public int $loyaltyPoints = 0;

    public AddressDTO $address;

    #[Property(deprecated: true, format: 'email')]
    public ?string $legacyContact = null;

    public function __construct(array $data = [])
    {
        $this->address = new AddressDTO($data['address'] ?? []);
        foreach ($data as $key => $value) {
            if ($key !== 'address' && property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}
