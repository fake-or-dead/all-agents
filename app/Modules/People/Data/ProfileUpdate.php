<?php

namespace App\Modules\People\Data;

final readonly class ProfileUpdate
{
    public function __construct(
        public string $personId,
        public string $givenName,
        public string $familyName,
        public ?string $email,
        public ?string $phone,
        public int $expectedVersion,
    ) {}
}
