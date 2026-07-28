<?php

namespace App\Modules\ReferenceData\Data;

final readonly class ReferenceResult
{
    /**
     * @param  list<array{id: string, code: string, label: string, postcode?: string}>  $items
     */
    public function __construct(
        public string $status,
        public string $parentType,
        public ?string $parentId,
        public array $items,
    ) {}
}
