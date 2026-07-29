<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_definitions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('form_key', 96)->unique();
            $table->string('title_th', 240);
            $table->timestampsTz();
        });

        Schema::create('form_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('definition_id');
            $table->unsignedInteger('version_number');
            $table->string('locale', 12);
            $table->enum('status', ['draft', 'published']);
            $table->char('schema_checksum', 64);
            $table->uuid('consent_document_version_id');
            $table->char('consent_document_checksum', 64);
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();

            $table->foreign('definition_id')
                ->references('id')
                ->on('form_definitions')
                ->restrictOnDelete();
            $table->foreign('consent_document_version_id')
                ->references('id')
                ->on('consent_document_versions')
                ->restrictOnDelete();
            $table->unique(['definition_id', 'version_number']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
ALTER TABLE form_versions
ADD CONSTRAINT form_versions_publication_state_check
CHECK (
    (status = 'draft' AND published_at IS NULL)
    OR (status = 'published' AND published_at IS NOT NULL)
);
SQL);
        }

        Schema::create('form_sections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('form_version_id');
            $table->string('semantic_key', 160);
            $table->string('title_th', 240);
            $table->text('description_th')->nullable();
            $table->unsignedSmallInteger('display_order');
            $table->timestampTz('created_at');

            $table->foreign('form_version_id')
                ->references('id')
                ->on('form_versions')
                ->restrictOnDelete();
            $table->unique(['form_version_id', 'semantic_key']);
            $table->unique(['form_version_id', 'display_order']);
            $table->unique(['form_version_id', 'id']);
        });

        Schema::create('form_fields', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('form_version_id');
            $table->uuid('section_id');
            $table->string('semantic_key', 160);
            $table->enum('field_type', [
                'short_text',
                'long_text',
                'phone',
                'single_choice',
                'multi_choice',
                'select',
                'date',
                'repeatable_group',
                'acknowledgement',
            ]);
            $table->string('label_th', 500);
            $table->text('help_text_th')->nullable();
            $table->string('placeholder_th', 500)->nullable();
            $table->json('required_rule');
            $table->json('validation_rules');
            $table->json('visibility_rule')->nullable();
            $table->enum('hidden_answer_policy', ['clear', 'retain']);
            $table->enum('renderer_hint', ['cards', 'inline', 'textarea', 'table'])->nullable();
            $table->json('initial_value')->nullable();
            $table->uuid('consent_document_version_id')->nullable();
            $table->char('consent_document_checksum', 64)->nullable();
            $table->unsignedSmallInteger('display_order');
            $table->timestampTz('created_at');

            $table->foreign(['form_version_id', 'section_id'])
                ->references(['form_version_id', 'id'])
                ->on('form_sections')
                ->restrictOnDelete();
            $table->foreign('consent_document_version_id')
                ->references('id')
                ->on('consent_document_versions')
                ->restrictOnDelete();
            $table->unique(['form_version_id', 'semantic_key']);
            $table->unique(['section_id', 'display_order']);
        });

        Schema::create('form_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('field_id');
            $table->string('semantic_key', 160);
            $table->string('value', 240);
            $table->string('label_th', 500);
            $table->unsignedSmallInteger('display_order');
            $table->timestampTz('created_at');

            $table->foreign('field_id')
                ->references('id')
                ->on('form_fields')
                ->restrictOnDelete();
            $table->unique(['field_id', 'semantic_key']);
            $table->unique(['field_id', 'value']);
            $table->unique(['field_id', 'display_order']);
        });

        Schema::create('form_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('form_version_id');
            $table->uuid('course_session_id');
            $table->string('course_type_key', 96);
            $table->enum('phase', ['pre-new', 'pre-alumni', 'post-new', 'post-alumni']);
            $table->enum('applicant_intent', ['trainee', 'staff_applicant']);
            $table->string('alumni_eligibility_key', 96)->default('none');
            $table->enum('lay_monastic_category', ['lay', 'monastic']);
            $table->string('approved_category', 48);
            $table->string('locale', 12);
            $table->timestampTz('effective_at');
            $table->timestampTz('created_at');

            $table->foreign('form_version_id')
                ->references('id')
                ->on('form_versions')
                ->restrictOnDelete();
            $table->index([
                'course_session_id',
                'course_type_key',
                'phase',
                'applicant_intent',
                'locale',
            ], 'form_assignment_resolution_index');
            $table->unique([
                'course_session_id',
                'course_type_key',
                'phase',
                'applicant_intent',
                'alumni_eligibility_key',
                'lay_monastic_category',
                'approved_category',
                'locale',
                'effective_at',
            ], 'form_assignment_context_effective_unique');
        });

        Schema::create('form_publication_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('form_version_id');
            $table->string('author_id', 160);
            $table->string('approver_id', 160);
            $table->text('reason');
            $table->json('checks');
            $table->timestampTz('published_at');
            $table->timestampTz('created_at');

            $table->foreign('form_version_id')
                ->references('id')
                ->on('form_versions')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            $this->installPostgresGuards();
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS protect_form_publication_record() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS protect_published_form_definition() CASCADE');
            DB::unprepared('DROP FUNCTION IF EXISTS protect_published_form_version() CASCADE');
        }

        Schema::dropIfExists('form_publication_events');
        Schema::dropIfExists('form_assignments');
        Schema::dropIfExists('form_options');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('form_sections');
        Schema::dropIfExists('form_versions');
        Schema::dropIfExists('form_definitions');
    }

    private function installPostgresGuards(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS protect_form_publication_record() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_published_form_definition() CASCADE');
        DB::unprepared('DROP FUNCTION IF EXISTS protect_published_form_version() CASCADE');
        DB::unprepared(<<<'SQL'
CREATE FUNCTION protect_published_form_definition() RETURNS trigger AS $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM form_versions
        WHERE definition_id = OLD.id AND status = 'published'
    ) THEN
        RAISE EXCEPTION 'definitions with published form versions are immutable';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER form_definitions_published_immutable
BEFORE UPDATE OR DELETE ON form_definitions
FOR EACH ROW EXECUTE FUNCTION protect_published_form_definition();
SQL);
        DB::unprepared(<<<'SQL'
CREATE FUNCTION protect_published_form_version() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF NEW.status <> 'draft' OR NEW.published_at IS NOT NULL THEN
            RAISE EXCEPTION 'new form versions must enter as draft';
        END IF;
        RETURN NEW;
    END IF;
    IF OLD.status = 'published' THEN
        RAISE EXCEPTION 'published form versions are immutable';
    END IF;
    IF TG_OP = 'UPDATE' AND NEW.status = 'published' THEN
        PERFORM 1
        FROM form_definitions
        WHERE id IN (OLD.definition_id, NEW.definition_id)
        ORDER BY id
        FOR SHARE;
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER form_versions_published_immutable
BEFORE INSERT OR UPDATE OR DELETE ON form_versions
FOR EACH ROW EXECUTE FUNCTION protect_published_form_version();
SQL);
        DB::unprepared(<<<'SQL'
CREATE FUNCTION protect_form_publication_record() RETURNS trigger AS $$
DECLARE
    old_version_id uuid;
    new_version_id uuid;
    old_field_id uuid;
    new_field_id uuid;
BEGIN
    IF TG_TABLE_NAME = 'form_sections' OR TG_TABLE_NAME = 'form_fields' THEN
        IF TG_OP <> 'INSERT' THEN
            old_version_id := OLD.form_version_id;
        END IF;
        IF TG_OP <> 'DELETE' THEN
            new_version_id := NEW.form_version_id;
        END IF;
    ELSIF TG_TABLE_NAME = 'form_options' THEN
        IF TG_OP <> 'INSERT' THEN
            old_field_id := OLD.field_id;
        END IF;
        IF TG_OP <> 'DELETE' THEN
            new_field_id := NEW.field_id;
        END IF;
        PERFORM 1
        FROM form_fields
        WHERE id IN (old_field_id, new_field_id)
        ORDER BY id
        FOR SHARE;
        SELECT form_version_id INTO old_version_id
        FROM form_fields
        WHERE id = old_field_id;
        SELECT form_version_id INTO new_version_id
        FROM form_fields
        WHERE id = new_field_id;
    ELSIF TG_TABLE_NAME = 'form_assignments' THEN
        IF TG_OP <> 'INSERT' THEN
            old_version_id := OLD.form_version_id;
        END IF;
        IF TG_OP <> 'DELETE' THEN
            new_version_id := NEW.form_version_id;
        END IF;
    ELSIF TG_TABLE_NAME = 'form_publication_events' THEN
        IF TG_OP <> 'INSERT' THEN
            RAISE EXCEPTION 'form publication events are append-only';
        END IF;
        new_version_id := NEW.form_version_id;
    END IF;

    PERFORM 1
    FROM form_versions
    WHERE id IN (old_version_id, new_version_id)
    ORDER BY id
    FOR SHARE;

    IF TG_TABLE_NAME IN ('form_assignments', 'form_publication_events')
        AND TG_OP = 'INSERT'
        AND NOT EXISTS (
            SELECT 1 FROM form_versions
            WHERE id = new_version_id AND status = 'published'
        )
    THEN
        RAISE EXCEPTION 'form assignments and publication events require a published version';
    END IF;

    IF TG_TABLE_NAME IN ('form_sections', 'form_fields', 'form_options')
        AND EXISTS (
        SELECT 1 FROM form_versions
        WHERE id IN (old_version_id, new_version_id) AND status = 'published'
    )
    THEN
        RAISE EXCEPTION 'published form records are immutable';
    END IF;

    IF TG_TABLE_NAME = 'form_assignments' AND TG_OP <> 'INSERT' THEN
        RAISE EXCEPTION 'form assignments are append-only';
    END IF;

    IF TG_OP = 'INSERT' THEN
        RETURN NEW;
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
CREATE TRIGGER form_sections_published_immutable
BEFORE INSERT OR UPDATE OR DELETE ON form_sections
FOR EACH ROW EXECUTE FUNCTION protect_form_publication_record();
CREATE TRIGGER form_fields_published_immutable
BEFORE INSERT OR UPDATE OR DELETE ON form_fields
FOR EACH ROW EXECUTE FUNCTION protect_form_publication_record();
CREATE TRIGGER form_options_published_immutable
BEFORE INSERT OR UPDATE OR DELETE ON form_options
FOR EACH ROW EXECUTE FUNCTION protect_form_publication_record();
CREATE TRIGGER form_assignments_published_immutable
BEFORE INSERT OR UPDATE OR DELETE ON form_assignments
FOR EACH ROW EXECUTE FUNCTION protect_form_publication_record();
CREATE TRIGGER form_publication_events_append_only
BEFORE INSERT OR UPDATE OR DELETE ON form_publication_events
FOR EACH ROW EXECUTE FUNCTION protect_form_publication_record();
SQL);
    }
};
