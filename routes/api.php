<?php

use App\Http\Controllers\Api\V1\Admin\IncidentController as AdminIncidentController;
use App\Http\Controllers\Api\V1\Admin\OrganizerDataSourceController;
use App\Http\Controllers\Api\V1\Admin\ProviderConnectionController as AdminProviderConnectionController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Devices\DeviceController;
use App\Http\Controllers\Api\V1\Devices\PairingController;
use App\Http\Controllers\Api\V1\Devices\ProductionJobController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\EventOpsController;
use App\Http\Controllers\Api\V1\GearController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Integrations\SyncController as ApiIntegrationsSyncController;
use App\Http\Controllers\Api\V1\LegacyCodeController;
use App\Http\Controllers\Api\V1\LegacyPlateModelController;
use App\Http\Controllers\Api\V1\Me\EventGearController as MeEventGearController;
use App\Http\Controllers\Api\V1\Me\EventMediaController as MeEventMediaController;
use App\Http\Controllers\Api\V1\Me\EventsController as MeEventsController;
use App\Http\Controllers\Api\V1\Me\NotificationController as MeNotificationController;
use App\Http\Controllers\Api\V1\Me\PushDeviceController as MePushDeviceController;
use App\Http\Controllers\Api\V1\Me\SupportSessionController as MeSupportSessionController;
use App\Http\Controllers\Api\V1\MedalController;
use App\Http\Controllers\Api\V1\PreregistrationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PublicAthleteController;
use App\Http\Controllers\Api\V1\Store\CartController;
use App\Http\Controllers\Api\V1\Store\CheckoutController;
use App\Http\Controllers\Api\V1\Store\OrderController;
use App\Http\Controllers\Api\V1\Store\PaymentController;
use App\Http\Controllers\Api\V1\Store\ProductController;
use App\Http\Controllers\Api\Webhooks\OpenPayWebhookController;
use App\Http\Controllers\Api\Webhooks\StripeWebhookController;
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

    Route::middleware(['auth:sanctum', 'user.token'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');

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
        | Admin — Organizer data source, provider test, incident resolution
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

            Route::post('incidents/{incident}/resolve', [AdminIncidentController::class, 'resolve'])
                ->middleware('api.idempotent')
                ->name('incidents.resolve');
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
        });

        Route::post('checkout', [CheckoutController::class, 'store'])
            ->middleware('api.idempotent')
            ->name('checkout.store');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order:uuid}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order:uuid}/payments/online', [PaymentController::class, 'online'])
            ->middleware('api.idempotent')
            ->name('orders.payments.online');

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

        Route::post('me/push-devices', [MePushDeviceController::class, 'store'])->name('me.push-devices.store');
        Route::delete('me/push-devices/{uuid}', [MePushDeviceController::class, 'destroy'])->name('me.push-devices.destroy');

        /*
        |------------------------------------------------------------------
        | Event Ops API (docs/adr/0006, docs/api/use-case-matrix.md) — same
        | Queries/Services App\Http\Controllers\OperatorController (Web)
        | uses. Staff-only: gated by the same `operator.access` permission
        | Web already requires, never a parallel permission scheme.
        |------------------------------------------------------------------
        */
        Route::middleware('can:operator.access')->prefix('event-ops')->name('event-ops.')->group(function () {
            Route::get('{eventEdition}', [EventOpsController::class, 'dashboard'])->name('dashboard');
            Route::get('{eventEdition}/participants/search', [EventOpsController::class, 'searchParticipants'])
                ->middleware('throttle:60,1')
                ->name('participants.search');
            Route::get('participants/{eventParticipant}', [EventOpsController::class, 'participant'])
                ->name('participants.show');
            Route::post('participants/{eventParticipant}/plate', [EventOpsController::class, 'generatePlate'])
                ->middleware('api.idempotent')
                ->name('participants.plate');
        });

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
        ->name('preregistrations.show');

    Route::get('legacy-codes/{code}', [LegacyCodeController::class, 'show'])
        ->middleware('throttle:api-legacy-lookup')
        ->name('legacy-codes.show');

    // Public store catalog (brief §109/§119) and the public, no-PII gear
    // lookup (brief §88/§107) — no auth, no secrets.
    Route::get('store/products', [ProductController::class, 'index'])->name('store.products.index');
    Route::get('store/products/{product:slug}', [ProductController::class, 'show'])->name('store.products.show');
    Route::get('gear/{code}', [GearController::class, 'publicShow'])
        ->middleware('throttle:api-legacy-lookup')
        ->name('gear.show');
    Route::get('legacy-plate-models', [LegacyPlateModelController::class, 'index'])->name('legacy-plate-models.index');

    /*
    |----------------------------------------------------------------------
    | Device API (docs/adr/0002, docs/device-api/v1.md) — the contract a
    | Finisher Event Desktop instance builds on. Still /api/v1: no parallel
    | API surface, just a `device`/`devices`/`production` sub-namespace with
    | its own auth (device.token, not auth:sanctum+user.token) and its own
    | error envelope (see bootstrap/app.php).
    |----------------------------------------------------------------------
    */
    Route::prefix('devices')->name('device.pairing.')->group(function () {
        Route::post('pair', [PairingController::class, 'pair'])
            ->middleware('throttle:api-register')
            ->name('pair');
        // Deliberately a looser limiter than api-register: a desktop polls
        // this every few seconds while waiting for a Super Admin, which
        // isn't the kind of one-shot action api-register is tuned for.
        Route::post('pair/confirm', [PairingController::class, 'confirm'])
            ->middleware('throttle:device-pairing-confirm')
            ->name('confirm');
    });

    Route::middleware(['auth:sanctum', 'device.token'])->group(function () {
        Route::get('device', [DeviceController::class, 'show'])
            ->middleware('ability:device:heartbeat')
            ->name('device.show');
        Route::get('device/bootstrap', [DeviceController::class, 'bootstrap'])
            ->middleware('ability:device:heartbeat')
            ->name('device.bootstrap');
        Route::post('device/heartbeat', [DeviceController::class, 'heartbeat'])
            ->middleware('ability:device:heartbeat')
            ->name('device.heartbeat');

        Route::prefix('production/jobs')->name('device.production.jobs.')->group(function () {
            Route::get('next', [ProductionJobController::class, 'next'])
                ->middleware('ability:production:read')
                ->name('next');
            Route::get('{job}', [ProductionJobController::class, 'show'])
                ->middleware('ability:production:read')
                ->name('show');
            Route::post('{job}/claim', [ProductionJobController::class, 'claim'])
                ->middleware(['ability:production:claim', 'device.idempotent'])
                ->name('claim');
            Route::post('{job}/release', [ProductionJobController::class, 'release'])
                ->middleware(['ability:production:claim', 'device.idempotent'])
                ->name('release');
            Route::get('{job}/artifact/{face}', [ProductionJobController::class, 'artifact'])
                ->middleware('ability:production:read')
                ->name('artifact');

            // Slice 2 (docs/adr/0003) — physical workflow. `production:update`
            // gates coordination steps, `production:engrave` gates the four
            // steps that represent actual laser activity — see
            // App\Enums\DeviceAbility.
            Route::post('{job}/prepare', [ProductionJobController::class, 'prepare'])
                ->middleware(['ability:production:update', 'device.idempotent'])
                ->name('prepare');
            Route::post('{job}/front/start', [ProductionJobController::class, 'frontStart'])
                ->middleware(['ability:production:engrave', 'device.idempotent'])
                ->name('front.start');
            Route::post('{job}/front/complete', [ProductionJobController::class, 'frontComplete'])
                ->middleware(['ability:production:engrave', 'device.idempotent'])
                ->name('front.complete');
            Route::post('{job}/flip/confirm', [ProductionJobController::class, 'flipConfirm'])
                ->middleware(['ability:production:update', 'device.idempotent'])
                ->name('flip.confirm');
            Route::post('{job}/back/start', [ProductionJobController::class, 'backStart'])
                ->middleware(['ability:production:engrave', 'device.idempotent'])
                ->name('back.start');
            Route::post('{job}/back/complete', [ProductionJobController::class, 'backComplete'])
                ->middleware(['ability:production:engrave', 'device.idempotent'])
                ->name('back.complete');
            Route::post('{job}/qr/verify', [ProductionJobController::class, 'qrVerify'])
                ->middleware(['ability:production:update', 'device.idempotent'])
                ->name('qr.verify');
            Route::post('{job}/deliver', [ProductionJobController::class, 'deliver'])
                ->middleware(['ability:production:update', 'device.idempotent'])
                ->name('deliver');
            Route::post('{job}/fail', [ProductionJobController::class, 'fail'])
                ->middleware(['ability:production:update', 'device.idempotent'])
                ->name('fail');
            Route::post('{job}/cancel', [ProductionJobController::class, 'cancel'])
                ->middleware(['ability:production:update', 'device.idempotent'])
                ->name('cancel');
        });
    });
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
Route::post('webhooks/stripe', StripeWebhookController::class)->name('api.webhooks.stripe');
Route::post('webhooks/openpay', OpenPayWebhookController::class)->name('api.webhooks.openpay');
