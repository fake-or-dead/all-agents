<?php

namespace App\Modules\PlatformOperations\Contracts;

use App\Modules\PlatformOperations\Data\CompletionResult;

interface CompletionAdapter
{
    /**
     * Implementations must treat the correlation ID as an idempotency key.
     */
    public function complete(string $probeId, string $correlationId): CompletionResult;

    public function name(): string;
}
