<?php

namespace App\Providers;

use App\Modules\ApplicationWorkflow\Contracts\ApplicationFacts;
use App\Modules\ApplicationWorkflow\Infrastructure\Persistence\DatabaseApplicationFacts;
use App\Modules\CourseCatalog\Contracts\CourseCatalog;
use App\Modules\CourseCatalog\Infrastructure\Persistence\DatabaseCourseCatalog;
use App\Modules\DocumentsConsent\Contracts\PublicCourseDocuments;
use App\Modules\DocumentsConsent\Infrastructure\Persistence\DatabasePublicCourseDocuments;
use App\Modules\IdentityAccess\Contracts\ApplicantIdentityResolver;
use App\Modules\IdentityAccess\Infrastructure\LaravelApplicantIdentityResolver;
use App\Modules\ReferenceData\Contracts\ReferenceData;
use App\Modules\ReferenceData\Infrastructure\Persistence\DatabaseReferenceData;
use Illuminate\Support\ServiceProvider;

final class CourseCatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApplicationFacts::class, DatabaseApplicationFacts::class);
        $this->app->bind(PublicCourseDocuments::class, DatabasePublicCourseDocuments::class);
        $this->app->bind(ApplicantIdentityResolver::class, LaravelApplicantIdentityResolver::class);
        $this->app->bind(CourseCatalog::class, DatabaseCourseCatalog::class);
        $this->app->bind(ReferenceData::class, DatabaseReferenceData::class);
    }
}
