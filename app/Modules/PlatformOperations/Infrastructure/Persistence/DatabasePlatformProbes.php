<?php

namespace App\Modules\PlatformOperations\Infrastructure\Persistence;

use App\Modules\Audit\Contracts\AuditLog;
use App\Modules\Audit\Data\AuditEvent;
use App\Modules\IdentityAccess\Data\Actor;
use App\Modules\PlatformOperations\Contracts\CompletionAdapter;
use App\Modules\PlatformOperations\Contracts\PlatformProbes;
use App\Modules\PlatformOperations\Data\ProbeView;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use stdClass;

final readonly class DatabasePlatformProbes implements PlatformProbes
{
    public function __construct(
        private ConnectionInterface $database,
        private AuditLog $audit,
        private CompletionAdapter $completion,
    ) {}

    public function request(Actor $actor, string $idempotencyKey): ProbeView
    {
        $this->authorize($actor);

        $existing = $this->database
            ->table('platform_probe_runs')
            ->where('actor_type', $actor->type)
            ->where('actor_id', $actor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $this->view($existing);
        }

        return $this->database->transaction(function () use ($actor, $idempotencyKey): ProbeView {
            $probeId = (string) Str::uuid();
            $outboxEventId = (string) Str::uuid();
            $correlationId = (string) Str::uuid();
            $now = CarbonImmutable::now();

            $inserted = $this->database->table('platform_probe_runs')->insertOrIgnore([
                'id' => $probeId,
                'idempotency_key' => $idempotencyKey,
                'actor_type' => $actor->type,
                'actor_id' => $actor->id,
                'correlation_id' => $correlationId,
                'status' => 'queued',
                'completion_adapter' => $this->completion->name(),
                'completion_code' => null,
                'queued_at' => $now,
                'completed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === 0) {
                $existing = $this->database
                    ->table('platform_probe_runs')
                    ->where('actor_type', $actor->type)
                    ->where('actor_id', $actor->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->firstOrFail();

                return $this->view($existing);
            }

            $this->audit->append(new AuditEvent(
                id: (string) Str::uuid(),
                actorType: $actor->type,
                actorId: $actor->id,
                action: 'platform.probe.requested',
                resourceType: 'platform_probe',
                resourceId: $probeId,
                outcome: 'accepted',
                correlationId: $correlationId,
                context: ['adapter' => $this->completion->name()],
                occurredAt: $now,
            ));

            $this->database->table('outbox_events')->insert([
                'id' => $outboxEventId,
                'topic' => 'platform.probe.requested',
                'aggregate_type' => 'platform_probe',
                'aggregate_id' => $probeId,
                'payload' => json_encode([
                    'probe_id' => $probeId,
                    'correlation_id' => $correlationId,
                ], JSON_THROW_ON_ERROR),
                'attempts' => 0,
                'available_at' => $now,
                'dispatched_at' => null,
                'claimed_at' => null,
                'processed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $run = $this->database->table('platform_probe_runs')->find($probeId);

            return $this->view($run);
        });
    }

    public function find(Actor $actor, string $probeId): ?ProbeView
    {
        $this->authorize($actor);

        $run = $this->database
            ->table('platform_probe_runs')
            ->where('id', $probeId)
            ->where('actor_type', $actor->type)
            ->where('actor_id', $actor->id)
            ->first();

        return $run === null ? null : $this->view($run);
    }

    private function authorize(Actor $actor): void
    {
        if (! $actor->can('platform.probe')) {
            throw new AuthorizationException;
        }
    }

    private function view(stdClass $run): ProbeView
    {
        $auditCount = $this->database
            ->table('audit_events')
            ->where('correlation_id', $run->correlation_id)
            ->count();

        return new ProbeView(
            id: $run->id,
            status: $run->status,
            correlationId: $run->correlation_id,
            completionCode: $run->completion_code,
            auditEventCount: $auditCount,
        );
    }
}
