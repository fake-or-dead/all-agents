<?php

namespace App\Modules\Audit\Infrastructure;

use App\Modules\Audit\Contracts\AuditLog;
use App\Modules\Audit\Data\AuditEvent;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseAuditLog implements AuditLog
{
    public function __construct(private ConnectionInterface $database) {}

    public function append(AuditEvent $event): void
    {
        $this->database->table('audit_events')->insert([
            'id' => $event->id,
            'actor_type' => $event->actorType,
            'actor_id' => $event->actorId,
            'action' => $event->action,
            'resource_type' => $event->resourceType,
            'resource_id' => $event->resourceId,
            'outcome' => $event->outcome,
            'correlation_id' => $event->correlationId,
            'context' => json_encode($event->context, JSON_THROW_ON_ERROR),
            'occurred_at' => $event->occurredAt,
        ]);
    }
}
