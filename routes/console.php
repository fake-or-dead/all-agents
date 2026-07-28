<?php

use App\Modules\PlatformOperations\Infrastructure\Queue\RecordWorkerHeartbeat;
use Illuminate\Support\Facades\Schedule;

Schedule::command('platform:relay-outbox')->everySecond()->withoutOverlapping();
Schedule::command('platform:scheduler-heartbeat')->everyTenSeconds()->withoutOverlapping();
Schedule::job(new RecordWorkerHeartbeat)->everyTenSeconds()->withoutOverlapping();
