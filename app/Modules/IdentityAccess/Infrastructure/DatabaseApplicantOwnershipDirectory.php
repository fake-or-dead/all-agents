<?php

namespace App\Modules\IdentityAccess\Infrastructure;

use App\Modules\IdentityAccess\Contracts\ApplicantOwnershipDirectory;
use App\Modules\IdentityAccess\Data\ApplicantIdentity;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DatabaseApplicantOwnershipDirectory implements ApplicantOwnershipDirectory
{
    public function __construct(private ConnectionInterface $database) {}

    public function activeApplicantForAccount(string $accountId): ?ApplicantIdentity
    {
        if (! Str::isUuid($accountId)) {
            return null;
        }

        $personId = $this->database
            ->table('accounts')
            ->where('id', $accountId)
            ->where('status', 'active')
            ->value('person_id');

        if (! is_string($personId) || ! Str::isUuid($personId)) {
            return null;
        }

        return new ApplicantIdentity($accountId, $personId);
    }
}
