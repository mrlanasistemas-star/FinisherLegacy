<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only system configuration overview (brief §5/§45) — surfaces
 * config/finisher.php values that operators actually need to see day to
 * day; changing them still happens via .env/config, never a settings
 * table (brief §143 explicitly avoids scattered constants, not a new
 * database-backed settings system).
 */
class SettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/settings/Index', [
            'commerce' => [
                'default_currency' => config('finisher.commerce.default_currency'),
                'default_inventory_location_slug' => config('finisher.commerce.default_inventory_location_slug'),
                'order_payment_expiry_minutes' => config('finisher.commerce.order_payment_expiry_minutes'),
                'coupon_reservation_minutes' => config('finisher.commerce.coupon_reservation_minutes'),
            ],
            'media' => [
                'free_images_per_participation' => config('finisher.event_media.free_images_per_participation'),
                'free_videos_per_participation' => config('finisher.event_media.free_videos_per_participation'),
                'max_image_bytes' => config('finisher.event_media.max_image_bytes'),
                'max_video_bytes' => config('finisher.event_media.max_video_bytes'),
            ],
            'payments' => [
                'stripe_configured' => filled(config('finisher.payments.stripe.secret')) && filled(config('finisher.payments.stripe.webhook_secret')),
            ],
            'apiVersion' => config('finisher.api_version'),
        ]);
    }
}
