<?php

namespace App\Modules\FormEngine\Data;

final readonly class FormSchema
{
    /**
     * @param  list<FormSection>  $sections
     */
    public function __construct(
        public string $formKey,
        public string $versionId,
        public int $versionNumber,
        public string $locale,
        public string $consentVersionId,
        public string $consentChecksum,
        public array $sections,
    ) {}
}
