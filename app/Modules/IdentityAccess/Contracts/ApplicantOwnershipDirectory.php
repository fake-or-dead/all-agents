<?php

namespace App\Modules\IdentityAccess\Contracts;

use App\Modules\IdentityAccess\Data\ApplicantIdentity;

interface ApplicantOwnershipDirectory
{
    public function activeApplicantForAccount(string $accountId): ?ApplicantIdentity;
}
