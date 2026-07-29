<?php

namespace App\Modules\FormEngine\Data;

final readonly class SchemaResolution
{
    public function __construct(
        public string $status,
        public ?FormSchema $schema,
    ) {}

    public static function resolved(FormSchema $schema): self
    {
        return new self('resolved', $schema);
    }

    public static function unsupportedPersona(): self
    {
        return new self('unsupported_persona', null);
    }

    public static function noAssignment(): self
    {
        return new self('no_assignment', null);
    }
}
