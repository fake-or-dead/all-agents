<?php

namespace App\Modules\CourseCatalog\Data;

final readonly class EligibilityContext
{
    public function __construct(
        public ?int $age,
        public ?string $category,
        public ?string $applicantType,
        public ?string $actorId,
    ) {}
}
