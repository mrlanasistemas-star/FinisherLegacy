<?php

namespace App\Enums;

/**
 * Every kind of notification a customer can receive (in-app center + push).
 * `adminSendable()` is the subset an admin may pick in the "send
 * notification" forms; `Custom` covers anything typed by hand. `category()`
 * maps each type to the preference category / Android channel it belongs to
 * (docs/renovacion/notificaciones.md §Tipos).
 */
enum NotificationType: string
{
    case PaymentPending = 'payment_pending';
    case ResultAvailable = 'result_available';
    case LegacyPlateReady = 'legacy_plate_ready';
    case OrderReady = 'order_ready';
    case EventUpdated = 'event_updated';
    case Custom = 'custom';

    // Legacy Moments social layer — only ever sent by the system, never
    // selectable in the admin "send notification" forms.
    case NewFollower = 'new_follower';
    case MomentReaction = 'moment_reaction';
    case MomentComment = 'moment_comment';

    // Entrenamientos: someone left a cheer through an "Envíame ánimo" link.
    case TrainingCheer = 'training_cheer';

    // Commerce lifecycle (App\Listeners\Notifications\NotifyCustomerOfOrder).
    case OrderPaid = 'order_paid';
    case OrderShipped = 'order_shipped';
    case OrderDelivered = 'order_delivered';
    case OrderRefunded = 'order_refunded';

    // Finisher Legacy Tag activated by its owner.
    case TagActivated = 'tag_activated';

    // Our team answered a support request.
    case SupportReply = 'support_reply';

    // Admin push campaign / broadcast saved to the inbox.
    case Broadcast = 'broadcast';

    /**
     * @return list<self>
     */
    public static function adminSendable(): array
    {
        return [
            self::PaymentPending,
            self::ResultAvailable,
            self::LegacyPlateReady,
            self::OrderReady,
            self::EventUpdated,
            self::Custom,
        ];
    }

    public function category(): NotificationCategory
    {
        return match ($this) {
            self::PaymentPending, self::OrderReady, self::OrderPaid, self::OrderShipped,
            self::OrderDelivered, self::OrderRefunded, self::TagActivated, self::LegacyPlateReady => NotificationCategory::Pedidos,
            self::NewFollower, self::MomentReaction, self::MomentComment => NotificationCategory::Comunidad,
            self::TrainingCheer => NotificationCategory::Entrenamientos,
            self::SupportReply => NotificationCategory::Soporte,
            self::ResultAvailable, self::EventUpdated, self::Custom, self::Broadcast => NotificationCategory::Avisos,
        };
    }
}
