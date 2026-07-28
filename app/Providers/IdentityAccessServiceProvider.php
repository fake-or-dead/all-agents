<?php

namespace App\Providers;

use App\Integrations\IdentityAccess\AuditSecurityEventRecorder;
use App\Modules\IdentityAccess\Contracts\LocalVerificationMailbox;
use App\Modules\IdentityAccess\Contracts\SecurityEventRecorder;
use App\Modules\IdentityAccess\Contracts\VerificationGateway;
use App\Modules\IdentityAccess\Infrastructure\Verification\DeterministicFakeVerificationGateway;
use App\Modules\People\Contracts\PersonIdentityDirectory;
use App\Modules\People\Infrastructure\DatabasePersonIdentityDirectory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

final class IdentityAccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/identity-access.php'));
    }

    public function register(): void
    {
        $this->app->bind(SecurityEventRecorder::class, AuditSecurityEventRecorder::class);
        $this->app->bind(PersonIdentityDirectory::class, DatabasePersonIdentityDirectory::class);
        $this->app->bind(
            LocalVerificationMailbox::class,
            DeterministicFakeVerificationGateway::class,
        );
        $this->app->bind(VerificationGateway::class, function (Application $app): VerificationGateway {
            return match ($app['config']->string('identity-access.verification_adapter')) {
                'deterministic-fake' => $app->make(DeterministicFakeVerificationGateway::class),
                default => throw new InvalidArgumentException(
                    'Unsupported identity verification adapter.',
                ),
            };
        });
    }
}
