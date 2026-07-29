<?php

namespace App\Modules\People\Data;

final readonly class AddressUpdate
{
    public function __construct(
        public string $personId,
        public string $addressLine1,
        public ?string $addressLine2,
        public string $provinceId,
        public string $amphoeId,
        public string $tambonId,
        public int $expectedVersion,
    ) {}
}
