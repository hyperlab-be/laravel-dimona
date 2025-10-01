<?php

namespace Hyperlab\Dimona\Data;

use Hyperlab\Dimona\Enums\Country;

class EmploymentLocationData
{
    public function __construct(
        public readonly string $name,
        public readonly string $street,
        public readonly string $houseNumber,
        public readonly ?string $boxNumber,
        public readonly string $postalCode,
        public readonly string $place,
        public readonly Country $country,
    ) {}
}
