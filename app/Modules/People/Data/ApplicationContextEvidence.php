<?php

namespace App\Modules\People\Data;

use Carbon\CarbonImmutable;

final readonly class ApplicationContextEvidence
{
    public function __construct(
        public string $personId,
        public int $version,
        public CarbonImmutable $birthDate,
        public string $approvedCategory,
        public string $layMonasticCategory,
        public string $provenance,
        public CarbonImmutable $effectiveAt,
        public ?CarbonImmutable $staleAt,
    ) {}
}
