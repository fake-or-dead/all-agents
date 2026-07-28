<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('given_name', 160);
            $table->string('family_name', 160);
            $table->timestampsTz();
        });

        Schema::create('person_identifiers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id');
            $table->string('type', 24);
            $table->char('country_code', 2);
            $table->text('identifier_encrypted');
            $table->char('lookup_digest', 64)->unique();
            $table->string('last_four', 4);
            $table->timestampsTz();

            $table->foreign('person_id')->references('id')->on('people')->cascadeOnDelete();
            $table->index(['person_id', 'type']);
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id')->unique();
            $table->char('email_digest', 64)->unique();
            $table->text('email_encrypted');
            $table->string('status', 24)->default('active');
            $table->rememberToken();
            $table->timestampsTz();

            $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
        });

        Schema::create('credentials', function (Blueprint $table): void {
            $table->uuid('account_id')->primary();
            $table->text('password_hash');
            $table->string('algorithm', 32)->default('current');
            $table->timestampTz('changed_at');

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });

        Schema::create('verification_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('purpose', 32);
            $table->char('identifier_digest', 64);
            $table->text('secret_hash')->nullable();
            $table->char('token_digest', 64)->nullable()->unique();
            $table->char('proof_digest', 64)->nullable()->unique();
            $table->unsignedSmallInteger('attempts_remaining')->default(5);
            $table->timestampTz('expires_at');
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('invalidated_at')->nullable();
            $table->string('invalidated_reason', 48)->nullable();
            $table->timestampsTz();

            $table->index(['purpose', 'identifier_digest', 'created_at']);
        });

        Schema::create('consent_acceptances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id');
            $table->string('document_version', 96);
            $table->string('context', 48);
            $table->json('evidence');
            $table->timestampTz('accepted_at');

            $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
            $table->index(['person_id', 'context']);
        });

        Schema::create('auth_sessions', function (Blueprint $table): void {
            $table->string('id', 255)->primary();
            $table->uuid('account_id');
            $table->timestampTz('authenticated_at');
            $table->timestampTz('last_seen_at');
            $table->timestampTz('revoked_at')->nullable();
            $table->string('revoked_reason', 48)->nullable();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->index(['account_id', 'revoked_at']);
        });

        Schema::create('local_verification_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 32);
            $table->char('recipient_digest', 64);
            $table->text('payload_encrypted');
            $table->timestampsTz();

            $table->index(['kind', 'recipient_digest', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('local_verification_deliveries');
        Schema::dropIfExists('auth_sessions');
        Schema::dropIfExists('consent_acceptances');
        Schema::dropIfExists('verification_challenges');
        Schema::dropIfExists('credentials');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('person_identifiers');
        Schema::dropIfExists('people');
    }
};
