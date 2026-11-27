<?php

use App\Providers\ApiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\JiraServiceProvider;

return [
    ApiServiceProvider::class,
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    JiraServiceProvider::class,
];
