<?php

namespace App\Modules\People\Contracts;

use App\Modules\People\Data\IdentityClaim;
use Carbon\CarbonImmutable;

interface PersonIdentityDirectory
{
    public function create(
        IdentityClaim $identity,
        string $givenName,
        string $familyName,
    ): string;

    public function claimForAccount(
        IdentityClaim $identity,
        string $givenName,
        string $familyName,
        ?string $ownershipProof,
        CarbonImmutable $now,
    ): ?string;

    public function approveAccountLink(
        IdentityClaim $identity,
        CarbonImmutable $expiresAt,
    ): ?string;

    public function personIdForIdentity(IdentityClaim $identity): ?string;

    public function identityExists(IdentityClaim $identity): bool;

    public function rateLimitPseudonym(IdentityClaim $identity): string;
}
