<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\V1\Admin\OrganizerDataSourceController;
use App\Http\Controllers\Api\V1\Admin\ProviderConnectionController as AdminProviderConnectionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\GearController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Integrations\SyncController as ApiIntegrationsSyncController;
use App\Http\Controllers\Api\V1\LegacyCodeController;
use App\Http\Controllers\Api\V1\Me\EventGearController as MeEventGearController;
use App\Http\Controllers\Api\V1\Me\EventMediaController as MeEventMediaController;
use App\Http\Controllers\Api\V1\Me\EventsController as MeEventsController;
use App\Http\Controllers\Api\V1\Me\NotificationController as MeNotificationController;
use App\Http\Controllers\Api\V1\Me\NotificationPreferenceController as MeNotificationPreferenceController;
use App\Http\Controllers\Api\V1\Me\PushDeviceController as MePushDeviceController;
use App\Http\Controllers\Api\V1\Me\SupportSessionController as MeSupportSessionController;
use App\Http\Controllers\Api\V1\MedalController;
use App\Http\Controllers\Api\V1\PreregistrationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PublicAthleteController;
use App\Http\Controllers\Api\V1\Social\AthleteSocialController;
use App\Http\Controllers\Api\V1\Social\CommentController;
use App\Http\Controllers\Api\V1\Social\FeedController;
use App\Http\Controllers\Api\V1\Social\MomentController;
use App\Http\Controllers\Api\V1\Social\ReportController;
use App\Http\Controllers\Api\V1\Social\SearchController;
use App\Http\Controllers\Api\V1\Store\CartController;
use App\Http\Controllers\Api\V1\Store\CheckoutController;
use App\Http\Controllers\Api\V1\Store\CollectionController as StoreCollectionController;
use App\Http\Controllers\Api\V1\Store\OrderController;
use App\Http\Controllers\Api\V1\Store\PaymentController;
use App\Http\Controllers\Api\V1\Store\ProductController;
use App\Http\Controllers\Api\V1\Training\TrainingLinkController;
use App\Http\Controllers\Api\V1\Training\TrainingMessageController;
use App\Http\Controllers\Api\Webhooks\OpenPayWebhookController;
use App\Http\Controllers\Api\Webhooks\StripeWebhookController;
use App\Http\Middleware\AlertOnFailedPaymentWebhook;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 — the contract iOS/Android/other Finisher Legacy sites build on.
|--------------------------------------------------------------------------
|
| Every controller here reuses the same Services/Actions/Policies/Form
| Requests as the web Inertia controllers. Never put business logic
| directly in a controller in this file.
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', [HealthController::class, 'show'])->name('health');

    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:api-register')
        ->name('auth.register');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    // Same password broker, email and web reset page as Fortify's own
    // forgot-password flow — just reachable without a web session.
    Route::post('auth/forgot-password', [AccountController::class, 'forgotPassword'])
        ->middleware('throttle:password-reset')
        ->name('auth.forgot-password');
    Route::post('auth/reset-password', [AccountController::class, 'resetPassword'])
        ->middleware('throttle:password-reset')
        ->name('auth.reset-password');

    // Native Google / Apple ID token → Sanctum token (docs/ARCHITECTURE.md
    // §Social sign-in). Disabled per provider until its client ids are set.
    Route::post('auth/social/{provider}', [AccountController::class, 'socialSignIn'])
        ->middleware('throttle:social-auth')
        ->whereIn('provider', ['google', 'apple'])
        ->name('auth.social');

    Route::middleware(['auth:sanctum', 'user.token'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::delete('me/account', [AccountController::class, 'destroy'])
            ->middleware('throttle:password-reset')
            ->name('me.account.destroy');

        /*
        |------------------------------------------------------------------
        | Legacy Moments — social layer (docs/SOCIAL_ARCHITECTURE.md). No
        | DMs/chat: every interaction hangs off a sporting Moment. Privacy
        | is enforced server-side by App\Services\Social\SocialVisibility.
        |------------------------------------------------------------------
        */
        Route::get('feed', [FeedController::class, 'feed'])->name('feed');
        Route::get('explore', [FeedController::class, 'explore'])->name('explore');
        Route::get('search', SearchController::class)->middleware('throttle:search')->name('search');

        Route::post('moments', [MomentController::class, 'store'])
            ->middleware(['throttle:social-write', 'api.idempotent'])
            ->name('moments.store');
        Route::get('moments/{moment:uuid}', [MomentController::class, 'show'])->name('moments.show');
        Route::patch('moments/{moment:uuid}', [MomentController::class, 'update'])->middleware('throttle:social-write')->name('moments.update');
        Route::delete('moments/{moment:uuid}', [MomentController::class, 'destroy'])->name('moments.destroy');
        Route::put('moments/{moment:uuid}/reactions/{type}', [MomentController::class, 'react'])
            ->middleware('throttle:social-write')
            ->name('moments.reactions.store');
        Route::delete('moments/{moment:uuid}/reactions/{type}', [MomentController::class, 'unreact'])
            ->middleware('throttle:social-write')
            ->name('moments.reactions.destroy');
        Route::get('moments/{moment:uuid}/comments', [CommentController::class, 'index'])->name('moments.comments.index');
        Route::post('moments/{moment:uuid}/comments', [CommentController::class, 'store'])
            ->middleware(['throttle:social-comment', 'api.idempotent'])
            ->name('moments.comments.store');
        Route::delete('comments/{comment:uuid}', [CommentController::class, 'destroy'])->name('comments.destroy');

        Route::prefix('athletes/{athleteProfile:username}')->name('athletes.')->group(function () {
            Route::post('follow', [AthleteSocialController::class, 'follow'])->middleware('throttle:social-write')->name('follow');
            Route::delete('follow', [AthleteSocialController::class, 'unfollow'])->middleware('throttle:social-write')->name('unfollow');
            Route::get('followers', [AthleteSocialController::class, 'followers'])->name('followers');
            Route::get('following', [AthleteSocialController::class, 'following'])->name('following');
            Route::get('moments', [AthleteSocialController::class, 'moments'])->name('moments');
            Route::post('block', [AthleteSocialController::class, 'block'])->middleware('throttle:social-write')->name('block');
            Route::delete('block', [AthleteSocialController::class, 'unblock'])->middleware('throttle:social-write')->name('unblock');
        });
        Route::get('me/blocks', [AthleteSocialController::class, 'blocks'])->name('me.blocks');
        Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:reports')->name('reports.store');

        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

        // Same controller/actions as `profile` above — `me/profile` is the
        // name the mobile-facing minimal API surface uses (brief §26/§75).
        Route::get('me/profile', [ProfileController::class, 'show'])->name('me.profile.show');
        Route::patch('me/profile', [ProfileController::class, 'update'])->name('me.profile.update');

        Route::get('medals', [MedalController::class, 'index'])->name('medals.index');
        Route::post('medals', [MedalController::class, 'store'])->name('medals.store');
        Route::get('medals/{medal:uuid}', [MedalController::class, 'show'])->name('medals.show');
        Route::patch('medals/{medal:uuid}', [MedalController::class, 'update'])->name('medals.update');
        Route::delete('medals/{medal:uuid}', [MedalController::class, 'destroy'])->name('medals.destroy');

        Route::post('legacy-codes/{code}/claim', [LegacyCodeController::class, 'claim'])
            ->middleware('throttle:api-claim')
            ->name('legacy-codes.claim');

        // Manual event creation (brief §19-§21/§121/§182) — staff-only,
        // gated by `events.manage` inside CreateEventRequest::authorize().
        Route::post('events', [EventController::class, 'store'])
            ->middleware('api.idempotent')
            ->name('events.store');

        /*
        |------------------------------------------------------------------
        | Admin — Organizer data source, provider test, user notifications
        | (brief §16-§17/§122/§136-§138). Permission-gated inside each Form
        | Request, same pattern as the rest of this file.
        |------------------------------------------------------------------
        */
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('organizers/{organizer}/data-source', [OrganizerDataSourceController::class, 'show'])
                ->middleware('can:eventdata.manage')
                ->name('organizers.data-source.show');
            Route::put('organizers/{organizer}/data-source', [OrganizerDataSourceController::class, 'update'])
                ->name('organizers.data-source.update');

            Route::post('provider-connections/{providerConnection}/test', [AdminProviderConnectionController::class, 'test'])
                ->middleware('can:integrations.sync')
                ->name('provider-connections.test');

            Route::post('users/{user}/notifications', [AdminNotificationController::class, 'store'])
                ->middleware('throttle:admin-notifications')
                ->name('users.notifications.store');
        });

        /*
        |------------------------------------------------------------------
        | Store — cart/checkout/orders/payments (brief §109-§114). Login
        | required for the whole store: products link to the Athlete, and
        | requiring auth simplifies ownership for Phase 1 (brief §198).
        |------------------------------------------------------------------
        */
        Route::prefix('cart')->name('cart.')->group(function () {
            Route::get('/', [CartController::class, 'show'])->name('show');
            Route::post('items', [CartController::class, 'addItem'])->name('items.store');
            Route::patch('items/{item}', [CartController::class, 'updateItem'])->name('items.update');
            Route::delete('items/{item}', [CartController::class, 'removeItem'])->name('items.destroy');
            Route::post('coupon', [CartController::class, 'applyCoupon'])->name('coupon.store');
            Route::delete('coupon', [CartController::class, 'removeCoupon'])->name('coupon.destroy');
        });

        Route::post('checkout', [CheckoutController::class, 'store'])
            ->middleware('api.idempotent')
            ->name('checkout.store');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order:uuid}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order:uuid}/payments/online', [PaymentController::class, 'online'])
            ->middleware('api.idempotent')
            ->name('orders.payments.online');
        // App → OpenPay web checkout (in-app browser). One-time URL.
        Route::post('orders/{order:uuid}/checkout-session', [PaymentController::class, 'checkoutSession'])
            ->middleware('throttle:20,1')
            ->name('orders.checkout-session');
        Route::post('orders/{order:uuid}/payments/sync', [PaymentController::class, 'sync'])
            ->middleware('throttle:30,1')
            ->name('orders.payments.sync');

        // Staff-only, gated by `payments.record_manual` inside the Form
        // Request (brief §114: "usar v1 route coherente sin inventar otra API").
        Route::post('admin/orders/{order:uuid}/payments/manual', [PaymentController::class, 'manual'])
            ->middleware('api.idempotent')
            ->name('admin.orders.payments.manual');

        /*
        |------------------------------------------------------------------
        | My Gear / My Events / Event Media (brief §115-§117).
        |------------------------------------------------------------------
        */
        Route::get('me/gear', [GearController::class, 'meIndex'])->name('me.gear.index');
        Route::post('gear/{code}/claim', [GearController::class, 'claim'])
            ->middleware('api.idempotent')
            ->name('gear.claim');

        Route::get('me/events', [MeEventsController::class, 'index'])->name('me.events.index');
        // Same controller/action as `me/events` — `me/history` is the
        // filterable name the minimal mobile-facing API surface uses
        // (brief §24/§75); both accept the same from/to/event_id/... filters.
        Route::get('me/history', [MeEventsController::class, 'index'])->name('me.history.index');
        Route::get('me/events/{participant}', [MeEventsController::class, 'show'])->name('me.events.show');

        Route::prefix('me/events/{participant}/media')->name('me.events.media.')->group(function () {
            Route::get('/', [MeEventMediaController::class, 'index'])->name('index');
            Route::post('/', [MeEventMediaController::class, 'store'])->name('store');
            Route::post('reorder', [MeEventMediaController::class, 'reorder'])->name('reorder');
        });
        Route::get('me/events/{participant}/media-entitlement', [MeEventMediaController::class, 'entitlement'])
            ->name('me.events.media-entitlement');
        Route::patch('me/media/{media:uuid}', [MeEventMediaController::class, 'updateVisibility'])->name('me.media.update');
        Route::delete('me/media/{media:uuid}', [MeEventMediaController::class, 'destroy'])->name('me.media.destroy');

        Route::prefix('me/events/{participant}/gear')->name('me.events.gear.')->group(function () {
            Route::get('/', [MeEventGearController::class, 'index'])->name('index');
            Route::post('/', [MeEventGearController::class, 'store'])->name('store');
            // withoutScopedBindings(): see the identical route in routes/web.php —
            // EventParticipant has no gears() relation, only gearSelections().
            Route::delete('{gear:uuid}', [MeEventGearController::class, 'destroy'])->withoutScopedBindings()->name('destroy');
        });

        Route::prefix('me/notifications')->name('me.notifications.')->group(function () {
            Route::get('/', [MeNotificationController::class, 'index'])->name('index');
            Route::get('preferences', [MeNotificationPreferenceController::class, 'show'])->name('preferences.show');
            Route::put('preferences', [MeNotificationPreferenceController::class, 'update'])->name('preferences.update');
            Route::post('read-all', [MeNotificationController::class, 'markAllRead'])->name('read-all');
            Route::post('{notification}/read', [MeNotificationController::class, 'markRead'])->name('read');
        });

        Route::prefix('me/support-sessions')->name('me.support-sessions.')->group(function () {
            Route::get('/', [MeSupportSessionController::class, 'index'])->name('index');
            Route::post('/', [MeSupportSessionController::class, 'store'])->name('store');
            Route::get('{supportSession}', [MeSupportSessionController::class, 'show'])->name('show');
            Route::get('{supportSession}/manifest', [MeSupportSessionController::class, 'manifest'])->name('manifest');
            Route::get('{supportSession}/triggered', [MeSupportSessionController::class, 'triggered'])->name('triggered');
        });
        Route::post('me/support-messages/{message}/consumed', [MeSupportSessionController::class, 'markConsumed'])->name('me.support-messages.consumed');

        // Entrenamientos — "Envíame ánimo" (docs/renovacion/entrenamientos.md). Owner-only, 404 for others.
        Route::prefix('training')->name('training.')->group(function () {
            Route::get('messages', [TrainingMessageController::class, 'index'])->name('messages.index');
            Route::post('messages/{uuid}/listened', [TrainingMessageController::class, 'listened'])->whereUuid('uuid')->name('messages.listened');
            Route::post('messages/{uuid}/favorite', [TrainingMessageController::class, 'favorite'])->whereUuid('uuid')->name('messages.favorite');
            Route::post('messages/{uuid}/block-sender', [TrainingMessageController::class, 'blockSender'])->whereUuid('uuid')->name('messages.block-sender');
            Route::post('messages/{uuid}/report', [TrainingMessageController::class, 'report'])->whereUuid('uuid')->middleware('throttle:20,1')->name('messages.report');
            Route::delete('messages/{uuid}', [TrainingMessageController::class, 'destroy'])->whereUuid('uuid')->name('messages.destroy');
            Route::get('links', [TrainingLinkController::class, 'index'])->name('links.index');
            Route::post('links', [TrainingLinkController::class, 'store'])->middleware('throttle:30,1')->name('links.store');
            Route::delete('links/{uuid}', [TrainingLinkController::class, 'destroy'])->whereUuid('uuid')->name('links.destroy');
        });
        Route::post('me/push-devices', [MePushDeviceController::class, 'store'])->name('me.push-devices.store');
        Route::delete('me/push-devices/{uuid}', [MePushDeviceController::class, 'destroy'])->name('me.push-devices.destroy');

        /*
        |------------------------------------------------------------------
        | Integrations sync API (docs/adr/0005) — same App\Jobs\SyncExternalEventJob
        | Web dispatches, never a second sync trigger.
        |------------------------------------------------------------------
        */
        Route::prefix('integrations')->name('integrations.')->group(function () {
            Route::middleware('can:integrations.view')->group(function () {
                Route::get('mappings/{eventMapping}/sync-runs/latest', [ApiIntegrationsSyncController::class, 'latestRun'])
                    ->name('mappings.sync-runs.latest');
                Route::get('sync-runs/{syncRun}', [ApiIntegrationsSyncController::class, 'show'])
                    ->name('sync-runs.show');
            });
            Route::middleware('can:integrations.sync')->post('mappings/{eventMapping}/sync', [ApiIntegrationsSyncController::class, 'store'])
                ->name('mappings.sync');
        });
    });

    Route::get('athletes/{athleteProfile:username}', [PublicAthleteController::class, 'show'])
        ->name('athletes.show');

    Route::get('events', [EventController::class, 'index'])->name('events.index');
    Route::get('events/{event:slug}', [EventController::class, 'show'])->name('events.show');

    Route::post('events/{edition}/preregister', [PreregistrationController::class, 'store'])
        ->middleware('throttle:api-register')
        ->name('events.preregister');
    Route::get('preregistrations/{token}', [PreregistrationController::class, 'show'])
        ->middleware('throttle:api-legacy-lookup')
        ->name('preregistrations.show');

    Route::get('legacy-codes/{code}', [LegacyCodeController::class, 'show'])
        ->middleware('throttle:api-legacy-lookup')
        ->name('legacy-codes.show');

    // Public store catalog (brief §109/§119) and the public, no-PII gear
    // lookup (brief §88/§107) — no auth, no secrets.
    Route::get('store/products', [ProductController::class, 'index'])->name('store.products.index');
    Route::get('store/products/{product:slug}', [ProductController::class, 'show'])->name('store.products.show');
    // Published tag collections with their `tag_design` (deck pp. 7–8).
    Route::get('store/collections', [StoreCollectionController::class, 'index'])->name('store.collections.index');
    Route::get('store/collections/{collection:slug}', [StoreCollectionController::class, 'show'])->name('store.collections.show');
    Route::get('gear/{code}', [GearController::class, 'publicShow'])
        ->middleware('throttle:api-legacy-lookup')
        ->name('gear.show');
});

/*
|--------------------------------------------------------------------------
| Payment webhooks — deliberately NOT under /api/v1 or auth:sanctum (brief
| §113): the provider calls this directly. Stripe is authenticated by its
| own signature (StripePaymentGateway::handleWebhook()); Openpay sends no
| signature at all, so OpenPayPaymentGateway::handleWebhook() re-fetches
| the transaction from Openpay's API instead (brief §52). Routes already
| outside the `web` middleware group's CSRF/session handling (this file,
| not routes/web.php).
|--------------------------------------------------------------------------
*/
// AlertOnFailedPaymentWebhook: a 4xx/5xx reply raises a throttled admin "Pagos" alert.
Route::post('webhooks/stripe', StripeWebhookController::class)
    ->middleware(AlertOnFailedPaymentWebhook::class.':stripe')
    ->name('api.webhooks.stripe');
Route::post('webhooks/openpay', OpenPayWebhookController::class)
    ->middleware(AlertOnFailedPaymentWebhook::class.':openpay')
    ->name('api.webhooks.openpay');

require __DIR__.'/api/tracking.php';
require __DIR__.'/api/platform.php';
require __DIR__.'/api/community.php';
require __DIR__.'/api/nfc.php';
require __DIR__.'/api/legacy.php';
require __DIR__.'/api/quotas.php';
require __DIR__.'/api/iap.php';
require __DIR__.'/api/push-campaigns.php';
