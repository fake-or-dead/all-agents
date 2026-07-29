<?php

namespace App\Modules\PlatformOperations\Infrastructure\Completion;

use App\Modules\PlatformOperations\Contracts\CompletionAdapter;
use App\Modules\PlatformOperations\Data\CompletionResult;

final class DeterministicFakeCompletionAdapter implements CompletionAdapter
{
    public function complete(string $probeId, string $correlationId): CompletionResult
    {
        return new CompletionResult('deterministic-fake.completed');
    }

    public function name(): string
    {
        return 'deterministic-fake';
    }
}
