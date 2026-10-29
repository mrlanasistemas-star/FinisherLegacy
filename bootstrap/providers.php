<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\LegacyTagServiceProvider;
use App\Providers\NfcServiceProvider;
use App\Providers\PaymentSettingsServiceProvider;
use App\Providers\TrackingServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    TrackingServiceProvider::class,
    HorizonServiceProvider::class,
    NfcServiceProvider::class,
    LegacyTagServiceProvider::class,
    PaymentSettingsServiceProvider::class,
];
