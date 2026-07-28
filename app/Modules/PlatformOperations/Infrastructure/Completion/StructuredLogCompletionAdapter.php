<?php

namespace App\Modules\PlatformOperations\Infrastructure\Completion;

use App\Modules\PlatformOperations\Contracts\CompletionAdapter;
use App\Modules\PlatformOperations\Data\CompletionResult;
use Psr\Log\LoggerInterface;

final readonly class StructuredLogCompletionAdapter implements CompletionAdapter
{
    public function __construct(private LoggerInterface $logger) {}

    public function complete(string $probeId, string $correlationId): CompletionResult
    {
        $this->logger->info('Platform probe completed.', [
            'probe_id' => $probeId,
            'correlation_id' => $correlationId,
        ]);

        return new CompletionResult('structured-log.completed');
    }

    public function name(): string
    {
        return 'structured-log';
    }
}
