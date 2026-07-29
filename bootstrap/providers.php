<?php

use App\Providers\AppServiceProvider;
use App\Providers\IdentityAccessServiceProvider;
use App\Providers\PlatformServiceProvider;

return [
    AppServiceProvider::class,
    IdentityAccessServiceProvider::class,
    PlatformServiceProvider::class,
];
