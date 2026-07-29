<?php

namespace App\Modules\IdentityAccess\Infrastructure;

use App\Modules\IdentityAccess\Contracts\ApplicantIdentityResolver;
use App\Modules\IdentityAccess\Data\ApplicantIdentity;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class LaravelApplicantIdentityResolver implements ApplicantIdentityResolver
{
    public function resolve(Request $request): ?ApplicantIdentity
    {
        $account = $request->user();
        if ($account === null) {
            return null;
        }

        if (data_get($account, 'identity_type') !== 'applicant') {
            return null;
        }

        $personId = data_get($account, 'person_id');
        if (! is_string($personId) || ! Str::isUuid($personId)) {
            return null;
        }

        return new ApplicantIdentity(
            (string) $account->getAuthIdentifier(),
            $personId,
        );
    }
}
