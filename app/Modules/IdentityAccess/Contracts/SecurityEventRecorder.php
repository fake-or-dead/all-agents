<?php

namespace App\Modules\IdentityAccess\Contracts;

interface SecurityEventRecorder
{
    /**
     * @param  array<string, scalar|null>  $context
     */
    public function record(
        string $actorType,
        string $actorId,
        string $action,
        string $resourceType,
        string $resourceId,
        string $outcome,
        string $correlationId,
        array $context = [],
    ): void;
}
