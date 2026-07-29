<?php

namespace App\Modules\IdentityAccess\Contracts;

interface LocalVerificationMailbox
{
    public function latestRecoveryPathFor(string $email): ?string;
}
