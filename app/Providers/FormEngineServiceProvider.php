<?php

namespace App\Providers;

use App\Modules\FormEngine\Contracts\PublishedFormSchemas;
use App\Modules\FormEngine\Infrastructure\Persistence\DatabasePublishedFormSchemas;
use Illuminate\Support\ServiceProvider;

final class FormEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PublishedFormSchemas::class, DatabasePublishedFormSchemas::class);
    }
}
