<?php

namespace Tests\Feature\FormEngine;

use App\Modules\FormEngine\Contracts\PublishedFormSchemas;
use App\Modules\FormEngine\Data\FormContext;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\LocalApplicationConsentSeeder;
use Database\Seeders\LocalPreNewFormSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class PublishedPreNewSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_the_exact_published_pre_new_schema_in_stable_order(): void
    {
        $this->seed(DatabaseSeeder::class);

        $schemas = $this->app->make(PublishedFormSchemas::class);
        $context = new FormContext(
            formKey: 'initial_application',
            courseSessionId: '10000000-0000-4000-8000-000000000001',
            courseTypeKey: 'meditation',
            phase: 'pre-new',
            applicantIntent: 'trainee',
            alumniEligibilityKey: null,
            layMonasticCategory: 'lay',
            approvedCategory: 'female',
            locale: 'th',
        );

        $first = $schemas->schemaFor($context);
        $second = $schemas->schemaFor($context);

        $this->assertSame('resolved', $first->status);
        $this->assertNotNull($first->schema);
        $this->assertEquals($first, $second);
        $this->assertSame('initial_application', $first->schema->formKey);
        $this->assertSame('30000000-0000-4000-8000-000000000102', $first->schema->versionId);
        $this->assertSame(1, $first->schema->versionNumber);
        $this->assertSame(
            '30000000-0000-4000-8000-000000000002',
            $first->schema->consentVersionId,
        );
        $this->assertSame(64, strlen($first->schema->consentChecksum));
        $this->assertSame(
            ['profile', 'preferences', 'consent'],
            array_map(static fn ($section): string => $section->key, $first->schema->sections),
        );
        $this->assertSame(
            ['ข้อมูลผู้สมัคร', 'ความต้องการระหว่างหลักสูตร', 'ความยินยอม'],
            array_map(static fn ($section): string => $section->title, $first->schema->sections),
        );

        $fields = collect($first->schema->sections)
            ->flatMap(static fn ($section): array => $section->fields)
            ->keyBy(static fn ($field): string => $field->key);

        $this->assertSame(
            ['profile.phone', 'preferences.needs_dinner', 'preferences.dinner_reason', 'consent.application'],
            $fields->keys()->all(),
        );
        $this->assertSame(
            ['question' => 'preferences.needs_dinner', 'operator' => 'equals', 'value' => 'yes'],
            $fields->get('preferences.dinner_reason')->visibility,
        );
        $this->assertSame('clear', $fields->get('preferences.dinner_reason')->hiddenAnswerPolicy);
        $this->assertSame(
            $first->schema->consentVersionId,
            $fields->get('consent.application')->consentVersionId,
        );
        $this->assertSame(
            $first->schema->consentChecksum,
            $fields->get('consent.application')->consentChecksum,
        );
    }

    public function test_never_falls_back_for_an_unsupported_or_mismatched_context(): void
    {
        $this->seed(DatabaseSeeder::class);

        $schemas = $this->app->make(PublishedFormSchemas::class);

        foreach ([null, 'unknown'] as $category) {
            $resolution = $schemas->schemaFor($this->context(approvedCategory: $category));

            $this->assertSame('unsupported_persona', $resolution->status);
            $this->assertNull($resolution->schema);
        }

        foreach ([
            ['phase' => 'post-new'],
            ['applicantIntent' => 'staff_applicant'],
            ['courseSessionId' => '10000000-0000-4000-8000-000000000002'],
            ['courseTypeKey' => 'service'],
            ['locale' => 'en'],
            ['alumniEligibilityKey' => 'verified-completion'],
        ] as $override) {
            $resolution = $schemas->schemaFor($this->context(...$override));

            $this->assertSame('no_assignment', $resolution->status);
            $this->assertNull($resolution->schema);
        }
    }

    public function test_local_fixture_seed_is_deterministic_when_run_twice(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $resolution = $this->app
            ->make(PublishedFormSchemas::class)
            ->schemaFor($this->context());

        $this->assertSame('resolved', $resolution->status);
        $this->assertNotNull($resolution->schema);
        $this->assertSame(
            '30000000-0000-4000-8000-000000000002',
            $resolution->schema->consentVersionId,
        );
        $this->assertNotSame(
            '10000000-0000-4000-8000-000000000002',
            $resolution->schema->consentVersionId,
        );
        $this->assertDatabaseCount('form_versions', 1);
        $this->assertDatabaseCount('form_publication_events', 1);
    }

    public function test_assignment_cannot_resolve_a_version_with_another_locale(): void
    {
        $this->seed(DatabaseSeeder::class);
        DB::table('form_versions')
            ->where('id', '30000000-0000-4000-8000-000000000102')
            ->update(['locale' => 'en']);

        $resolution = $this->app
            ->make(PublishedFormSchemas::class)
            ->schemaFor($this->context(locale: 'th'));

        $this->assertSame('no_assignment', $resolution->status);
        $this->assertNull($resolution->schema);
    }

    public function test_second_seed_fails_closed_when_a_published_child_was_tampered(): void
    {
        $this->seed(DatabaseSeeder::class);
        DB::table('form_fields')
            ->where('id', '30000000-0000-4000-8000-000000000301')
            ->update(['label_th' => 'tampered']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Existing pre-new FormEngine child schema does not match.');
        $this->app->make(LocalPreNewFormSeeder::class)->run();
    }

    public function test_second_seed_fails_closed_when_publication_evidence_is_missing(): void
    {
        $this->seed(DatabaseSeeder::class);
        DB::table('form_assignments')->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Existing pre-new FormEngine assignment or publication evidence does not match.',
        );
        $this->app->make(LocalPreNewFormSeeder::class)->run();
    }

    public function test_second_seed_fails_closed_when_assignment_time_changes(): void
    {
        $this->seed(DatabaseSeeder::class);
        DB::table('form_assignments')->update([
            'effective_at' => '2030-01-01 00:00:00+07',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Existing pre-new FormEngine assignment or publication evidence does not match.',
        );
        $this->app->make(LocalPreNewFormSeeder::class)->run();
    }

    public function test_second_seed_binds_consent_to_the_exact_acknowledgement_field(): void
    {
        $this->seed(DatabaseSeeder::class);
        DB::table('form_fields')
            ->where('id', '30000000-0000-4000-8000-000000000304')
            ->update([
                'consent_document_version_id' => null,
                'consent_document_checksum' => null,
            ]);
        DB::table('form_fields')
            ->where('id', '30000000-0000-4000-8000-000000000301')
            ->update([
                'consent_document_version_id' => '30000000-0000-4000-8000-000000000002',
                'consent_document_checksum' => hash(
                    'sha256',
                    LocalApplicationConsentSeeder::Content,
                ),
            ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Existing pre-new FormEngine child schema does not match.');
        $this->app->make(LocalPreNewFormSeeder::class)->run();
    }

    public function test_application_consent_seed_fails_closed_when_document_identity_changes(): void
    {
        $this->seed(DatabaseSeeder::class);
        DB::table('consent_documents')
            ->where('id', LocalApplicationConsentSeeder::DocumentId)
            ->update(['purpose' => 'rewritten']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Existing application-consent fixture does not match.');
        $this->app->make(LocalApplicationConsentSeeder::class)->run();
    }

    public function test_second_seed_rejects_form_version_publication_timestamp_drift(): void
    {
        $this->requireMutableFixtureDatabase();
        $this->seed(DatabaseSeeder::class);
        DB::table('form_versions')
            ->where('id', '30000000-0000-4000-8000-000000000102')
            ->update(['published_at' => '2026-07-30 00:00:00+07:00']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Existing pre-new FormEngine definition does not match.');
        $this->app->make(LocalPreNewFormSeeder::class)->run();
    }

    public function test_second_seed_rejects_child_creation_timestamp_drift(): void
    {
        $this->requireMutableFixtureDatabase();
        $this->seed(DatabaseSeeder::class);
        DB::table('form_sections')
            ->where('id', '30000000-0000-4000-8000-000000000201')
            ->update(['created_at' => '2026-07-30 00:00:00+07:00']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Existing pre-new FormEngine child timestamps do not match.',
        );
        $this->app->make(LocalPreNewFormSeeder::class)->run();
    }

    public function test_second_seed_rejects_consent_publication_timestamp_drift(): void
    {
        $this->requireMutableFixtureDatabase();
        $this->seed(DatabaseSeeder::class);
        DB::table('consent_document_versions')
            ->where('id', LocalApplicationConsentSeeder::VersionId)
            ->update(['published_at' => '2026-07-30 00:00:00+07:00']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Existing application-consent fixture does not match.');
        $this->app->make(LocalApplicationConsentSeeder::class)->run();
    }

    public function test_second_seed_allows_unrelated_future_form_publications_to_coexist(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->insertUnrelatedPublishedFixture();

        $this->app->make(LocalPreNewFormSeeder::class)->run();

        $this->assertDatabaseCount('form_versions', 2);
        $this->assertDatabaseCount('form_assignments', 2);
        $this->assertDatabaseCount('form_publication_events', 2);
    }

    private function context(
        string $formKey = 'initial_application',
        string $courseSessionId = '10000000-0000-4000-8000-000000000001',
        string $courseTypeKey = 'meditation',
        string $phase = 'pre-new',
        string $applicantIntent = 'trainee',
        ?string $alumniEligibilityKey = null,
        string $layMonasticCategory = 'lay',
        ?string $approvedCategory = 'female',
        string $locale = 'th',
    ): FormContext {
        return new FormContext(
            formKey: $formKey,
            courseSessionId: $courseSessionId,
            courseTypeKey: $courseTypeKey,
            phase: $phase,
            applicantIntent: $applicantIntent,
            alumniEligibilityKey: $alumniEligibilityKey,
            layMonasticCategory: $layMonasticCategory,
            approvedCategory: $approvedCategory,
            locale: $locale,
        );
    }

    private function requireMutableFixtureDatabase(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            self::markTestSkipped(
                'PostgreSQL publication guards prevent constructing timestamp drift.',
            );
        }
    }

    private function insertUnrelatedPublishedFixture(): void
    {
        $now = now();
        $checksum = hash('sha256', LocalApplicationConsentSeeder::Content);
        DB::table('form_definitions')->insert([
            'id' => '31000000-0000-4000-8000-000000000001',
            'form_key' => 'future_form',
            'title_th' => 'แบบฟอร์มในอนาคต',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('form_versions')->insert([
            'id' => '31000000-0000-4000-8000-000000000002',
            'definition_id' => '31000000-0000-4000-8000-000000000001',
            'version_number' => 1,
            'locale' => 'th',
            'status' => 'draft',
            'schema_checksum' => str_repeat('e', 64),
            'consent_document_version_id' => LocalApplicationConsentSeeder::VersionId,
            'consent_document_checksum' => $checksum,
            'published_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('form_sections')->insert([
            'id' => '31000000-0000-4000-8000-000000000003',
            'form_version_id' => '31000000-0000-4000-8000-000000000002',
            'semantic_key' => 'future',
            'title_th' => 'ส่วนในอนาคต',
            'description_th' => null,
            'display_order' => 1,
            'created_at' => $now,
        ]);
        DB::table('form_fields')->insert([
            'id' => '31000000-0000-4000-8000-000000000004',
            'form_version_id' => '31000000-0000-4000-8000-000000000002',
            'section_id' => '31000000-0000-4000-8000-000000000003',
            'semantic_key' => 'future.consent',
            'field_type' => 'acknowledgement',
            'label_th' => 'ความยินยอมในอนาคต',
            'help_text_th' => null,
            'placeholder_th' => null,
            'required_rule' => json_encode(true, JSON_THROW_ON_ERROR),
            'validation_rules' => json_encode([], JSON_THROW_ON_ERROR),
            'visibility_rule' => null,
            'hidden_answer_policy' => 'retain',
            'renderer_hint' => null,
            'initial_value' => json_encode(false, JSON_THROW_ON_ERROR),
            'consent_document_version_id' => LocalApplicationConsentSeeder::VersionId,
            'consent_document_checksum' => $checksum,
            'display_order' => 1,
            'created_at' => $now,
        ]);
        DB::table('form_versions')
            ->where('id', '31000000-0000-4000-8000-000000000002')
            ->update([
                'status' => 'published',
                'published_at' => $now,
                'updated_at' => $now,
            ]);
        DB::table('form_assignments')->insert([
            'id' => '31000000-0000-4000-8000-000000000005',
            'form_version_id' => '31000000-0000-4000-8000-000000000002',
            'course_session_id' => '10000000-0000-4000-8000-000000000002',
            'course_type_key' => 'service',
            'phase' => 'pre-new',
            'applicant_intent' => 'staff_applicant',
            'alumni_eligibility_key' => 'none',
            'lay_monastic_category' => 'lay',
            'approved_category' => 'male',
            'locale' => 'th',
            'effective_at' => $now,
            'created_at' => $now,
        ]);
        DB::table('form_publication_events')->insert([
            'id' => '31000000-0000-4000-8000-000000000006',
            'form_version_id' => '31000000-0000-4000-8000-000000000002',
            'author_id' => 'future-author',
            'approver_id' => 'future-approver',
            'reason' => 'Coexistence fixture',
            'checks' => json_encode(['schema_valid' => true], JSON_THROW_ON_ERROR),
            'published_at' => $now,
            'created_at' => $now,
        ]);
    }
}
