<?php

namespace App\Integrations\IdentityAccess;

use App\Modules\Audit\Contracts\AuditLog;
use App\Modules\Audit\Data\AuditEvent;
use App\Modules\IdentityAccess\Contracts\SecurityEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class AuditSecurityEventRecorder implements SecurityEventRecorder
{
    public function __construct(private AuditLog $auditLog) {}

    public function record(
        string $actorType,
        string $actorId,
        string $action,
        string $resourceType,
        string $resourceId,
        string $outcome,
        string $correlationId,
        array $context = [],
    ): void {
        $this->auditLog->append(new AuditEvent(
            id: (string) Str::uuid(),
            actorType: $actorType,
            actorId: $actorId,
            action: $action,
            resourceType: $resourceType,
            resourceId: $resourceId,
            outcome: $outcome,
            correlationId: $correlationId,
            context: $context,
            occurredAt: CarbonImmutable::now(),
        ));
    }
}
