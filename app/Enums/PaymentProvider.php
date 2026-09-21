<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case OpenPay = 'openpay';
    case Stripe = 'stripe';
    case Manual = 'manual';
}
