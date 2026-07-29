<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->string('lookup_key_version', 24);
            $table->char('lookup_digest', 64)->unique();
            $table->string('last_four', 4);
            $table->timestampsTz();

            $table->foreign('person_id')->references('id')->on('people')->cascadeOnDelete();
            $table->index(['person_id', 'type']);
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id');
            $table->string('email_digest_key_version', 24);
            $table->char('email_digest', 64)->unique();
            $table->text('email_encrypted');
            $table->string('status', 24)->default('active');
            $table->unsignedBigInteger('credential_epoch')->default(1);
            $table->rememberToken();
            $table->timestampsTz();

            $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
        });

        Schema::create('person_account_link_proofs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id');
            $table->char('token_digest', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('approved_at');
            $table->timestampsTz();

            $table->foreign('person_id')->references('id')->on('people')->cascadeOnDelete();
            $table->index(['person_id', 'consumed_at']);
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
            $table->string('identifier_key_version', 24);
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
            $table->string('active_slot', 16)->nullable()->default('active');
            $table->timestampsTz();

            $table->index(['purpose', 'identifier_digest', 'created_at']);
            $table->unique(['purpose', 'identifier_digest', 'active_slot']);
        });

        Schema::create('verification_subject_locks', function (Blueprint $table): void {
            $table->char('lock_key', 64)->primary();
            $table->timestampsTz();
        });

        Schema::create('consent_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('document_key', 96)->unique();
            $table->string('title', 240);
            $table->string('purpose', 96);
            $table->timestampsTz();
        });

        Schema::create('consent_document_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->string('version_label', 96);
            $table->string('locale', 12);
            $table->text('content');
            $table->char('content_checksum', 64);
            $table->string('status', 24);
            $table->timestampTz('published_at');
            $table->timestampsTz();

            $table->foreign('document_id')->references('id')->on('consent_documents')->restrictOnDelete();
            $table->unique(['document_id', 'version_label', 'locale']);
        });

        Schema::create('consent_acceptances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id');
            $table->uuid('document_version_id');
            $table->char('document_checksum', 64);
            $table->string('locale', 12);
            $table->string('context', 48);
            $table->json('evidence');
            $table->timestampTz('accepted_at');

            $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
            $table->foreign('document_version_id')->references('id')->on('consent_document_versions')->restrictOnDelete();
            $table->index(['person_id', 'context']);
        });

        $now = now();
        $documentId = '10000000-0000-4000-8000-000000000001';
        $versionId = '10000000-0000-4000-8000-000000000002';
        $content = 'เอกสารตัวอย่างสำหรับการพัฒนาภายใน: Tapoda จะใช้ข้อมูลบัญชีและข้อมูลประจำตัวเพื่อยืนยันตัวบุคคล จัดการการสมัคร และรักษาความปลอดภัยของบัญชี เอกสารนี้ไม่ใช่ข้อความกฎหมายสำหรับระบบจริง';
        DB::table('consent_documents')->insert([
            'id' => $documentId,
            'document_key' => 'registration-consent',
            'title' => 'ความยินยอมการสร้างบัญชี (ตัวอย่างภายใน)',
            'purpose' => 'account_registration',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('consent_document_versions')->insert([
            'id' => $versionId,
            'document_id' => $documentId,
            'version_label' => 'local-fixture-v1',
            'locale' => 'th',
            'content' => $content,
            'content_checksum' => hash('sha256', $content),
            'status' => 'published',
            'published_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // PostgreSQL is the authoritative local runtime. These guards make
        // published consent evidence append-only even for direct SQL callers.
        if (DB::getDriverName() === 'pgsql') {
            // PostgreSQL functions outlive tables dropped by RefreshDatabase.
            // Remove a stale test-schema function before installing this
            // migration's authoritative guard.
            DB::unprepared('DROP FUNCTION IF EXISTS protect_consent_acceptance() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS protect_published_consent_version() CASCADE');
            DB::unprepared(<<<'SQL'
CREATE FUNCTION protect_published_consent_version() RETURNS trigger AS $$
BEGIN
    IF OLD.status = 'published' THEN
        RAISE EXCEPTION 'published consent document versions are immutable';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER consent_document_versions_immutable
BEFORE UPDATE OR DELETE ON consent_document_versions
FOR EACH ROW EXECUTE FUNCTION protect_published_consent_version();
SQL);
            DB::unprepared(<<<'SQL'
CREATE FUNCTION protect_consent_acceptance() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'consent acceptances are append-only';
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER consent_acceptances_append_only
BEFORE UPDATE OR DELETE ON consent_acceptances
FOR EACH ROW EXECUTE FUNCTION protect_consent_acceptance();
SQL);
        }

        Schema::create('auth_sessions', function (Blueprint $table): void {
            $table->string('id', 255)->primary();
            $table->uuid('account_id');
            $table->unsignedBigInteger('credential_epoch');
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
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS protect_consent_acceptance() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS protect_published_consent_version() CASCADE');
        }
        Schema::dropIfExists('local_verification_deliveries');
        Schema::dropIfExists('auth_sessions');
        Schema::dropIfExists('consent_acceptances');
        Schema::dropIfExists('consent_document_versions');
        Schema::dropIfExists('consent_documents');
        Schema::dropIfExists('verification_subject_locks');
        Schema::dropIfExists('verification_challenges');
        Schema::dropIfExists('credentials');
        Schema::dropIfExists('person_account_link_proofs');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('person_identifiers');
        Schema::dropIfExists('people');
    }
};
