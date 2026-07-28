<?php

namespace App\Modules\Audit\Data;

use Carbon\CarbonImmutable;

final readonly class AuditEvent
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public function __construct(
        public string $id,
        public string $actorType,
        public string $actorId,
        public string $action,
        public string $resourceType,
        public string $resourceId,
        public string $outcome,
        public string $correlationId,
        public array $context,
        public CarbonImmutable $occurredAt,
    ) {}
}
