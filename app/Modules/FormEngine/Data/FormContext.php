<?php

namespace App\Modules\FormEngine\Data;

final readonly class FormContext
{
    public function __construct(
        public string $formKey,
        public string $courseSessionId,
        public string $courseTypeKey,
        public string $phase,
        public string $applicantIntent,
        public ?string $alumniEligibilityKey,
        public string $layMonasticCategory,
        public ?string $approvedCategory,
        public string $locale,
    ) {}
}
