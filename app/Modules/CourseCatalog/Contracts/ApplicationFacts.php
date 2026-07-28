<?php

namespace App\Modules\CourseCatalog\Contracts;

interface ApplicationFacts
{
    public function state(string $courseSessionId, ?string $actorId): string;
}
