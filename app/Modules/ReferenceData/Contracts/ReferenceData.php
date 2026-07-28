<?php

namespace App\Modules\ReferenceData\Contracts;

use App\Modules\ReferenceData\Data\ReferenceResult;

interface ReferenceData
{
    public function topLevel(string $type): ReferenceResult;

    public function children(string $parentType, string $parentId): ReferenceResult;
}
