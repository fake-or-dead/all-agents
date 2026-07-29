<?php

namespace App\Modules\CourseCatalog\Data;

final readonly class EligibilityResult
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public string $status,
        public string $code,
        public string $message,
        public array $reasons = [],
    ) {}
}
