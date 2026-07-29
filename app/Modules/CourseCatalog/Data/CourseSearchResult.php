<?php

namespace App\Modules\CourseCatalog\Data;

final readonly class CourseSearchResult
{
    /**
     * @param  list<array<string, mixed>>  $sessions
     * @param  list<array{id: string, label: string}>  $courseTypes
     * @param  list<array{id: string, label: string}>  $centers
     */
    public function __construct(
        public array $sessions,
        public array $courseTypes,
        public array $centers,
    ) {}
}
