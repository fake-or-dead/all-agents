<?php

namespace Database\Seeders;

use App\Modules\FormEngine\Application\FormPublicationValidator;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LocalPreNewFormSeeder extends Seeder
{
    private const string DefinitionId = '30000000-0000-4000-8000-000000000101';

    private const string VersionId = '30000000-0000-4000-8000-000000000102';

    public function run(): void
    {
        if (! app(Application::class)->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'Synthetic FormEngine fixtures may only be seeded locally or in tests.',
            );
        }

        $consent = DB::table('consent_document_versions')
            ->where('id', LocalApplicationConsentSeeder::VersionId)
            ->where('status', 'published')
            ->sole();
        $now = CarbonImmutable::parse('2026-07-29T00:00:00Z');
        $sections = [
            [
                'id' => '30000000-0000-4000-8000-000000000201',
                'semantic_key' => 'profile',
                'title_th' => 'ข้อมูลผู้สมัคร',
                'description_th' => 'ตรวจสอบข้อมูลติดต่อที่ใช้กับใบสมัครนี้',
                'display_order' => 1,
            ],
            [
                'id' => '30000000-0000-4000-8000-000000000202',
                'semantic_key' => 'preferences',
                'title_th' => 'ความต้องการระหว่างหลักสูตร',
                'description_th' => 'ระบุข้อมูลที่จำเป็นต่อการจัดหลักสูตร',
                'display_order' => 2,
            ],
            [
                'id' => '30000000-0000-4000-8000-000000000203',
                'semantic_key' => 'consent',
                'title_th' => 'ความยินยอม',
                'description_th' => 'อ่านเอกสารเวอร์ชันที่กำหนดก่อนยืนยัน',
                'display_order' => 3,
            ],
        ];
        $fields = [
            [
                'id' => '30000000-0000-4000-8000-000000000301',
                'section_id' => $sections[0]['id'],
                'semantic_key' => 'profile.phone',
                'field_type' => 'phone',
                'label_th' => 'หมายเลขโทรศัพท์',
                'help_text_th' => null,
                'placeholder_th' => '08XXXXXXXX',
                'required_rule' => true,
                'validation_rules' => [
                    ['rule' => 'phone', 'messageKey' => 'validation.phone'],
                ],
                'visibility_rule' => null,
                'hidden_answer_policy' => 'retain',
                'renderer_hint' => null,
                'initial_value' => null,
                'consent_document_version_id' => null,
                'consent_document_checksum' => null,
                'display_order' => 1,
            ],
            [
                'id' => '30000000-0000-4000-8000-000000000302',
                'section_id' => $sections[1]['id'],
                'semantic_key' => 'preferences.needs_dinner',
                'field_type' => 'single_choice',
                'label_th' => 'ต้องการอาหารเย็นในวันเดินทางมาถึงหรือไม่',
                'help_text_th' => null,
                'placeholder_th' => null,
                'required_rule' => true,
                'validation_rules' => [],
                'visibility_rule' => null,
                'hidden_answer_policy' => 'retain',
                'renderer_hint' => 'cards',
                'initial_value' => null,
                'consent_document_version_id' => null,
                'consent_document_checksum' => null,
                'display_order' => 1,
            ],
            [
                'id' => '30000000-0000-4000-8000-000000000303',
                'section_id' => $sections[1]['id'],
                'semantic_key' => 'preferences.dinner_reason',
                'field_type' => 'long_text',
                'label_th' => 'เหตุผลที่ต้องการอาหารเย็น',
                'help_text_th' => 'ระบุเฉพาะข้อมูลที่จำเป็น',
                'placeholder_th' => null,
                'required_rule' => false,
                'validation_rules' => [
                    [
                        'rule' => 'max_length',
                        'parameters' => ['value' => 500],
                        'messageKey' => 'validation.max_length',
                    ],
                ],
                'visibility_rule' => [
                    'question' => 'preferences.needs_dinner',
                    'operator' => 'equals',
                    'value' => 'yes',
                ],
                'hidden_answer_policy' => 'clear',
                'renderer_hint' => 'textarea',
                'initial_value' => null,
                'consent_document_version_id' => null,
                'consent_document_checksum' => null,
                'display_order' => 2,
            ],
            [
                'id' => '30000000-0000-4000-8000-000000000304',
                'section_id' => $sections[2]['id'],
                'semantic_key' => 'consent.application',
                'field_type' => 'acknowledgement',
                'label_th' => 'ข้าพเจ้าอ่านและยอมรับเอกสารยินยอมการสมัครหลักสูตร',
                'help_text_th' => null,
                'placeholder_th' => null,
                'required_rule' => true,
                'validation_rules' => [
                    ['rule' => 'required', 'messageKey' => 'validation.required'],
                ],
                'visibility_rule' => null,
                'hidden_answer_policy' => 'retain',
                'renderer_hint' => null,
                'initial_value' => false,
                'consent_document_version_id' => LocalApplicationConsentSeeder::VersionId,
                'consent_document_checksum' => (string) $consent->content_checksum,
                'display_order' => 1,
            ],
        ];
        $options = [
            [
                'id' => '30000000-0000-4000-8000-000000000401',
                'field_id' => $fields[1]['id'],
                'semantic_key' => 'yes',
                'value' => 'yes',
                'label_th' => 'ต้องการ',
                'display_order' => 1,
            ],
            [
                'id' => '30000000-0000-4000-8000-000000000402',
                'field_id' => $fields[1]['id'],
                'semantic_key' => 'no',
                'value' => 'no',
                'label_th' => 'ไม่ต้องการ',
                'display_order' => 2,
            ],
        ];
        $publicationSections = array_map(
            static fn (array $section): array => [
                ...$section,
                'fields' => array_map(
                    static fn (array $field): array => [
                        ...$field,
                        'options' => array_values(array_filter(
                            $options,
                            static fn (array $option): bool => $option['field_id'] === $field['id'],
                        )),
                    ],
                    array_values(array_filter(
                        $fields,
                        static fn (array $field): bool => $field['section_id'] === $section['id'],
                    )),
                ),
            ],
            $sections,
        );
        app(FormPublicationValidator::class)->validate($publicationSections);
        $schemaChecksum = hash(
            'sha256',
            json_encode([$sections, $fields, $options], JSON_THROW_ON_ERROR),
        );
        $existing = DB::table('form_versions')->where('id', self::VersionId)->first();

        if ($existing !== null) {
            if (
                $existing->definition_id !== self::DefinitionId
                || (int) $existing->version_number !== 1
                || $existing->locale !== 'th'
                || $existing->status !== 'published'
                || $existing->schema_checksum !== $schemaChecksum
                || $existing->consent_document_version_id
                    !== LocalApplicationConsentSeeder::VersionId
                || $existing->consent_document_checksum !== $consent->content_checksum
            ) {
                throw new RuntimeException('Existing pre-new FormEngine fixture does not match.');
            }

            $this->assertExistingFootprint(
                $sections,
                $fields,
                $options,
                $schemaChecksum,
                (string) $consent->content_checksum,
                $now,
            );

            return;
        }

        DB::transaction(function () use (
            $consent,
            $fields,
            $now,
            $options,
            $schemaChecksum,
            $sections,
        ): void {
            DB::table('form_definitions')->insert([
                'id' => self::DefinitionId,
                'form_key' => 'initial_application',
                'title_th' => 'แบบฟอร์มสมัครหลักสูตรครั้งแรก',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('form_versions')->insert([
                'id' => self::VersionId,
                'definition_id' => self::DefinitionId,
                'version_number' => 1,
                'locale' => 'th',
                'status' => 'draft',
                'schema_checksum' => $schemaChecksum,
                'consent_document_version_id' => LocalApplicationConsentSeeder::VersionId,
                'consent_document_checksum' => $consent->content_checksum,
                'published_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($sections as $section) {
                DB::table('form_sections')->insert([
                    ...$section,
                    'form_version_id' => self::VersionId,
                    'created_at' => $now,
                ]);
            }

            foreach ($fields as $field) {
                DB::table('form_fields')->insert([
                    ...$field,
                    'form_version_id' => self::VersionId,
                    'required_rule' => json_encode($field['required_rule'], JSON_THROW_ON_ERROR),
                    'validation_rules' => json_encode(
                        $field['validation_rules'],
                        JSON_THROW_ON_ERROR,
                    ),
                    'visibility_rule' => $field['visibility_rule'] === null
                        ? null
                        : json_encode($field['visibility_rule'], JSON_THROW_ON_ERROR),
                    'initial_value' => json_encode($field['initial_value'], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                ]);
            }

            DB::table('form_options')->insert(array_map(
                static fn (array $option): array => [
                    ...$option,
                    'created_at' => $now,
                ],
                $options,
            ));
            DB::table('form_versions')->where('id', self::VersionId)->update([
                'status' => 'published',
                'published_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('form_assignments')->insert([
                'id' => '30000000-0000-4000-8000-000000000501',
                'form_version_id' => self::VersionId,
                'course_session_id' => '10000000-0000-4000-8000-000000000001',
                'course_type_key' => 'meditation',
                'phase' => 'pre-new',
                'applicant_intent' => 'trainee',
                'alumni_eligibility_key' => 'none',
                'lay_monastic_category' => 'lay',
                'approved_category' => 'female',
                'locale' => 'th',
                'effective_at' => $now,
                'created_at' => $now,
            ]);
            DB::table('form_publication_events')->insert([
                'id' => '30000000-0000-4000-8000-000000000601',
                'form_version_id' => self::VersionId,
                'author_id' => 'local-form-author',
                'approver_id' => 'local-form-approver',
                'reason' => 'Deterministic local pre-new fixture',
                'checks' => json_encode(['schema_valid' => true], JSON_THROW_ON_ERROR),
                'published_at' => $now,
                'created_at' => $now,
            ]);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $sections
     * @param  list<array<string, mixed>>  $fields
     * @param  list<array<string, mixed>>  $options
     */
    private function assertExistingFootprint(
        array $sections,
        array $fields,
        array $options,
        string $schemaChecksum,
        string $consentChecksum,
        CarbonImmutable $fixtureTime,
    ): void {
        $definition = DB::table('form_definitions')->where('id', self::DefinitionId)->first();
        $version = DB::table('form_versions')->where('id', self::VersionId)->first();

        if (
            $definition === null
            || $definition->form_key !== 'initial_application'
            || $definition->title_th !== 'แบบฟอร์มสมัครหลักสูตรครั้งแรก'
            || ! $this->timestampMatches($definition->created_at, $fixtureTime)
            || ! $this->timestampMatches($definition->updated_at, $fixtureTime)
            || $version === null
            || $version->definition_id !== self::DefinitionId
            || (int) $version->version_number !== 1
            || $version->locale !== 'th'
            || $version->status !== 'published'
            || $version->schema_checksum !== $schemaChecksum
            || $version->consent_document_version_id
                !== LocalApplicationConsentSeeder::VersionId
            || $version->consent_document_checksum !== $consentChecksum
            || ! $this->timestampMatches($version->published_at, $fixtureTime)
            || ! $this->timestampMatches($version->created_at, $fixtureTime)
            || ! $this->timestampMatches($version->updated_at, $fixtureTime)
        ) {
            throw new RuntimeException('Existing pre-new FormEngine definition does not match.');
        }

        $actualSections = DB::table('form_sections')
            ->where('form_version_id', self::VersionId)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $section): array => [
                'id' => (string) $section->id,
                'semantic_key' => (string) $section->semantic_key,
                'title_th' => (string) $section->title_th,
                'description_th' => $section->description_th === null
                    ? null
                    : (string) $section->description_th,
                'display_order' => (int) $section->display_order,
            ])
            ->all();
        $actualFields = DB::table('form_fields')
            ->where('form_version_id', self::VersionId)
            ->orderBy('id')
            ->get()
            ->map(static fn (object $field): array => [
                'id' => (string) $field->id,
                'section_id' => (string) $field->section_id,
                'semantic_key' => (string) $field->semantic_key,
                'field_type' => (string) $field->field_type,
                'label_th' => (string) $field->label_th,
                'help_text_th' => $field->help_text_th === null
                    ? null
                    : (string) $field->help_text_th,
                'placeholder_th' => $field->placeholder_th === null
                    ? null
                    : (string) $field->placeholder_th,
                'required_rule' => json_decode(
                    (string) $field->required_rule,
                    true,
                    flags: JSON_THROW_ON_ERROR,
                ),
                'validation_rules' => json_decode(
                    (string) $field->validation_rules,
                    true,
                    flags: JSON_THROW_ON_ERROR,
                ),
                'visibility_rule' => $field->visibility_rule === null
                    ? null
                    : json_decode(
                        (string) $field->visibility_rule,
                        true,
                        flags: JSON_THROW_ON_ERROR,
                    ),
                'hidden_answer_policy' => (string) $field->hidden_answer_policy,
                'renderer_hint' => $field->renderer_hint === null
                    ? null
                    : (string) $field->renderer_hint,
                'initial_value' => $field->initial_value === null
                    ? null
                    : json_decode(
                        (string) $field->initial_value,
                        true,
                        flags: JSON_THROW_ON_ERROR,
                    ),
                'consent_document_version_id' => $field->consent_document_version_id === null
                    ? null
                    : (string) $field->consent_document_version_id,
                'consent_document_checksum' => $field->consent_document_checksum === null
                    ? null
                    : (string) $field->consent_document_checksum,
                'display_order' => (int) $field->display_order,
            ])
            ->all();
        $actualOptions = DB::table('form_options')
            ->whereIn('field_id', array_column($fields, 'id'))
            ->orderBy('id')
            ->get()
            ->map(static fn (object $option): array => [
                'id' => (string) $option->id,
                'field_id' => (string) $option->field_id,
                'semantic_key' => (string) $option->semantic_key,
                'value' => (string) $option->value,
                'label_th' => (string) $option->label_th,
                'display_order' => (int) $option->display_order,
            ])
            ->all();
        $actualChecksum = hash(
            'sha256',
            json_encode(
                [$actualSections, $actualFields, $actualOptions],
                JSON_THROW_ON_ERROR,
            ),
        );

        if ($actualChecksum !== $schemaChecksum) {
            throw new RuntimeException('Existing pre-new FormEngine child schema does not match.');
        }

        if (
            ! $this->allCreatedAtMatch(
                'form_sections',
                array_column($sections, 'id'),
                $fixtureTime,
            )
            || ! $this->allCreatedAtMatch(
                'form_fields',
                array_column($fields, 'id'),
                $fixtureTime,
            )
            || ! $this->allCreatedAtMatch(
                'form_options',
                array_column($options, 'id'),
                $fixtureTime,
            )
        ) {
            throw new RuntimeException(
                'Existing pre-new FormEngine child timestamps do not match.',
            );
        }

        $assignment = DB::table('form_assignments')
            ->where([
                'id' => '30000000-0000-4000-8000-000000000501',
                'form_version_id' => self::VersionId,
                'course_session_id' => '10000000-0000-4000-8000-000000000001',
                'course_type_key' => 'meditation',
                'phase' => 'pre-new',
                'applicant_intent' => 'trainee',
                'alumni_eligibility_key' => 'none',
                'lay_monastic_category' => 'lay',
                'approved_category' => 'female',
                'locale' => 'th',
            ])
            ->first();
        $assignmentMatches = $assignment !== null
            && CarbonImmutable::parse(
                (string) $assignment->effective_at,
                'UTC',
            )->equalTo($fixtureTime)
            && $this->timestampMatches($assignment->created_at, $fixtureTime);
        $event = DB::table('form_publication_events')
            ->where('id', '30000000-0000-4000-8000-000000000601')
            ->first();
        $eventMatches = $event !== null
            && $event->form_version_id === self::VersionId
            && $event->author_id === 'local-form-author'
            && $event->approver_id === 'local-form-approver'
            && $event->reason === 'Deterministic local pre-new fixture'
            && CarbonImmutable::parse(
                (string) $event->published_at,
                'UTC',
            )->equalTo($fixtureTime)
            && $this->timestampMatches($event->created_at, $fixtureTime)
            && json_decode((string) $event->checks, true, flags: JSON_THROW_ON_ERROR)
                === ['schema_valid' => true];

        if (
            DB::table('form_assignments')->where('form_version_id', self::VersionId)->count() !== 1
            || DB::table('form_publication_events')
                ->where('form_version_id', self::VersionId)
                ->count() !== 1
            || ! $assignmentMatches
            || ! $eventMatches
        ) {
            throw new RuntimeException(
                'Existing pre-new FormEngine assignment or publication evidence does not match.',
            );
        }

        $consentReferences = DB::table('form_fields')
            ->where('form_version_id', self::VersionId)
            ->where('consent_document_version_id', LocalApplicationConsentSeeder::VersionId)
            ->where('consent_document_checksum', $consentChecksum)
            ->count();

        if (
            $consentReferences !== 1
            || DB::table('form_fields')
                ->where('form_version_id', self::VersionId)
                ->whereNotNull('consent_document_version_id')
                ->count() !== 1
        ) {
            throw new RuntimeException(
                'Existing pre-new FormEngine consent reference does not match.',
            );
        }
    }

    /**
     * @param  list<string>  $ids
     */
    private function allCreatedAtMatch(
        string $table,
        array $ids,
        CarbonImmutable $expected,
    ): bool {
        $timestamps = DB::table($table)
            ->whereIn('id', $ids)
            ->pluck('created_at');

        return $timestamps->count() === count($ids)
            && $timestamps->every(
                fn (mixed $actual): bool => $this->timestampMatches($actual, $expected),
            );
    }

    private function timestampMatches(mixed $actual, CarbonImmutable $expected): bool
    {
        return is_string($actual)
            && CarbonImmutable::parse($actual, 'UTC')->equalTo($expected);
    }
}
