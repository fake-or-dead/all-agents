<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasColumn('platform_completion_receipts', 'completed_at')
            && ! Schema::hasColumn('platform_completion_receipts', 'reserved_at')
        ) {
            Schema::table('platform_completion_receipts', function (Blueprint $table): void {
                $table->renameColumn('completed_at', 'reserved_at');
            });
        }

        Schema::table('platform_completion_receipts', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_completion_receipts', 'status')) {
                $table->string('status', 32)->default('pending');
            }

            if (! Schema::hasColumn('platform_completion_receipts', 'attempts')) {
                $table->unsignedInteger('attempts')->default(0);
            }

            if (! Schema::hasColumn('platform_completion_receipts', 'delivered_at')) {
                $table->timestampTz('delivered_at')->nullable();
            }
        });

        DB::table('platform_completion_receipts')->update([
            'status' => 'delivered',
            'attempts' => 1,
            'delivered_at' => DB::raw('reserved_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('platform_completion_receipts', function (Blueprint $table): void {
            $table->dropColumn(['status', 'attempts', 'delivered_at']);
        });

        Schema::table('platform_completion_receipts', function (Blueprint $table): void {
            $table->renameColumn('reserved_at', 'completed_at');
        });
    }
};
