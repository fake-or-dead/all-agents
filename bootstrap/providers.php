<?php

use App\Providers\AppServiceProvider;
use App\Providers\CourseCatalogServiceProvider;
use App\Providers\IdentityAccessServiceProvider;
use App\Providers\PeopleServiceProvider;
use App\Providers\PlatformServiceProvider;

return [
    AppServiceProvider::class,
    CourseCatalogServiceProvider::class,
    IdentityAccessServiceProvider::class,
    PeopleServiceProvider::class,
    PlatformServiceProvider::class,
];
