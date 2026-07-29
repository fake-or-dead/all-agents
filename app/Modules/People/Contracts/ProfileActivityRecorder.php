<?php

namespace App\Modules\People\Contracts;

interface ProfileActivityRecorder
{
    /** @param array<string, scalar|null> $context */
    public function record(
        string $accountId,
        string $personId,
        string $action,
        string $outcome,
        string $correlationId,
        array $context = [],
    ): void;
}
