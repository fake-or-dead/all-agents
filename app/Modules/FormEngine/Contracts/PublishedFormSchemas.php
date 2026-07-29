<?php

namespace App\Modules\FormEngine\Contracts;

use App\Modules\FormEngine\Data\FormContext;
use App\Modules\FormEngine\Data\SchemaResolution;

interface PublishedFormSchemas
{
    public function schemaFor(FormContext $context): SchemaResolution;
}
