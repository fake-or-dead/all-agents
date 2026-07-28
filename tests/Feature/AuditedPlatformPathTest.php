<?php

namespace Tests\Feature;

use App\Modules\PlatformOperations\Contracts\CompletionAdapter;
use App\Modules\PlatformOperations\Contracts\PlatformProbeWorker;
use App\Modules\PlatformOperations\Infrastructure\Completion\StructuredLogCompletionAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Psr\Log\LoggerInterface;
use RuntimeException;
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

    public function test_idempotency_key_is_scoped_to_the_authorized_actor(): void
    {
        $first = $this
            ->withHeaders([
                'Idempotency-Key' => 'probe-20260729-shared-key',
                'X-Tapoda-Test-Actor' => 'platform-operator-a',
            ])
            ->postJson('/platform/probes')
            ->assertAccepted();
        $second = $this
            ->withHeaders([
                'Idempotency-Key' => 'probe-20260729-shared-key',
                'X-Tapoda-Test-Actor' => 'platform-operator-b',
            ])
            ->postJson('/platform/probes')
            ->assertAccepted();

        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('platform_probe_runs', 2);
        $this->assertDatabaseCount('audit_events', 2);
        $this->assertDatabaseCount('outbox_events', 2);
    }

    public function test_actor_cannot_read_another_actors_probe(): void
    {
        $probe = $this
            ->withHeaders([
                'Idempotency-Key' => 'probe-20260729-private',
                'X-Tapoda-Test-Actor' => 'platform-operator-a',
            ])
            ->postJson('/platform/probes')
            ->assertAccepted();

        $this
            ->withHeader('X-Tapoda-Test-Actor', 'platform-operator-b')
            ->getJson('/platform/probes/'.$probe->json('data.id'))
            ->assertNotFound();
    }

    public function test_completion_retry_after_adapter_success_has_one_effective_result(): void
    {
        $logger = Mockery::mock(LoggerInterface::class);
        $logger
            ->shouldReceive('info')
            ->once()
            ->with('Platform probe completed.', Mockery::on(
                fn (array $context): bool => isset($context['probe_id'], $context['correlation_id']),
            ));
        $adapter = new StructuredLogCompletionAdapter(DB::connection(), $logger);
        $this->app->instance(CompletionAdapter::class, $adapter);

        $accepted = $this
            ->withHeaders([
                'Idempotency-Key' => 'probe-20260729-crash-window',
                'X-Tapoda-Test-Actor' => 'platform-operator',
            ])
            ->postJson('/platform/probes')
            ->assertAccepted();
        $probe = DB::table('platform_probe_runs')->find($accepted->json('data.id'));

        // Simulate adapter success followed by a process crash before worker DB completion.
        $adapter->complete($probe->id, $probe->correlation_id);

        $this->artisan('platform:relay-outbox')->assertSuccessful();

        $this->assertDatabaseCount('platform_completion_receipts', 1);
        $this->assertDatabaseHas('platform_completion_receipts', [
            'correlation_id' => $probe->correlation_id,
            'status' => 'delivered',
            'attempts' => 1,
        ]);
        $this->assertDatabaseHas('platform_probe_runs', [
            'id' => $probe->id,
            'status' => 'completed',
            'completion_code' => 'structured-log.completed',
        ]);
        $this->assertDatabaseCount('audit_events', 2);
        $this->assertDatabaseCount('outbox_events', 1);
    }

    public function test_pending_completion_is_retried_after_effect_failure(): void
    {
        $deliveryAttempts = 0;
        $effectiveDeliveries = [];
        $logger = Mockery::mock(LoggerInterface::class);
        $logger
            ->shouldReceive('info')
            ->twice()
            ->with('Platform probe completed.', Mockery::on(
                fn (array $context): bool => isset(
                    $context['probe_id'],
                    $context['correlation_id'],
                    $context['delivery_key'],
                ),
            ))
            ->andReturnUsing(function (string $message, array $context) use (
                &$deliveryAttempts,
                &$effectiveDeliveries,
            ): void {
                $deliveryAttempts++;
                $this->assertDatabaseHas('platform_completion_receipts', [
                    'correlation_id' => $context['correlation_id'],
                    'status' => 'pending',
                    'delivered_at' => null,
                ]);

                if ($deliveryAttempts === 1) {
                    throw new RuntimeException('Injected failure before observable completion.');
                }

                $effectiveDeliveries[$context['delivery_key']] = $message;
            });
        $adapter = new StructuredLogCompletionAdapter(DB::connection(), $logger);
        $this->app->instance(CompletionAdapter::class, $adapter);

        $accepted = $this
            ->withHeaders([
                'Idempotency-Key' => 'probe-20260729-effect-retry',
                'X-Tapoda-Test-Actor' => 'platform-operator',
            ])
            ->postJson('/platform/probes')
            ->assertAccepted();
        $probe = DB::table('platform_probe_runs')->find($accepted->json('data.id'));
        $outbox = DB::table('outbox_events')->where('aggregate_id', $probe->id)->first();

        try {
            $this->app
                ->make(PlatformProbeWorker::class)
                ->completeOutboxEvent($outbox->id);
            $this->fail('The injected delivery failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Injected failure before observable completion.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseHas('platform_completion_receipts', [
            'correlation_id' => $probe->correlation_id,
            'status' => 'pending',
            'attempts' => 1,
            'delivered_at' => null,
        ]);
        $this->assertDatabaseHas('platform_probe_runs', [
            'id' => $probe->id,
            'status' => 'processing',
            'completion_code' => null,
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'id' => $outbox->id,
            'processed_at' => null,
            'claimed_at' => null,
        ]);
        $this->assertDatabaseCount('audit_events', 1);
        $this->assertSame([], $effectiveDeliveries);

        $this->app
            ->make(PlatformProbeWorker::class)
            ->completeOutboxEvent($outbox->id);

        $this->assertDatabaseHas('platform_completion_receipts', [
            'correlation_id' => $probe->correlation_id,
            'status' => 'delivered',
            'attempts' => 2,
        ]);
        $this->assertDatabaseHas('platform_probe_runs', [
            'id' => $probe->id,
            'status' => 'completed',
            'completion_code' => 'structured-log.completed',
        ]);
        $this->assertDatabaseCount('audit_events', 2);
        $this->assertCount(1, $effectiveDeliveries);

        $adapter->complete($probe->id, $probe->correlation_id);

        $this->assertSame(2, $deliveryAttempts);
        $this->assertCount(1, $effectiveDeliveries);
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
