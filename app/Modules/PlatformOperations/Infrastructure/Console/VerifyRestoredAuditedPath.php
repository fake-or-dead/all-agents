<?php

namespace App\Modules\PlatformOperations\Infrastructure\Console;

use App\Modules\IdentityAccess\Data\Actor;
use App\Modules\PlatformOperations\Contracts\CompletionAdapter;
use App\Modules\PlatformOperations\Contracts\PlatformProbes;
use App\Modules\PlatformOperations\Contracts\PlatformProbeWorker;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;

final class VerifyRestoredAuditedPath extends Command
{
    protected $signature = 'platform:verify-restored-audited-path
        {--marker-id= : Existing audit event UUID that must remain unchanged}';

    protected $description = 'Verify the audited path without resetting or deleting restored data.';

    public function handle(
        ConnectionInterface $database,
        PlatformProbes $probes,
        PlatformProbeWorker $worker,
        CompletionAdapter $completion,
    ): int {
        $markerId = trim((string) $this->option('marker-id'));

        if (! Str::isUuid($markerId)) {
            throw new RuntimeException('A valid restored audit marker UUID is required.');
        }

        $markerBefore = $this->marker($database, $markerId);
        $before = $this->counts($database);
        $verificationId = (string) Str::uuid();
        $actor = new Actor(
            type: 'restore-verification',
            id: 'reviewed-artifact',
            capabilities: ['platform.probe'],
        );
        $requested = $probes->request($actor, "restore-verify-{$verificationId}");
        $outbox = $database
            ->table('outbox_events')
            ->where('aggregate_id', $requested->id)
            ->firstOrFail();

        $worker->completeOutboxEvent($outbox->id);

        $completed = $probes->find($actor, $requested->id);

        if (
            $completed === null
            || $completed->status !== 'completed'
            || $completed->auditEventCount !== 2
            || $completed->completionCode === null
        ) {
            throw new RuntimeException('Restored audited probe did not complete.');
        }

        $after = $this->counts($database);
        $expectedDeltas = [
            'platform_probe_runs' => 1,
            'audit_events' => 2,
            'outbox_events' => 1,
            'platform_completion_receipts' => $completion->name() === 'structured-log' ? 1 : 0,
        ];
        $actualDeltas = [];

        foreach ($expectedDeltas as $table => $expectedDelta) {
            $actualDeltas[$table] = $after[$table] - $before[$table];

            if ($actualDeltas[$table] !== $expectedDelta) {
                throw new RuntimeException(
                    "Unexpected {$table} delta: expected {$expectedDelta}, got {$actualDeltas[$table]}.",
                );
            }
        }

        $markerAfter = $this->marker($database, $markerId);

        if ($this->rowHash($markerBefore) !== $this->rowHash($markerAfter)) {
            throw new RuntimeException('The restored audit marker changed during verification.');
        }

        $correlationCounts = [
            'platform_probe_runs' => $database
                ->table('platform_probe_runs')
                ->where('correlation_id', $completed->correlationId)
                ->count(),
            'audit_events' => $database
                ->table('audit_events')
                ->where('correlation_id', $completed->correlationId)
                ->count(),
            'outbox_events' => $database
                ->table('outbox_events')
                ->where('aggregate_id', $completed->id)
                ->whereNotNull('processed_at')
                ->count(),
        ];

        if ($correlationCounts !== [
            'platform_probe_runs' => 1,
            'audit_events' => 2,
            'outbox_events' => 1,
        ]) {
            throw new RuntimeException('Verification rows do not form one completed correlated set.');
        }

        $this->line((string) json_encode([
            'status' => 'verified',
            'marker_id' => $markerId,
            'probe_id' => $completed->id,
            'correlation_id' => $completed->correlationId,
            'completion_adapter' => $completion->name(),
            'completion_code' => $completed->completionCode,
            'deltas' => $actualDeltas,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function counts(ConnectionInterface $database): array
    {
        return [
            'platform_probe_runs' => $database->table('platform_probe_runs')->count(),
            'audit_events' => $database->table('audit_events')->count(),
            'outbox_events' => $database->table('outbox_events')->count(),
            'platform_completion_receipts' => $database
                ->table('platform_completion_receipts')
                ->count(),
        ];
    }

    private function marker(ConnectionInterface $database, string $markerId): stdClass
    {
        $marker = $database
            ->table('audit_events')
            ->where('id', $markerId)
            ->first();

        if ($marker === null) {
            throw new RuntimeException('The restored audit marker is missing.');
        }

        return $marker;
    }

    private function rowHash(stdClass $row): string
    {
        return hash(
            'sha256',
            (string) json_encode((array) $row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        );
    }
}
