<?php

namespace App\Modules\FormEngine\Data;

final readonly class FormOption
{
    public function __construct(
        public string $key,
        public string $value,
        public string $label,
    ) {}
}
