<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_training_idempotency', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('account_id');
            $table->uuid('person_id');
            $table->string('idempotency_key', 128);
            $table->text('payload_encrypted');
            $table->uuid('training_id')->nullable();
            $table->timestampsTz();

            $table->foreign('account_id')->references('id')->on('accounts')->restrictOnDelete();
            $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
            $table->foreign('training_id')
                ->references('id')
                ->on('person_training_experiences')
                ->restrictOnDelete();
            $table->unique(
                ['account_id', 'person_id', 'idempotency_key'],
                'training_idempotency_actor_person_key_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_training_idempotency');
    }
};
