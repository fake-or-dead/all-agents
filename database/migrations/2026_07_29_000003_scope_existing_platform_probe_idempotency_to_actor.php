<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('platform_probe_runs', 'actor_type')) {
            return;
        }

        Schema::table('platform_probe_runs', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->string('actor_type', 64)->default('legacy-account');
            $table->unique(
                ['actor_type', 'actor_id', 'idempotency_key'],
                'platform_probe_actor_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        // Pre-merge local compatibility only. The preceding migration owns clean rollback.
    }
};
