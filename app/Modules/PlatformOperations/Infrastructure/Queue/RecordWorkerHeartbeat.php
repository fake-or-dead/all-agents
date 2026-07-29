<?php

namespace App\Modules\PlatformOperations\Infrastructure\Queue;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RecordWorkerHeartbeat implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(ConnectionInterface $database): void
    {
        $database->table('runtime_heartbeats')->updateOrInsert(
            ['component' => 'worker'],
            ['seen_at' => CarbonImmutable::now()],
        );
    }
}
