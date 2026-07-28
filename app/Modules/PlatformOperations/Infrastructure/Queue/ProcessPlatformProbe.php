<?php

namespace App\Modules\PlatformOperations\Infrastructure\Queue;

use App\Modules\PlatformOperations\Contracts\PlatformProbeWorker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ProcessPlatformProbe implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly string $outboxEventId) {}

    public function handle(PlatformProbeWorker $worker): void
    {
        $worker->completeOutboxEvent($this->outboxEventId);
    }
}
