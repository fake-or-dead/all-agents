<?php

namespace App\Modules\FormEngine\Data;

final readonly class FormSection
{
    /**
     * @param  list<FormField>  $fields
     */
    public function __construct(
        public string $key,
        public string $title,
        public ?string $description,
        public array $fields,
    ) {}
}
