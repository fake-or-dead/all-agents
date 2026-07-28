<?php

namespace App\Modules\PlatformOperations\Data;

final readonly class ReadinessReport
{
    public function __construct(
        public bool $database,
        public bool $redis,
        public bool $queue,
        public bool $scheduler,
        public int $pendingMigrations,
    ) {}

    public function ready(): bool
    {
        return $this->database
            && $this->redis
            && $this->queue
            && $this->scheduler
            && $this->pendingMigrations === 0;
    }
}
