<?php

namespace App\Modules\PlatformOperations\Infrastructure\Completion;

use App\Modules\PlatformOperations\Contracts\CompletionAdapter;
use App\Modules\PlatformOperations\Data\CompletionResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use LogicException;
use Psr\Log\LoggerInterface;

final readonly class StructuredLogCompletionAdapter implements CompletionAdapter
{
    public function __construct(
        private ConnectionInterface $database,
        private LoggerInterface $logger,
    ) {}

    public function complete(string $probeId, string $correlationId): CompletionResult
    {
        $completionCode = 'structured-log.completed';
        $inserted = $this->database->table('platform_completion_receipts')->insertOrIgnore([
            'correlation_id' => $correlationId,
            'probe_id' => $probeId,
            'adapter' => $this->name(),
            'completion_code' => $completionCode,
            'completed_at' => CarbonImmutable::now(),
        ]);

        $receipt = $this->database
            ->table('platform_completion_receipts')
            ->where('correlation_id', $correlationId)
            ->firstOrFail();

        if ($receipt->probe_id !== $probeId || $receipt->adapter !== $this->name()) {
            throw new LogicException('Completion correlation ID was reused for a different operation.');
        }

        if ($inserted === 1) {
            $this->logger->info('Platform probe completed.', [
                'probe_id' => $probeId,
                'correlation_id' => $correlationId,
            ]);
        }

        return new CompletionResult($receipt->completion_code);
    }

    public function name(): string
    {
        return 'structured-log';
    }
}
