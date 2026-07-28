<?php

namespace App\Modules\IdentityAccess\Contracts;

interface VerificationGateway
{
    public function issueVerificationCode(string $email, string $challengeId): string;

    public function deliverRecoveryLink(string $email, string $token): void;
}
