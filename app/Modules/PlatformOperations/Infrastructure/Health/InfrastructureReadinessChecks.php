<?php

namespace App\Modules\PlatformOperations\Infrastructure\Health;

use App\Modules\PlatformOperations\Contracts\ReadinessChecks;
use App\Modules\PlatformOperations\Data\ReadinessReport;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Redis\RedisManager;
use Throwable;

final readonly class InfrastructureReadinessChecks implements ReadinessChecks
{
    public function __construct(
        private ConnectionInterface $database,
        private RedisManager $redis,
        private Migrator $migrator,
    ) {}

    public function run(): ReadinessReport
    {
        $databaseReady = $this->databaseReady();
        $redisReady = $this->redisReady();

        return new ReadinessReport(
            database: $databaseReady,
            redis: $redisReady,
            queue: $redisReady && $this->heartbeatFresh('worker'),
            scheduler: $databaseReady && $this->heartbeatFresh('scheduler'),
            pendingMigrations: $databaseReady ? $this->pendingMigrations() : 1,
        );
    }

    private function databaseReady(): bool
    {
        try {
            $this->database->select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function redisReady(): bool
    {
        try {
            return in_array($this->redis->connection()->ping(), [true, 'PONG', '+PONG'], true);
        } catch (Throwable) {
            return false;
        }
    }

    private function heartbeatFresh(string $component): bool
    {
        try {
            $seenAt = $this->database
                ->table('runtime_heartbeats')
                ->where('component', $component)
                ->value('seen_at');

            return is_string($seenAt)
                && CarbonImmutable::parse($seenAt)->isAfter(
                    CarbonImmutable::now()->subSeconds(
                        config()->integer('platform.health.heartbeat_max_age_seconds'),
                    ),
                );
        } catch (Throwable) {
            return false;
        }
    }

    private function pendingMigrations(): int
    {
        try {
            $files = $this->migrator->getMigrationFiles(database_path('migrations'));
            $ran = $this->migrator->getRepository()->getRan();

            return count(array_diff(array_keys($files), $ran));
        } catch (Throwable) {
            return 1;
        }
    }
}
