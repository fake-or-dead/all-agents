<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditedPlatformPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_request_crosses_transaction_audit_outbox_and_worker_boundaries(): void
    {
        $headers = [
            'Idempotency-Key' => 'probe-20260729-001',
            'X-Tapoda-Test-Actor' => 'platform-operator',
        ];

        $accepted = $this
            ->withHeaders($headers)
            ->postJson('/platform/probes')
            ->assertAccepted()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.audit_event_count', 1);

        $probeId = $accepted->json('data.id');

        $this->artisan('platform:relay-outbox')->assertSuccessful();

        $this
            ->withHeaders($headers)
            ->getJson("/platform/probes/{$probeId}")
            ->assertOk()
            ->assertJsonPath('data.id', $probeId)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.completion.code', 'deterministic-fake.completed')
            ->assertJsonPath('data.audit_event_count', 2);
    }

    public function test_probe_request_requires_an_authorized_actor(): void
    {
        $this
            ->withHeader('Idempotency-Key', 'probe-20260729-unauthorized')
            ->postJson('/platform/probes')
            ->assertForbidden();

        $this->assertDatabaseCount('platform_probe_runs', 0);
        $this->assertDatabaseCount('audit_events', 0);
        $this->assertDatabaseCount('outbox_events', 0);
    }

    public function test_idempotency_key_returns_the_original_probe_without_duplicate_events(): void
    {
        $headers = [
            'Idempotency-Key' => 'probe-20260729-idempotent',
            'X-Tapoda-Test-Actor' => 'platform-operator',
        ];

        $first = $this->withHeaders($headers)->postJson('/platform/probes')->assertAccepted();
        $second = $this->withHeaders($headers)->postJson('/platform/probes')->assertAccepted();

        $second
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.audit_event_count', 1);

        $this->assertDatabaseCount('platform_probe_runs', 1);
        $this->assertDatabaseCount('audit_events', 1);
        $this->assertDatabaseCount('outbox_events', 1);
    }

    public function test_production_actor_adapter_ignores_the_test_header(): void
    {
        config()->set('platform.actor_adapter', 'laravel-auth');

        $this
            ->withHeaders([
                'Idempotency-Key' => 'probe-20260729-production',
                'X-Tapoda-Test-Actor' => 'platform-operator',
            ])
            ->postJson('/platform/probes')
            ->assertForbidden();
    }
}
