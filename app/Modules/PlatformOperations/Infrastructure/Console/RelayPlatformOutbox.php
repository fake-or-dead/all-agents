<?php

namespace App\Modules\PlatformOperations\Infrastructure\Console;

use App\Modules\PlatformOperations\Infrastructure\Queue\ProcessPlatformProbe;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;

final class RelayPlatformOutbox extends Command
{
    protected $signature = 'platform:relay-outbox {--limit=100}';

    protected $description = 'Dispatch unprocessed platform outbox events.';

    public function handle(ConnectionInterface $database): int
    {
        $events = $database
            ->table('outbox_events')
            ->whereNull('processed_at')
            ->where('available_at', '<=', CarbonImmutable::now())
            ->where(function ($query): void {
                $query
                    ->whereNull('dispatched_at')
                    ->orWhere('dispatched_at', '<=', CarbonImmutable::now()->subMinutes(5));
            })
            ->orderBy('created_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get(['id']);

        foreach ($events as $event) {
            $now = CarbonImmutable::now();
            $database->table('outbox_events')->where('id', $event->id)->update([
                'dispatched_at' => $now,
                'updated_at' => $now,
            ]);

            ProcessPlatformProbe::dispatch($event->id);
        }

        $this->components->info("Dispatched {$events->count()} platform event(s).");

        return self::SUCCESS;
    }
}
