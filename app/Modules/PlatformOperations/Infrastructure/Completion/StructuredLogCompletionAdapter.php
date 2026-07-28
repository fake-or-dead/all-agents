<?php

namespace App\Modules\PlatformOperations\Infrastructure\Completion;

use App\Modules\PlatformOperations\Contracts\CompletionAdapter;
use App\Modules\PlatformOperations\Data\CompletionResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use stdClass;

final readonly class StructuredLogCompletionAdapter implements CompletionAdapter
{
    public function __construct(
        private ConnectionInterface $database,
        private LoggerInterface $logger,
    ) {}

    public function complete(string $probeId, string $correlationId): CompletionResult
    {
        $completionCode = 'structured-log.completed';
        $receipt = $this->reserve($probeId, $correlationId, $completionCode);

        if ($receipt->status === 'delivered') {
            return new CompletionResult($receipt->completion_code);
        }

        $this->database
            ->table('platform_completion_receipts')
            ->where('correlation_id', $correlationId)
            ->where('status', 'pending')
            ->increment('attempts');

        // The log/provider boundary is at-least-once. Consumers must deduplicate
        // using delivery_key if a process dies after the effect but before delivery
        // state is committed.
        $this->logger->info('Platform probe completed.', [
            'probe_id' => $probeId,
            'correlation_id' => $correlationId,
            'delivery_key' => $correlationId,
        ]);

        $this->database->transaction(function () use ($probeId, $correlationId): void {
            $receipt = $this->database
                ->table('platform_completion_receipts')
                ->where('correlation_id', $correlationId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertSameOperation($receipt, $probeId);

            if ($receipt->status === 'delivered') {
                return;
            }

            $this->database
                ->table('platform_completion_receipts')
                ->where('correlation_id', $correlationId)
                ->update([
                    'status' => 'delivered',
                    'delivered_at' => CarbonImmutable::now(),
                ]);
        });

        return new CompletionResult($completionCode);
    }

    public function name(): string
    {
        return 'structured-log';
    }

    private function reserve(
        string $probeId,
        string $correlationId,
        string $completionCode,
    ): stdClass {
        return $this->database->transaction(function () use (
            $probeId,
            $correlationId,
            $completionCode,
        ): stdClass {
            $this->database->table('platform_completion_receipts')->insertOrIgnore([
                'correlation_id' => $correlationId,
                'probe_id' => $probeId,
                'adapter' => $this->name(),
                'completion_code' => $completionCode,
                'status' => 'pending',
                'attempts' => 0,
                'reserved_at' => CarbonImmutable::now(),
                'delivered_at' => null,
            ]);

            $receipt = $this->database
                ->table('platform_completion_receipts')
                ->where('correlation_id', $correlationId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertSameOperation($receipt, $probeId);

            return $receipt;
        });
    }

    private function assertSameOperation(stdClass $receipt, string $probeId): void
    {
        if ($receipt->probe_id !== $probeId || $receipt->adapter !== $this->name()) {
            throw new LogicException('Completion correlation ID was reused for a different operation.');
        }
    }
}
