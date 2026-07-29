<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_completion_receipts', function (Blueprint $table): void {
            $table->uuid('correlation_id')->primary();
            $table->uuid('probe_id');
            $table->string('adapter', 64);
            $table->string('completion_code', 128);
            $table->string('status', 32)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('reserved_at');
            $table->timestampTz('delivered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_completion_receipts');
    }
};
