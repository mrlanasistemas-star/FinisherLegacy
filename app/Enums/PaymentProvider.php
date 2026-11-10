<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case OpenPay = 'openpay';
    case Stripe = 'stripe';
    case Manual = 'manual';
    // In-app purchases: the money is collected by the store, verified server-side.
    case AppStore = 'app_store';
    case GooglePlay = 'google_play';
}
