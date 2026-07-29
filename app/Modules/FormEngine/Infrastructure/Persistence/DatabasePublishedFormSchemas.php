<?php

namespace App\Modules\FormEngine\Infrastructure\Persistence;

use App\Modules\FormEngine\Contracts\PublishedFormSchemas;
use App\Modules\FormEngine\Data\FormContext;
use App\Modules\FormEngine\Data\FormField;
use App\Modules\FormEngine\Data\FormOption;
use App\Modules\FormEngine\Data\FormSchema;
use App\Modules\FormEngine\Data\FormSection;
use App\Modules\FormEngine\Data\SchemaResolution;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DatabasePublishedFormSchemas implements PublishedFormSchemas
{
    public function schemaFor(FormContext $context): SchemaResolution
    {
        if (
            $context->approvedCategory === null
            || ! in_array($context->approvedCategory, ['female', 'male', 'monastic'], true)
        ) {
            return SchemaResolution::unsupportedPersona();
        }

        $assignment = DB::table('form_assignments')
            ->join('form_versions', 'form_versions.id', '=', 'form_assignments.form_version_id')
            ->join('form_definitions', 'form_definitions.id', '=', 'form_versions.definition_id')
            ->where('form_definitions.form_key', $context->formKey)
            ->where('form_versions.status', 'published')
            ->where('form_versions.locale', $context->locale)
            ->where('form_assignments.course_session_id', $context->courseSessionId)
            ->where('form_assignments.course_type_key', $context->courseTypeKey)
            ->where('form_assignments.phase', $context->phase)
            ->where('form_assignments.applicant_intent', $context->applicantIntent)
            ->where('form_assignments.lay_monastic_category', $context->layMonasticCategory)
            ->where('form_assignments.approved_category', $context->approvedCategory)
            ->where('form_assignments.locale', $context->locale)
            ->where(
                'form_assignments.alumni_eligibility_key',
                $context->alumniEligibilityKey ?? 'none',
            )
            ->where('form_assignments.effective_at', '<=', now())
            ->orderByDesc('form_assignments.effective_at')
            ->orderByDesc('form_versions.version_number')
            ->select([
                'form_definitions.form_key',
                'form_versions.id as version_id',
                'form_versions.version_number',
                'form_versions.locale',
                'form_versions.consent_document_version_id',
                'form_versions.consent_document_checksum',
            ])
            ->first();

        if ($assignment === null) {
            return SchemaResolution::noAssignment();
        }

        $sections = DB::table('form_sections')
            ->where('form_version_id', $assignment->version_id)
            ->orderBy('display_order')
            ->get()
            ->map(fn (object $section): FormSection => new FormSection(
                key: (string) $section->semantic_key,
                title: (string) $section->title_th,
                description: $section->description_th === null
                    ? null
                    : (string) $section->description_th,
                fields: $this->fields((string) $assignment->version_id, (string) $section->id),
            ))
            ->all();

        return SchemaResolution::resolved(new FormSchema(
            formKey: (string) $assignment->form_key,
            versionId: (string) $assignment->version_id,
            versionNumber: (int) $assignment->version_number,
            locale: (string) $assignment->locale,
            consentVersionId: (string) $assignment->consent_document_version_id,
            consentChecksum: (string) $assignment->consent_document_checksum,
            sections: $sections,
        ));
    }

    /**
     * @return list<FormField>
     */
    private function fields(string $versionId, string $sectionId): array
    {
        return DB::table('form_fields')
            ->where('form_version_id', $versionId)
            ->where('section_id', $sectionId)
            ->orderBy('display_order')
            ->get()
            ->map(fn (object $field): FormField => new FormField(
                key: (string) $field->semantic_key,
                type: (string) $field->field_type,
                label: (string) $field->label_th,
                helpText: $field->help_text_th === null ? null : (string) $field->help_text_th,
                placeholder: $field->placeholder_th === null
                    ? null
                    : (string) $field->placeholder_th,
                required: $this->decodeRequired((string) $field->required_rule),
                validation: $this->decodeList((string) $field->validation_rules),
                visibility: $field->visibility_rule === null
                    ? null
                    : $this->decodeObject((string) $field->visibility_rule),
                hiddenAnswerPolicy: (string) $field->hidden_answer_policy,
                rendererHint: $field->renderer_hint === null
                    ? null
                    : (string) $field->renderer_hint,
                initialValue: $field->initial_value === null
                    ? null
                    : json_decode((string) $field->initial_value, true, flags: JSON_THROW_ON_ERROR),
                consentVersionId: $field->consent_document_version_id === null
                    ? null
                    : (string) $field->consent_document_version_id,
                consentChecksum: $field->consent_document_checksum === null
                    ? null
                    : (string) $field->consent_document_checksum,
                options: $this->options((string) $field->id),
            ))
            ->all();
    }

    /**
     * @return list<FormOption>
     */
    private function options(string $fieldId): array
    {
        return DB::table('form_options')
            ->where('field_id', $fieldId)
            ->orderBy('display_order')
            ->get()
            ->map(static fn (object $option): FormOption => new FormOption(
                key: (string) $option->semantic_key,
                value: (string) $option->value,
                label: (string) $option->label_th,
            ))
            ->all();
    }

    /**
     * @return bool|array<string, mixed>
     */
    private function decodeRequired(string $json): bool|array
    {
        $value = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (is_bool($value)) {
            return $value;
        }

        if (! is_array($value) || array_is_list($value)) {
            throw new RuntimeException('Stored required rule is invalid.');
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeObject(string $json): array
    {
        $value = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($value) || array_is_list($value)) {
            throw new RuntimeException('Stored form rule is invalid.');
        }

        return $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeList(string $json): array
    {
        $value = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($value) || ! array_is_list($value)) {
            throw new RuntimeException('Stored validation rules are invalid.');
        }

        return $value;
    }
}
