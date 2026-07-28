<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('actor_type', 64);
            $table->string('actor_id', 128);
            $table->string('action', 128);
            $table->string('resource_type', 64);
            $table->uuid('resource_id');
            $table->string('outcome', 32);
            $table->uuid('correlation_id')->index();
            $table->json('context');
            $table->timestampTz('occurred_at');

            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
