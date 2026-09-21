<?php

namespace App\Enums;

/**
 * What happened when a PushNotificationGateway tried to deliver one
 * message to one device (consolidation brief §50-§51) — `NotConfigured`
 * is the expected, non-error outcome while no real provider (Expo/FCM/
 * APNs) is wired up: the database notification always still saves, push
 * is purely additive.
 */
enum PushSendStatus: string
{
    case Sent = 'sent';
    case NotConfigured = 'not_configured';
    case Failed = 'failed';
}
