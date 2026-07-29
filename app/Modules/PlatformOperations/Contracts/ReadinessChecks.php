<?php

namespace App\Modules\PlatformOperations\Contracts;

use App\Modules\PlatformOperations\Data\ReadinessReport;

interface ReadinessChecks
{
    public function run(): ReadinessReport;
}
