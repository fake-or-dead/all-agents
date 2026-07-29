<?php

namespace App\Providers;

use App\Integrations\People\AuditProfileActivityRecorder;
use App\Modules\ApplicationWorkflow\Contracts\MemberApplicationHistory;
use App\Modules\ApplicationWorkflow\Infrastructure\Persistence\DatabaseMemberApplicationHistory;
use App\Modules\People\Contracts\MemberProfileMutations;
use App\Modules\People\Contracts\MemberProfiles;
use App\Modules\People\Contracts\ProfileActivityRecorder;
use App\Modules\People\Infrastructure\DatabaseMemberProfiles;
use App\Modules\People\Infrastructure\TransactionalMemberProfileMutations;
use Illuminate\Support\ServiceProvider;

final class PeopleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/member.php'));
    }

    public function register(): void
    {
        $this->app->bind(MemberProfiles::class, DatabaseMemberProfiles::class);
        $this->app->bind(
            MemberProfileMutations::class,
            TransactionalMemberProfileMutations::class,
        );
        $this->app->bind(ProfileActivityRecorder::class, AuditProfileActivityRecorder::class);
        $this->app->bind(
            MemberApplicationHistory::class,
            DatabaseMemberApplicationHistory::class,
        );
    }
}
