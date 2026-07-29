<?php

namespace App\Modules\CourseCatalog\Data;

final readonly class CourseSearch
{
    /**
     * @param  array<string, string>  $errors
     */
    public function __construct(
        public ?int $year,
        public ?int $month,
        public ?string $courseType,
        public ?string $center,
        public array $errors = [],
    ) {}
}
