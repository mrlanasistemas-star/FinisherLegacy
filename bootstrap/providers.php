<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\I18nServiceProvider;
use App\Providers\LegacyTagServiceProvider;
use App\Providers\NfcServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\PaymentSettingsServiceProvider;
use App\Providers\TrackingServiceProvider;
use App\Providers\TrainingServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    TrackingServiceProvider::class,
    HorizonServiceProvider::class,
    NfcServiceProvider::class,
    LegacyTagServiceProvider::class,
    PaymentSettingsServiceProvider::class,
    TrainingServiceProvider::class,
    NotificationServiceProvider::class,
    I18nServiceProvider::class,
];
