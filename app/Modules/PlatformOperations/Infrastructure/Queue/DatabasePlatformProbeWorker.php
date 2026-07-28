<?php

namespace App\Modules\PlatformOperations\Infrastructure\Queue;

use App\Modules\Audit\Contracts\AuditLog;
use App\Modules\Audit\Data\AuditEvent;
use App\Modules\PlatformOperations\Contracts\CompletionAdapter;
use App\Modules\PlatformOperations\Contracts\PlatformProbeWorker;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class DatabasePlatformProbeWorker implements PlatformProbeWorker
{
    public function __construct(
        private ConnectionInterface $database,
        private CompletionAdapter $completion,
        private AuditLog $audit,
    ) {}

    public function completeOutboxEvent(string $outboxEventId): void
    {
        $claim = $this->database->transaction(function () use ($outboxEventId): ?array {
            $outbox = $this->database
                ->table('outbox_events')
                ->where('id', $outboxEventId)
                ->lockForUpdate()
                ->first();

            if ($outbox === null || $outbox->processed_at !== null) {
                return null;
            }

            if (
                $outbox->claimed_at !== null
                && CarbonImmutable::parse($outbox->claimed_at)->isAfter(CarbonImmutable::now()->subMinutes(5))
            ) {
                return null;
            }

            $payload = json_decode($outbox->payload, true, flags: JSON_THROW_ON_ERROR);
            $probe = $this->database
                ->table('platform_probe_runs')
                ->where('id', $payload['probe_id'])
                ->lockForUpdate()
                ->first();

            if ($probe === null) {
                throw new RuntimeException('Outbox event references a missing platform probe.');
            }

            if ($probe->status === 'completed') {
                $now = CarbonImmutable::now();
                $this->database->table('outbox_events')->where('id', $outboxEventId)->update([
                    'processed_at' => $now,
                    'updated_at' => $now,
                ]);

                return null;
            }

            $now = CarbonImmutable::now();
            $this->database->table('platform_probe_runs')->where('id', $probe->id)->update([
                'status' => 'processing',
                'updated_at' => $now,
            ]);
            $this->database->table('outbox_events')->where('id', $outboxEventId)->update([
                'attempts' => $outbox->attempts + 1,
                'claimed_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'probe_id' => $probe->id,
                'correlation_id' => $probe->correlation_id,
            ];
        });

        if ($claim === null) {
            return;
        }

        try {
            $result = $this->completion->complete(
                $claim['probe_id'],
                $claim['correlation_id'],
            );
        } catch (Throwable $exception) {
            $this->database->table('outbox_events')->where('id', $outboxEventId)->update([
                'claimed_at' => null,
                'updated_at' => CarbonImmutable::now(),
            ]);

            throw $exception;
        }

        $this->database->transaction(function () use ($outboxEventId, $claim, $result): void {
            $now = CarbonImmutable::now();
            $updated = $this->database
                ->table('platform_probe_runs')
                ->where('id', $claim['probe_id'])
                ->where('status', '!=', 'completed')
                ->update([
                    'status' => 'completed',
                    'completion_code' => $result->code,
                    'completed_at' => $now,
                    'updated_at' => $now,
                ]);

            $this->database->table('outbox_events')->where('id', $outboxEventId)->update([
                'processed_at' => $now,
                'claimed_at' => null,
                'updated_at' => $now,
            ]);

            if ($updated === 0) {
                return;
            }

            $this->audit->append(new AuditEvent(
                id: (string) Str::uuid(),
                actorType: 'system',
                actorId: 'platform-worker',
                action: 'platform.probe.completed',
                resourceType: 'platform_probe',
                resourceId: $claim['probe_id'],
                outcome: 'completed',
                correlationId: $claim['correlation_id'],
                context: ['adapter' => $this->completion->name(), 'code' => $result->code],
                occurredAt: $now,
            ));

            $this->database->table('runtime_heartbeats')->updateOrInsert(
                ['component' => 'worker'],
                ['seen_at' => $now],
            );
        });
    }
}
