<?php

namespace App\Modules\IdentityAccess\Infrastructure;

use App\Models\Account;
use App\Modules\IdentityAccess\Contracts\ApplicantIdentityResolver;
use App\Modules\IdentityAccess\Contracts\ApplicantOwnershipDirectory;
use App\Modules\IdentityAccess\Data\ApplicantIdentity;
use Illuminate\Http\Request;

final readonly class LaravelApplicantIdentityResolver implements ApplicantIdentityResolver
{
    public function __construct(private ApplicantOwnershipDirectory $ownership) {}

    public function resolve(Request $request): ?ApplicantIdentity
    {
        $account = $request->user();
        if (! $account instanceof Account) {
            return null;
        }

        return $this->ownership->activeApplicantForAccount(
            (string) $account->getAuthIdentifier(),
        );
    }
}
