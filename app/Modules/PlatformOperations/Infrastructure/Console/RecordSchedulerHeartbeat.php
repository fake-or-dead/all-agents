<?php

namespace App\Modules\PlatformOperations\Infrastructure\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;

final class RecordSchedulerHeartbeat extends Command
{
    protected $signature = 'platform:scheduler-heartbeat';

    protected $description = 'Record scheduler liveness without personal data.';

    public function handle(ConnectionInterface $database): int
    {
        $database->table('runtime_heartbeats')->updateOrInsert(
            ['component' => 'scheduler'],
            ['seen_at' => CarbonImmutable::now()],
        );

        return self::SUCCESS;
    }
}
