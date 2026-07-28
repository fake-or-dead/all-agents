<?php

namespace App\Modules\People\Contracts;

interface PersonIdentityDirectory
{
    public function create(
        string $type,
        string $countryCode,
        string $normalizedIdentifier,
        string $lookupDigest,
        string $givenName,
        string $familyName,
    ): string;

    public function personIdForIdentifier(string $lookupDigest): ?string;

    public function identifierExists(string $lookupDigest): bool;
}
