<?php

namespace App\Modules\FormEngine\Data;

final readonly class FormField
{
    /**
     * @param  bool|array<string, mixed>  $required
     * @param  list<array<string, mixed>>  $validation
     * @param  array<string, mixed>|null  $visibility
     * @param  list<FormOption>  $options
     */
    public function __construct(
        public string $key,
        public string $type,
        public string $label,
        public ?string $helpText,
        public ?string $placeholder,
        public bool|array $required,
        public array $validation,
        public ?array $visibility,
        public string $hiddenAnswerPolicy,
        public ?string $rendererHint,
        public mixed $initialValue,
        public ?string $consentVersionId,
        public ?string $consentChecksum,
        public array $options,
    ) {}
}
