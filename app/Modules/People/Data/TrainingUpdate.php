<?php

namespace App\Modules\People\Data;

use Carbon\CarbonImmutable;

final readonly class TrainingUpdate
{
    public function __construct(
        public string $personId,
        public ?string $trainingId,
        public string $courseName,
        public string $providerName,
        public CarbonImmutable $startedOn,
        public ?CarbonImmutable $endedOn,
        public ?int $expectedVersion,
    ) {}
}
