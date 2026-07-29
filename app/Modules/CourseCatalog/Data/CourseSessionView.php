<?php

namespace App\Modules\CourseCatalog\Data;

final readonly class CourseSessionView
{
    /**
     * @param  array<string, mixed>  $session
     */
    public function __construct(
        public array $session,
        public EligibilityResult $eligibility,
    ) {}
}
