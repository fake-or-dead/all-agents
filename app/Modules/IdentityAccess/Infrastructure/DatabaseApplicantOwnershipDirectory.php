<?php

namespace App\Modules\IdentityAccess\Infrastructure;

use App\Modules\IdentityAccess\Contracts\ApplicantOwnershipDirectory;
use App\Modules\IdentityAccess\Data\ApplicantIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DatabaseApplicantOwnershipDirectory implements ApplicantOwnershipDirectory
{
    public function activeApplicantForAccount(string $accountId): ?ApplicantIdentity
    {
        if (! Str::isUuid($accountId)) {
            return null;
        }

        $personId = DB::table('applicant_account_ownerships')
            ->where('account_id', $accountId)
            ->where('account_status', 'active')
            ->where('identity_role', 'applicant')
            ->value('person_id');

        if (! is_string($personId) || ! Str::isUuid($personId)) {
            return null;
        }

        return new ApplicantIdentity($accountId, $personId);
    }
}
