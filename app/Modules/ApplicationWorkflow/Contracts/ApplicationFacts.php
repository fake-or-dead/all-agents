<?php

namespace App\Modules\ApplicationWorkflow\Contracts;

interface ApplicationFacts
{
    public function state(string $courseSessionId, ?string $personId): string;
}
