<?php

namespace App\Modules\PlatformOperations\Contracts;

interface PlatformProbeWorker
{
    public function completeOutboxEvent(string $outboxEventId): void;
}
