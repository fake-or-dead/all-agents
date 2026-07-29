<?php

namespace App\Modules\IdentityAccess\Data;

final readonly class ApplicantIdentity
{
    public function __construct(
        public string $accountId,
        public string $personId,
    ) {}
}
