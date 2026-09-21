<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage — piloto
    |--------------------------------------------------------------------------
    |
    | Finisher Legacy está en fase piloto: el almacenamiento NO es ilimitado.
    | Todos los límites de archivos/cuotas viven aquí — nunca hardcodeados en
    | controllers o Form Requests — para poder subirlos sin migración cuando
    | el piloto crezca.
    */

    'image' => [
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'max_original_kb' => 8192,
        'thumbnail_size' => 400,
        'display_max_width' => 1800,

        // El piloto no necesita conservar el original gigante una vez que
        // existe una versión optimizada de alta calidad. Cambiar a false
        // para dejar de guardarlos y ahorrar almacenamiento; ya guardados
        // no se borran retroactivamente por este cambio.
        'keep_original' => true,
    ],

    'profile' => [
        'avatar' => [
            'max_kb' => 3072,
        ],
        'cover' => [
            'max_kb' => 5120,
        ],
    ],

    'medal' => [
        'front' => [
            'max_kb' => 8192,
        ],
        'back' => [
            'max_kb' => 8192,
        ],
        'gallery' => [
            'max_kb' => 8192,
            'max_files' => 3,
        ],
        // front + back + gallery, del lado del servidor (independiente de
        // cuántos campos exponga el formulario).
        'max_images_per_medal' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Video — piloto
    |--------------------------------------------------------------------------
    |
    | DESHABILITADO A PROPÓSITO. La infraestructura actual no tiene
    | procesamiento seguro de video (no hay ffmpeg/cola de transcoding
    | instalada), y el proyecto no debe arriesgarse a subir video sin
    | validarlo/transcodificarlo correctamente. El esquema queda preparado
    | para cuando se aborde con la herramienta adecuada; no se habilita el
    | upload mientras `enabled` sea false.
    */
    'video' => [
        'enabled' => false,
        'max_seconds' => 30,
        'max_mb' => 25,
        'mimes' => ['mp4', 'mov'],
        'max_per_medal' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cuotas globales por atleta — piloto
    |--------------------------------------------------------------------------
    */
    'quotas' => [
        'max_medals_per_athlete' => 30,
        'max_images_per_athlete' => 150,
        'max_videos_per_athlete' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Device / Production API (ADR 0002, Slice 1)
    |--------------------------------------------------------------------------
    |
    | Tunables for pairing, heartbeat and job-claim leases — centralized here
    | instead of hardcoded in the Actions/Services that use them, so a real
    | deployment can tune timings without touching code.
    */

    // A device counts as "online" if its last heartbeat is within this many
    // seconds. Deliberately NOT a persisted "offline" status — a device
    // that stops sending heartbeats doesn't need a background job to flip
    // a flag; ProductionDevice::isOnline() derives it from `last_seen_at`
    // on read. See App\Enums\ProductionDeviceStatus.
    'device_online_timeout_seconds' => env('FINISHER_DEVICE_ONLINE_TIMEOUT_SECONDS', 90),

    // Event Ops shows a "datos sin actualizar" warning once the last
    // successful provider sync is older than this — see
    // App\Queries\Operations\GetEventOperationsDashboard and
    // docs/adr/0006-event-operations.md §11.
    'event_ops_sync_stale_seconds' => env('FINISHER_EVENT_OPS_SYNC_STALE_SECONDS', 300),

    // How long a claimed ProductionJob's lease lasts before it becomes
    // reclaimable by another device — ONLY while the job is Assigned or
    // Preparing (ProductionJob::isSafeToRelease()). Once physical
    // engraving starts (engraving_front onward) the lease stops mattering
    // entirely: there is real, irreversible physical state on the plate,
    // so a job is never auto-reclaimed out from under a device past that
    // point — see docs/adr/0003-production-state-machine.md §Lease.
    'device_lease_seconds' => env('FINISHER_DEVICE_LEASE_SECONDS', 900),

    // How long a pairing code/poll token stays valid before a desktop must
    // request a new one. Short on purpose — pairing is a one-time,
    // attended (Super Admin present) handshake, not a long-lived credential.
    'pairing_expiration_minutes' => env('FINISHER_PAIRING_EXPIRATION_MINUTES', 10),

    // Length of the human-readable pairing code shown on the desktop
    // screen and in the admin "Estaciones" list. Not a secret — the real
    // secret is the poll token the desktop keeps privately (docs/adr/0002).
    'pairing_code_length' => env('FINISHER_PAIRING_CODE_LENGTH', 6),

    // How long a stored device idempotency response is honored before a
    // repeated Idempotency-Key is treated as a new request.
    'idempotency_ttl_seconds' => env('FINISHER_IDEMPOTENCY_TTL_SECONDS', 86400),

    // Private disk (never a public URL) for frozen ProductionArtifact
    // files — served only through the authenticated, ownership-checked
    // Device API artifact endpoint. See
    // App\Services\Production\ProductionArtifactService.
    'production_artifact_disk' => env('FINISHER_PRODUCTION_ARTIFACT_DISK', 'local'),

    // The /api/v1 contract version a client (future Desktop/Mobile) can
    // check against — bumped only on a breaking change, not every deploy.
    // `minimum_supported_client_version` is nullable and unenforced today
    // (no client exists yet to reject) — it's the contract point Slice 6
    // prepares for a future version-gate, never a value this backend
    // guesses at (docs/api/v1.md §Versión).
    'api_version' => '1.0',
    'minimum_supported_client_version' => env('FINISHER_MINIMUM_SUPPORTED_CLIENT_VERSION'),

    /*
    |--------------------------------------------------------------------------
    | Commerce ecosystem (brief §45-§217)
    |--------------------------------------------------------------------------
    */
    'commerce' => [
        // MXN initially, but every money row still carries its own
        // `currency` column — never hardcoded elsewhere (brief §152).
        'default_currency' => env('FINISHER_DEFAULT_CURRENCY', 'MXN'),

        // Slug of the InventoryLocation Checkout reserves/commits stock
        // against — a real deployment with multiple warehouses can extend
        // CheckoutCart to pick one per order later; single-location for
        // this phase (documented debt, brief §51).
        'default_inventory_location_slug' => env('FINISHER_DEFAULT_INVENTORY_LOCATION', 'main-warehouse'),

        // How long an inventory reservation from CheckoutCart survives
        // before App\Console\Commands\ReleaseExpiredInventoryReservations
        // releases it back to available stock (brief §158/§194).
        'reservation_ttl_minutes' => env('FINISHER_RESERVATION_TTL_MINUTES', 30),

        // A pending, unpaid Order older than this is eligible for
        // automatic expiry — never applied to a paid Order (brief §196).
        'order_payment_expiry_minutes' => env('FINISHER_ORDER_PAYMENT_EXPIRY_MINUTES', 60),

        // How long a CouponRedemption stays "reserved" (counts against
        // the coupon's usage limit) before it's ignored by
        // App\Actions\Commerce\ValidateCoupon's usage count — an
        // abandoned, never-paid checkout stops holding a coupon hostage
        // without needing a cleanup scheduler (consolidation brief §37).
        'coupon_reservation_minutes' => env('FINISHER_COUPON_RESERVATION_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments (brief §64-§78)
    |--------------------------------------------------------------------------
    |
    | Both `stripe/stripe-php` and `openpay/sdk` are real installed
    | dependencies — every gateway below uses the official SDK, never a
    | hand-rolled HTTP call. Real credentials are still a per-environment
    | secret (brief §71: "no inventar credenciales"), so both stay
    | NotConfigured stubs in dev/test/CI until real keys are set via env.
    | `default_gateway` is which one CreateOnlinePayment uses when the
    | caller doesn't pick one — OpenPay is the primary gateway (brief §49),
    | Stripe stays available and fully wired for accounts that need it.
    */
    'payments' => [
        'default_gateway' => env('FINISHER_PAYMENT_GATEWAY', 'openpay'),

        'stripe' => [
            'key' => env('STRIPE_KEY'),
            'secret' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],

        // Country codes match what Openpay\Data\Openpay::getInstance()
        // accepts: MX, CO, or PE — MX matches this app's MXN default
        // (`finisher.commerce.default_currency`).
        'openpay' => [
            'merchant_id' => env('OPENPAY_MERCHANT_ID'),
            'private_key' => env('OPENPAY_PRIVATE_KEY'),
            'public_key' => env('OPENPAY_PUBLIC_KEY'),
            'country' => env('OPENPAY_COUNTRY', 'MX'),
            'production_mode' => (bool) env('OPENPAY_PRODUCTION_MODE', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Event media (brief §41-§46/§94-§101)
    |--------------------------------------------------------------------------
    */
    'event_media' => [
        'free_images_per_participation' => env('FINISHER_MEDIA_FREE_IMAGES', 5),
        'free_videos_per_participation' => env('FINISHER_MEDIA_FREE_VIDEOS', 1),
        'max_image_bytes' => env('FINISHER_MEDIA_MAX_IMAGE_BYTES', 8 * 1024 * 1024),
        'max_video_bytes' => env('FINISHER_MEDIA_MAX_VIDEO_BYTES', 100 * 1024 * 1024),
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'video_mimes' => ['mp4', 'webm'],
        'disk' => env('FINISHER_MEDIA_DISK', 'athlete_media'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Athlete Support ("Mi equipo de apoyo") — brief §29-§30
    |--------------------------------------------------------------------------
    */
    'support' => [
        'audio_max_seconds' => env('FINISHER_SUPPORT_AUDIO_MAX_SECONDS', 60),
        'audio_max_bytes' => env('FINISHER_SUPPORT_AUDIO_MAX_BYTES', 10 * 1024 * 1024),
        'audio_mimes' => ['webm', 'mp4', 'm4a', 'mpga', 'mp3', 'ogg', 'wav'],
        // Private — audio is never served from a public disk URL, only
        // through a signed, time-limited route (brief §41).
        'audio_disk' => env('FINISHER_SUPPORT_AUDIO_DISK', 'support_audio'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Product media (brief §57/§139)
    |--------------------------------------------------------------------------
    */
    'product_media' => [
        'disk' => env('FINISHER_PRODUCT_MEDIA_DISK', 'product_media'),
    ],
];
