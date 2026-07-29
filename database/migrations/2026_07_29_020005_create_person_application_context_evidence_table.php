<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_application_context_evidence', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('person_id');
            $table->unsignedBigInteger('version');
            $table->text('facts_encrypted');
            $table->string('encryption_key_version', 24);
            $table->timestampTz('effective_at');
            $table->timestampTz('stale_at')->nullable();
            $table->timestampTz('created_at');

            $table->foreign('person_id')->references('id')->on('people')->restrictOnDelete();
            $table->unique(
                ['person_id', 'version'],
                'person_context_evidence_person_version_unique',
            );
            $table->index(
                ['person_id', 'effective_at', 'version'],
                'person_context_evidence_resolution_index',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->installPostgresGuards();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('person_application_context_evidence');

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(
                'DROP FUNCTION IF EXISTS protect_person_application_context_evidence()',
            );
            DB::unprepared(
                'DROP FUNCTION IF EXISTS validate_person_application_context_evidence_insert()',
            );
        }
    }

    private function installPostgresGuards(): void
    {
        DB::unprepared(<<<'SQL'
ALTER TABLE person_application_context_evidence
    ADD CONSTRAINT person_context_evidence_version_positive
        CHECK (version > 0),
    ADD CONSTRAINT person_context_evidence_interval_valid
        CHECK (stale_at IS NULL OR stale_at > effective_at);

CREATE OR REPLACE FUNCTION validate_person_application_context_evidence_insert() RETURNS trigger AS $$
DECLARE
    latest_version BIGINT;
    latest_effective_at TIMESTAMPTZ;
    latest_stale_at TIMESTAMPTZ;
BEGIN
    PERFORM pg_advisory_xact_lock(hashtextextended(NEW.person_id::text, 0));

    SELECT version, effective_at, stale_at
    INTO latest_version, latest_effective_at, latest_stale_at
    FROM person_application_context_evidence
    WHERE person_id = NEW.person_id
    ORDER BY version DESC
    LIMIT 1;

    IF latest_version IS NULL AND NEW.version <> 1 THEN
        RAISE EXCEPTION 'person application context evidence must start at version 1';
    END IF;

    IF latest_version IS NOT NULL AND NEW.version <> latest_version + 1 THEN
        RAISE EXCEPTION 'person application context evidence version must be monotonic';
    END IF;

    IF latest_effective_at IS NOT NULL AND NEW.effective_at <= latest_effective_at THEN
        RAISE EXCEPTION 'person application context evidence effective time must be monotonic';
    END IF;

    IF latest_stale_at IS NOT NULL AND NEW.effective_at < latest_stale_at THEN
        RAISE EXCEPTION 'person application context evidence intervals must not overlap';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER person_application_context_evidence_insert_valid
BEFORE INSERT ON person_application_context_evidence
FOR EACH ROW EXECUTE FUNCTION validate_person_application_context_evidence_insert();

CREATE OR REPLACE FUNCTION protect_person_application_context_evidence() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'person application context evidence is append-only';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER person_application_context_evidence_append_only
BEFORE UPDATE OR DELETE ON person_application_context_evidence
FOR EACH ROW EXECUTE FUNCTION protect_person_application_context_evidence();
SQL);
    }
};
