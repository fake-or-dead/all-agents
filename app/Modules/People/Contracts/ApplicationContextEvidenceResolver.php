<?php

namespace App\Modules\People\Contracts;

use App\Modules\People\Data\ApplicationContextEvidenceResolution;
use Carbon\CarbonImmutable;

interface ApplicationContextEvidenceResolver
{
    public function resolveForPerson(
        string $personId,
        CarbonImmutable $at,
    ): ApplicationContextEvidenceResolution;
}
