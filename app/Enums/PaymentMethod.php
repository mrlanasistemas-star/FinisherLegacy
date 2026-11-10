<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case OnlineCard = 'online_card';
    case MercadoPagoTerminal = 'mercado_pago_terminal';
    case Cash = 'cash';
    // StoreKit / Play Billing — never offered as a manual/POS method.
    case InAppPurchase = 'in_app_purchase';
}
