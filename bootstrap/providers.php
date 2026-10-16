<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TrackingServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\NfcServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    TrackingServiceProvider::class,
    HorizonServiceProvider::class,
    NfcServiceProvider::class,
];
