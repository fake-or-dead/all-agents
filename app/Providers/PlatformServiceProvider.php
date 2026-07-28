<?php

namespace App\Providers;

use App\Modules\Audit\Contracts\AuditLog;
use App\Modules\Audit\Infrastructure\DatabaseAuditLog;
use App\Modules\IdentityAccess\Contracts\ActorResolver;
use App\Modules\IdentityAccess\Infrastructure\LaravelAuthActorResolver;
use App\Modules\IdentityAccess\Infrastructure\TestHeaderActorResolver;
use App\Modules\PlatformOperations\Contracts\CompletionAdapter;
use App\Modules\PlatformOperations\Contracts\PlatformProbes;
use App\Modules\PlatformOperations\Contracts\PlatformProbeWorker;
use App\Modules\PlatformOperations\Contracts\ReadinessChecks;
use App\Modules\PlatformOperations\Infrastructure\Completion\DeterministicFakeCompletionAdapter;
use App\Modules\PlatformOperations\Infrastructure\Completion\StructuredLogCompletionAdapter;
use App\Modules\PlatformOperations\Infrastructure\Console\RecordSchedulerHeartbeat;
use App\Modules\PlatformOperations\Infrastructure\Console\RelayPlatformOutbox;
use App\Modules\PlatformOperations\Infrastructure\Console\VerifyRestoredAuditedPath;
use App\Modules\PlatformOperations\Infrastructure\Health\InfrastructureReadinessChecks;
use App\Modules\PlatformOperations\Infrastructure\Persistence\DatabasePlatformProbes;
use App\Modules\PlatformOperations\Infrastructure\Queue\DatabasePlatformProbeWorker;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class PlatformServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RecordSchedulerHeartbeat::class,
                RelayPlatformOutbox::class,
                VerifyRestoredAuditedPath::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->bind(AuditLog::class, DatabaseAuditLog::class);
        $this->app->bind(PlatformProbes::class, DatabasePlatformProbes::class);
        $this->app->bind(PlatformProbeWorker::class, DatabasePlatformProbeWorker::class);
        $this->app->bind(ReadinessChecks::class, InfrastructureReadinessChecks::class);

        $this->app->bind(ActorResolver::class, function (Application $app): ActorResolver {
            return match ($app['config']->string('platform.actor_adapter')) {
                'laravel-auth' => $app->make(LaravelAuthActorResolver::class),
                'test-header' => $app->make(TestHeaderActorResolver::class),
                default => throw new InvalidArgumentException('Unsupported platform actor adapter.'),
            };
        });

        $this->app->bind(CompletionAdapter::class, function (Application $app): CompletionAdapter {
            return match ($app['config']->string('platform.completion_adapter')) {
                'structured-log' => $app->make(StructuredLogCompletionAdapter::class),
                'deterministic-fake' => $app->make(DeterministicFakeCompletionAdapter::class),
                default => throw new InvalidArgumentException('Unsupported platform completion adapter.'),
            };
        });
    }
}
