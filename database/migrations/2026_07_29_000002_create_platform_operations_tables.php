<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_probe_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 128);
            $table->string('actor_type', 64);
            $table->string('actor_id', 128);
            $table->uuid('correlation_id')->unique();
            $table->string('status', 32);
            $table->string('completion_adapter', 64);
            $table->string('completion_code', 128)->nullable();
            $table->timestampTz('queued_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['actor_type', 'actor_id', 'idempotency_key'],
                'platform_probe_actor_idempotency_unique',
            );
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('topic', 128);
            $table->string('aggregate_type', 64);
            $table->uuid('aggregate_id');
            $table->json('payload');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('available_at');
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();

            $table->index(['processed_at', 'available_at']);
        });

        Schema::create('runtime_heartbeats', function (Blueprint $table): void {
            $table->string('component', 32)->primary();
            $table->timestampTz('seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runtime_heartbeats');
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('platform_probe_runs');
    }
};
