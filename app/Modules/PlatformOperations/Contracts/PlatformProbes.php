<?php

namespace App\Modules\PlatformOperations\Contracts;

use App\Modules\IdentityAccess\Data\Actor;
use App\Modules\PlatformOperations\Data\ProbeView;

interface PlatformProbes
{
    public function request(Actor $actor, string $idempotencyKey): ProbeView;

    public function find(Actor $actor, string $probeId): ?ProbeView;
}
