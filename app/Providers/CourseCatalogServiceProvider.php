<?php

namespace App\Providers;

use App\Modules\CourseCatalog\Contracts\ApplicationFacts;
use App\Modules\CourseCatalog\Contracts\CourseCatalog;
use App\Modules\CourseCatalog\Infrastructure\Persistence\DatabaseApplicationFacts;
use App\Modules\CourseCatalog\Infrastructure\Persistence\DatabaseCourseCatalog;
use App\Modules\ReferenceData\Contracts\ReferenceData;
use App\Modules\ReferenceData\Infrastructure\Persistence\DatabaseReferenceData;
use Illuminate\Support\ServiceProvider;

final class CourseCatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApplicationFacts::class, DatabaseApplicationFacts::class);
        $this->app->bind(CourseCatalog::class, DatabaseCourseCatalog::class);
        $this->app->bind(ReferenceData::class, DatabaseReferenceData::class);
    }
}
