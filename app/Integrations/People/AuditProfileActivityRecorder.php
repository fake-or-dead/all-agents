<?php

namespace App\Integrations\People;

use App\Modules\Audit\Contracts\AuditLog;
use App\Modules\Audit\Data\AuditEvent;
use App\Modules\People\Contracts\ProfileActivityRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final readonly class AuditProfileActivityRecorder implements ProfileActivityRecorder
{
    public function __construct(private AuditLog $auditLog) {}

    public function record(
        string $accountId,
        string $personId,
        string $action,
        string $outcome,
        string $correlationId,
        array $context = [],
    ): void {
        $this->auditLog->append(new AuditEvent(
            id: (string) Str::uuid(),
            actorType: 'account',
            actorId: $accountId,
            action: $action,
            resourceType: 'person',
            resourceId: $personId,
            outcome: $outcome,
            correlationId: $correlationId,
            context: $context,
            occurredAt: CarbonImmutable::now(),
        ));
    }
}
