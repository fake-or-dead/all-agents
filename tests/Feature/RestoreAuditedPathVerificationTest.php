<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RestoreAuditedPathVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_preserves_restored_marker_and_adds_one_correlated_set(): void
    {
        $markerId = '00000000-0000-4000-8000-000000000909';
        $marker = [
            'id' => $markerId,
            'actor_type' => 'restore-marker',
            'actor_id' => 'pre-backup',
            'action' => 'platform.restore.marker',
            'resource_type' => 'platform_restore',
            'resource_id' => '00000000-0000-4000-8000-000000000910',
            'outcome' => 'recorded',
            'correlation_id' => '00000000-0000-4000-8000-000000000911',
            'context' => json_encode(['purpose' => 'non-destructive-restore-check'], JSON_THROW_ON_ERROR),
            'occurred_at' => CarbonImmutable::parse('2026-07-29T00:00:00+07:00'),
        ];
        DB::table('audit_events')->insert($marker);

        $this
            ->artisan('platform:verify-restored-audited-path', [
                '--marker-id' => $markerId,
            ])
            ->expectsOutputToContain('"status":"verified"')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_events', [
            'id' => $markerId,
            'actor_type' => 'restore-marker',
            'actor_id' => 'pre-backup',
            'action' => 'platform.restore.marker',
            'resource_type' => 'platform_restore',
            'resource_id' => '00000000-0000-4000-8000-000000000910',
            'outcome' => 'recorded',
            'correlation_id' => '00000000-0000-4000-8000-000000000911',
        ]);
        $this->assertDatabaseCount('platform_probe_runs', 1);
        $this->assertDatabaseCount('audit_events', 3);
        $this->assertDatabaseCount('outbox_events', 1);
        $this->assertDatabaseCount('platform_completion_receipts', 0);
        $this->assertDatabaseHas('platform_probe_runs', [
            'actor_type' => 'restore-verification',
            'actor_id' => 'reviewed-artifact',
            'status' => 'completed',
            'completion_adapter' => 'deterministic-fake',
            'completion_code' => 'deterministic-fake.completed',
        ]);
        $this->assertSame(
            1,
            DB::table('audit_events')
                ->where('actor_type', '!=', 'restore-marker')
                ->distinct()
                ->count('correlation_id'),
        );
    }
}
