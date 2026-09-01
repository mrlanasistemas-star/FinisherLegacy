<?php

use App\Http\Controllers\Admin\AthleteController as AdminAthleteController;
use App\Http\Controllers\Admin\AthleteIdentityConflictController as AdminAthleteIdentityConflictController;
use App\Http\Controllers\Admin\AuditController as AdminAuditController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EditionController as AdminEditionController;
use App\Http\Controllers\Admin\IncidentController as AdminIncidentController;
use App\Http\Controllers\Admin\Integrations\ProviderConnectionController as AdminProviderConnectionController;
use App\Http\Controllers\Admin\Integrations\SyncController as AdminSyncController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\LegacyCodeController as AdminLegacyCodeController;
use App\Http\Controllers\Admin\LegacyPlateModelController as AdminLegacyPlateModelController;
use App\Http\Controllers\Admin\LegacyPlatePresaleController as AdminLegacyPlatePresaleController;
use App\Http\Controllers\Admin\LegacyPlateProductionController as AdminLegacyPlateProductionController;
use App\Http\Controllers\Admin\MachineProfileController as AdminMachineProfileController;
use App\Http\Controllers\Admin\OrganizerController as AdminOrganizerController;
use App\Http\Controllers\Admin\ParticipantController as AdminParticipantController;
use App\Http\Controllers\Admin\PlateController as AdminPlateController;
use App\Http\Controllers\Admin\PlateStudioController as AdminPlateStudioController;
use App\Http\Controllers\Admin\PreregistrationController as AdminPreregistrationController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductionDeviceController as AdminProductionDeviceController;
use App\Http\Controllers\Admin\ProductionSetupController as AdminProductionSetupController;
use App\Http\Controllers\Admin\RoleController as AdminRoleController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\Store\OrderController as AdminStoreOrderController;
use App\Http\Controllers\Admin\Store\PaymentController as AdminStorePaymentController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AthleteHistoryController;
use App\Http\Controllers\AthleteProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LegacyCodeController;
use App\Http\Controllers\MedalController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\PreregistrationController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\Store\CartController as StoreCartController;
use App\Http\Controllers\Store\CheckoutController as StoreCheckoutController;
use App\Http\Controllers\Store\OrderController as StoreOrderController;
use App\Http\Controllers\Store\ProductController as StoreProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::inertia('how-it-works', 'HowItWorks')->name('how-it-works');
Route::inertia('privacy', 'Privacy')->name('privacy');
Route::inertia('terms', 'Terms')->name('terms');
Route::inertia('contact', 'Contact')->name('contact');

Route::get('events', [EventController::class, 'index'])->name('events.index');
Route::get('events/{event:slug}', [EventController::class, 'show'])->name('events.show');
Route::get('events/{event:slug}/preregister', [EventController::class, 'preregister'])->name('events.preregister');
Route::post('events/{event:slug}/preregister', [EventController::class, 'storePreregistration'])
    ->middleware('throttle:api-register')
    ->name('preregistrations.store');

Route::get('preregistrations/{token}', [PreregistrationController::class, 'show'])->name('preregistrations.show');
Route::get('preregistrations/{token}/qr.svg', [PreregistrationController::class, 'qr'])->name('preregistrations.qr');

Route::get('l/{code}', [LegacyCodeController::class, 'show'])->name('legacy-code.show');
Route::get('l/{code}/qr.svg', [LegacyCodeController::class, 'qr'])->name('legacy-code.qr');
Route::get('l/{code}/continue/{provider}', [LegacyCodeController::class, 'continueTo'])->name('legacy-code.continue');

Route::get('/@{athleteProfile:username}', [PublicProfileController::class, 'show'])->name('profile.public');

Route::prefix('tienda')->name('store.products.')->group(function () {
    Route::get('/', [StoreProductController::class, 'index'])->name('index');
    Route::get('{product:slug}', [StoreProductController::class, 'show'])->name('show');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('l/{code}/claim', [LegacyCodeController::class, 'claim'])->name('legacy-code.claim');

    Route::prefix('carrito')->name('store.cart.')->group(function () {
        Route::get('/', [StoreCartController::class, 'show'])->name('show');
        Route::post('items', [StoreCartController::class, 'addItem'])->name('items.store');
        Route::patch('items/{item}', [StoreCartController::class, 'updateItem'])->name('items.update');
        Route::delete('items/{item}', [StoreCartController::class, 'removeItem'])->name('items.destroy');
    });

    Route::prefix('checkout')->name('store.checkout.')->group(function () {
        Route::get('/', [StoreCheckoutController::class, 'show'])->name('show');
        Route::post('/', [StoreCheckoutController::class, 'store'])->name('store');
        Route::post('{order:uuid}/online-payment', [StoreCheckoutController::class, 'onlinePayment'])->name('online-payment');
    });

    Route::prefix('mis-pedidos')->name('store.orders.')->group(function () {
        Route::get('/', [StoreOrderController::class, 'index'])->name('index');
        Route::get('{order:uuid}', [StoreOrderController::class, 'show'])->name('show');
    });

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // "Mi Legado" (product UX consolidation brief §3-§6) — the card
    // gallery lives on the main /dashboard route (nav item "Mi Legado"
    // already pointed here); this is only its per-participation detail.
    Route::get('dashboard/legado/{participant}', [AthleteHistoryController::class, 'legadoShow'])->name('dashboard.legado.show');

    // Still reachable by direct URL (brief §3: "no necesariamente borres
    // rutas") — not in the sidebar anymore, consolidated into Mi Legado.
    Route::get('dashboard/my-events', [AthleteHistoryController::class, 'myEvents'])->name('dashboard.my-events');
    Route::get('dashboard/my-events/{participant}', [AthleteHistoryController::class, 'myEventShow'])->name('dashboard.my-events.show');
    Route::post('dashboard/my-events/{participant}/media', [AthleteHistoryController::class, 'uploadMedia'])->name('dashboard.my-events.media.store');
    Route::patch('dashboard/media/{media:uuid}/visibility', [AthleteHistoryController::class, 'updateMediaVisibility'])->name('dashboard.media.visibility');
    Route::delete('dashboard/media/{media:uuid}', [AthleteHistoryController::class, 'destroyMedia'])->name('dashboard.media.destroy');
    Route::get('dashboard/my-plates', [AthleteHistoryController::class, 'myPlates'])->name('dashboard.my-plates');
    Route::get('dashboard/my-gear', [AthleteHistoryController::class, 'myGear'])->name('dashboard.my-gear');

    Route::get('dashboard/profile/edit', [AthleteProfileController::class, 'edit'])->name('dashboard.profile.edit');
    Route::patch('dashboard/profile', [AthleteProfileController::class, 'update'])->name('dashboard.profile.update');

    Route::get('dashboard/medals/search/events', [MedalController::class, 'searchEvents'])->name('dashboard.medals.search-events');
    Route::get('dashboard/medals/search/match', [MedalController::class, 'matchParticipant'])->name('dashboard.medals.match-participant');

    Route::resource('dashboard/medals', MedalController::class)
        ->parameters(['medals' => 'medal'])
        ->names('dashboard.medals');

    Route::delete('dashboard/medals/{medal}/gallery/{medalImage}', [MedalController::class, 'destroyGalleryImage'])
        ->name('dashboard.medals.gallery.destroy');

    Route::middleware('can:operator.access')->prefix('operator')->name('operator.')->group(function () {
        Route::get('/', [OperatorController::class, 'index'])->name('index');
        Route::post('event', [OperatorController::class, 'selectEvent'])->name('select-event');
        Route::get('status', [OperatorController::class, 'status'])->middleware('throttle:30,1')->name('status');
        Route::get('search', [OperatorController::class, 'search'])->middleware('throttle:60,1')->name('search');
        Route::post('preview', [OperatorController::class, 'previewPlate'])->middleware('throttle:60,1')->name('preview');
        Route::get('participants/{eventParticipant}', [OperatorController::class, 'showParticipant'])->name('participants.show');
        Route::post('participants/{eventParticipant}/plate', [OperatorController::class, 'generateIntegratedPlate'])->name('participants.plate');
        Route::post('quick-plate', [OperatorController::class, 'generateQuickPlate'])->name('quick-plate');
        Route::get('quick-plate/{plate}', [OperatorController::class, 'showQuickPlate'])->name('quick-plate.show');
    });

    Route::middleware('can:production.access')->prefix('production')->name('production.')->group(function () {
        Route::get('/', [ProductionController::class, 'index'])->name('index');

        // Manual/web fallback for the same physical workflow the Device
        // API drives (docs/adr/0003-production-state-machine.md §45) —
        // every route here calls the same Actions as the Device API.
        Route::middleware('can:production.manage')->prefix('jobs/{job}')->name('jobs.')->group(function () {
            Route::patch('prepare', [ProductionController::class, 'prepare'])->name('prepare');
            Route::patch('front/start', [ProductionController::class, 'frontStart'])->name('front.start');
            Route::patch('front/complete', [ProductionController::class, 'frontComplete'])->name('front.complete');
            Route::patch('flip/confirm', [ProductionController::class, 'flipConfirm'])->name('flip.confirm');
            Route::patch('back/start', [ProductionController::class, 'backStart'])->name('back.start');
            Route::patch('back/complete', [ProductionController::class, 'backComplete'])->name('back.complete');
            Route::post('qr/verify', [ProductionController::class, 'qrVerify'])->name('qr.verify');
            Route::patch('deliver', [ProductionController::class, 'deliver'])->name('deliver');
            Route::post('fail', [ProductionController::class, 'fail'])->name('fail');
            Route::patch('cancel', [ProductionController::class, 'cancel'])->name('cancel');
        });
    });

    // Production file downloads: gated by plates.view alone (not the wider
    // dashboard.admin.view admin-panel gate) so operators and production staff can
    // download what they just generated without needing full admin access.
    Route::middleware('can:plates.view')->prefix('admin/plates')->name('admin.plates.')->group(function () {
        Route::get('{plate}/export/{face}/{format}', [AdminPlateController::class, 'export'])->name('export');
        Route::get('{plate}/export-qr/{format?}', [AdminPlateController::class, 'exportQr'])->name('export-qr');
        Route::get('{plate}/export-package', [AdminPlateController::class, 'exportPackage'])->name('export-package');
    });

    Route::middleware('can:imports.manage')->prefix('imports')->name('imports.')->group(function () {
        Route::get('/', [ImportController::class, 'index'])->name('index');
        Route::get('create', [ImportController::class, 'create'])->name('create');
        Route::post('upload', [ImportController::class, 'upload'])->middleware('throttle:20,1')->name('upload');
        Route::post('/', [ImportController::class, 'store'])->name('store');
        Route::get('{eventImport}', [ImportController::class, 'show'])->name('show');
    });

    Route::middleware('can:dashboard.admin.view')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::middleware('can:events.view')->get('editions', [AdminEditionController::class, 'index'])->name('editions.index');
        Route::middleware('can:events.manage')->group(function () {
            // Registered before the {eventEdition} wildcard route below —
            // Laravel matches in registration order, so "create" would
            // otherwise be swallowed as an eventEdition route parameter.
            Route::get('editions/create', [AdminEditionController::class, 'create'])->name('editions.create');
            Route::post('editions', [AdminEditionController::class, 'store'])->name('editions.store');
            Route::post('editions/{eventEdition}/price-schedules', [AdminEditionController::class, 'storePriceSchedule'])->name('editions.price-schedules.store');
        });
        Route::middleware('can:events.view')->get('editions/{eventEdition}', [AdminEditionController::class, 'show'])->name('editions.show');

        Route::middleware('can:editions.manage')->prefix('events/{eventEdition}/production-setup')->name('editions.production-setup.')->group(function () {
            Route::get('/', [AdminProductionSetupController::class, 'show'])->name('show');
            Route::post('assign-template', [AdminProductionSetupController::class, 'assignTemplate'])->name('assign-template');
            Route::post('qr-test', [AdminProductionSetupController::class, 'markQrTested'])->name('qr-test');
        });
        Route::middleware('can:preregistrations.view')->get('preregistrations', [AdminPreregistrationController::class, 'index'])->name('preregistrations.index');
        Route::middleware('can:participants.view')->prefix('participants')->name('participants.')->group(function () {
            Route::get('/', [AdminParticipantController::class, 'index'])->name('index');
            Route::get('export', [AdminParticipantController::class, 'export'])->name('export');
            Route::get('{eventParticipant}', [AdminParticipantController::class, 'show'])->name('show');
        });

        // Canonical athlete identity (docs/adr/0004-athlete-canonical-identity.md).
        Route::middleware('can:athletes.view')->prefix('athletes')->name('athletes.')->group(function () {
            Route::get('/', [AdminAthleteController::class, 'index'])->name('index');
            Route::get('{athlete}', [AdminAthleteController::class, 'show'])->name('show');
        });
        Route::middleware('can:athletes.manage')->prefix('identity-conflicts')->name('identity-conflicts.')->group(function () {
            Route::get('/', [AdminAthleteIdentityConflictController::class, 'index'])->name('index');
            Route::post('{conflict}/resolve', [AdminAthleteIdentityConflictController::class, 'resolve'])->name('resolve');
        });

        // Unified external event ingestion (docs/adr/0005-unified-event-ingestion.md).
        Route::middleware('can:integrations.view')->prefix('integrations')->name('integrations.')->group(function () {
            Route::get('/', [AdminProviderConnectionController::class, 'index'])->name('index');
            Route::get('{providerConnection}', [AdminProviderConnectionController::class, 'show'])->name('show');
            Route::get('sync-runs/{syncRun}', [AdminSyncController::class, 'show'])->name('sync-runs.show');
            Route::get('sync-runs/{syncRun}/status', [AdminSyncController::class, 'status'])->name('sync-runs.status');

            Route::middleware('can:integrations.manage')->group(function () {
                Route::post('/', [AdminProviderConnectionController::class, 'store'])->name('store');
                Route::post('{providerConnection}/test', [AdminProviderConnectionController::class, 'test'])->name('test');
                Route::post('{providerConnection}/events', [AdminProviderConnectionController::class, 'linkEvent'])->name('events.link');
            });

            Route::middleware('can:integrations.sync')->post('mappings/{eventMapping}/sync', [AdminSyncController::class, 'store'])->name('mappings.sync');
        });

        Route::middleware('can:plates.view')->group(function () {
            Route::get('plates', [AdminPlateController::class, 'index'])->name('plates.index');
            Route::post('plates/export-batch', [AdminPlateController::class, 'exportBatch'])->name('plates.export-batch');
            Route::get('plates/{plate}', [AdminPlateController::class, 'show'])->name('plates.show');
            Route::middleware('can:plates.manage')->post('plates/{plate}/reprint', [AdminPlateController::class, 'reprint'])->name('plates.reprint');
        });
        Route::middleware('can:legacycodes.view')->get('legacy-codes', [AdminLegacyCodeController::class, 'index'])->name('legacy-codes.index');

        Route::middleware('can:legacyplates.manage')->prefix('legacy-plate-models')->name('legacy-plate-models.')->group(function () {
            Route::get('/', [AdminLegacyPlateModelController::class, 'index'])->name('index');
            Route::get('{legacyPlateModel}', [AdminLegacyPlateModelController::class, 'show'])->name('show');
            Route::post('/', [AdminLegacyPlateModelController::class, 'store'])->name('store');
            Route::patch('{legacyPlateModel}', [AdminLegacyPlateModelController::class, 'update'])->name('update');
            Route::patch('{legacyPlateModel}/fields', [AdminLegacyPlateModelController::class, 'updateFields'])->name('fields.update');
        });

        Route::middleware('can:legacyplates.produce')->prefix('legacy-plates/production')->name('legacy-plates.production.')->group(function () {
            Route::get('/', [AdminLegacyPlateProductionController::class, 'index'])->name('index');
            Route::post('entitlements/{legacyPlateEntitlement}/produce', [AdminLegacyPlateProductionController::class, 'produce'])->name('produce');
        });

        Route::middleware('can:legacyplates.manage')->get('legacy-plates/presales', [AdminLegacyPlatePresaleController::class, 'index'])->name('legacy-plates.presales.index');

        Route::middleware('can:platetemplates.view')->prefix('plate-studio')->name('plate-studio.')->group(function () {
            Route::get('/', [AdminPlateStudioController::class, 'index'])->name('index');
            Route::get('templates/{plateTemplate}/versions/{plateTemplateVersion}', [AdminPlateStudioController::class, 'edit'])->name('edit');
            Route::post('preview', [AdminPlateStudioController::class, 'preview'])->name('preview');
            Route::get('versions/{plateTemplateVersion}/test-export/{face}', [AdminPlateStudioController::class, 'testExport'])->name('versions.test-export');
            Route::get('calibration/{face}', [AdminPlateStudioController::class, 'calibrationCard'])->name('calibration');

            Route::middleware('can:platetemplates.manage')->group(function () {
                Route::post('templates', [AdminPlateStudioController::class, 'store'])->name('templates.store');
                Route::patch('templates/{plateTemplate}', [AdminPlateStudioController::class, 'update'])->name('templates.update');
                Route::post('templates/{plateTemplate}/duplicate', [AdminPlateStudioController::class, 'duplicate'])->name('templates.duplicate');
                Route::post('templates/{plateTemplate}/archive', [AdminPlateStudioController::class, 'archiveTemplate'])->name('templates.archive');
                Route::post('templates/{plateTemplate}/versions', [AdminPlateStudioController::class, 'createVersion'])->name('versions.create');
                Route::patch('versions/{plateTemplateVersion}', [AdminPlateStudioController::class, 'updateVersion'])->name('versions.update');
                Route::post('versions/{plateTemplateVersion}/publish', [AdminPlateStudioController::class, 'publish'])->name('versions.publish');
                Route::post('versions/{plateTemplateVersion}/archive', [AdminPlateStudioController::class, 'archiveVersion'])->name('versions.archive');
            });
        });

        // Machine profiles: a workflow label ("Fiber 30W — LightBurn"), not
        // a driver — gated the same as plate templates since it's part of
        // the same production-configuration surface.
        Route::middleware('can:platetemplates.view')->prefix('machine-profiles')->name('machine-profiles.')->group(function () {
            Route::get('/', [AdminMachineProfileController::class, 'index'])->name('index');

            Route::middleware('can:platetemplates.manage')->group(function () {
                Route::post('/', [AdminMachineProfileController::class, 'store'])->name('store');
                Route::patch('{machineProfile}', [AdminMachineProfileController::class, 'update'])->name('update');
            });
        });

        // Estaciones: Super Admin only in practice — `admin` gets every
        // permission via RolePermissionSeeder, `super_admin` bypasses via
        // Gate::before, and no other seeded role is granted these keys.
        Route::middleware('can:productiondevices.view')->prefix('production-devices')->name('production-devices.')->group(function () {
            Route::get('/', [AdminProductionDeviceController::class, 'index'])->name('index');

            Route::middleware('can:productiondevices.manage')->group(function () {
                Route::post('pairings/{pairingRequest}/approve', [AdminProductionDeviceController::class, 'approvePairing'])->name('pairings.approve');
                Route::post('{device}/revoke', [AdminProductionDeviceController::class, 'revoke'])->name('revoke');
            });
        });

        Route::middleware('can:incidents.view')->get('incidents', [AdminIncidentController::class, 'index'])->name('incidents.index');
        Route::middleware('can:incidents.manage')->patch('incidents/{incident}/resolve', [AdminIncidentController::class, 'resolve'])->name('incidents.resolve');

        Route::middleware('can:users.view')->get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::middleware('can:users.manage')->group(function () {
            Route::get('users/create', [AdminUserController::class, 'create'])->name('users.create');
            Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
            Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
            Route::patch('users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('users.update-status');
            Route::patch('users/{user}/roles', [AdminUserController::class, 'updateRoles'])->name('users.update-roles');
            Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('users.reset-password');
        });

        Route::middleware('can:roles.manage')->prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [AdminRoleController::class, 'index'])->name('index');
            Route::get('create', [AdminRoleController::class, 'create'])->name('create');
            Route::post('/', [AdminRoleController::class, 'store'])->name('store');
            Route::get('{role}/edit', [AdminRoleController::class, 'edit'])->name('edit');
            Route::patch('{role}', [AdminRoleController::class, 'update'])->name('update');
            Route::post('{role}/duplicate', [AdminRoleController::class, 'duplicate'])->name('duplicate');
        });

        Route::middleware('can:organizers.view')->get('organizers', [AdminOrganizerController::class, 'index'])->name('organizers.index');
        Route::middleware('can:organizers.view')->get('organizers/{organizer}', [AdminOrganizerController::class, 'show'])->name('organizers.show');
        Route::middleware('can:organizers.manage')->post('organizers', [AdminOrganizerController::class, 'store'])->name('organizers.store');
        Route::middleware('can:organizers.manage')->patch('organizers/{organizer}', [AdminOrganizerController::class, 'update'])->name('organizers.update');
        Route::middleware('can:eventdata.manage')->put('organizers/{organizer}/data-source', [AdminOrganizerController::class, 'updateDataSource'])->name('organizers.data-source.update');
        Route::middleware('can:integrations.sync')->post('provider-connections/{providerConnection}/test', [AdminOrganizerController::class, 'testConnection'])->name('provider-connections.test');

        Route::middleware('can:eventdata.manage')->get('data-sources', [AdminOrganizerController::class, 'dataSources'])->name('data-sources.index');

        Route::middleware('can:audit.view')->get('audit', [AdminAuditController::class, 'index'])->name('audit.index');
        Route::get('settings', [AdminSettingsController::class, 'index'])->name('settings.index');

        Route::middleware('can:products.manage')->prefix('products')->name('products.')->group(function () {
            Route::get('/', [AdminProductController::class, 'index'])->name('index');
            Route::post('/', [AdminProductController::class, 'store'])->name('store');
            Route::get('{product}', [AdminProductController::class, 'show'])->name('show');
            Route::patch('{product}', [AdminProductController::class, 'update'])->name('update');
            Route::post('{product}/variants', [AdminProductController::class, 'storeVariant'])->name('variants.store');
            Route::patch('variants/{variant}', [AdminProductController::class, 'updateVariant'])->name('variants.update');
        });

        Route::middleware('can:inventory.manage')->prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/', [AdminInventoryController::class, 'index'])->name('index');
            Route::post('adjust', [AdminInventoryController::class, 'adjust'])->name('adjust');
        });

        Route::middleware('can:orders.view')->prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [AdminStoreOrderController::class, 'index'])->name('index');
            Route::get('{order:uuid}', [AdminStoreOrderController::class, 'show'])->name('show');
        });
        Route::middleware('can:orders.manage')->post('orders/items/{item}/fulfill', [AdminStoreOrderController::class, 'fulfillItem'])->name('orders.items.fulfill');

        Route::middleware('can:payments.view')->get('payments', [AdminStorePaymentController::class, 'index'])->name('payments.index');
        Route::middleware('can:payments.record_manual')->post('orders/{order:uuid}/payments/manual', [AdminStorePaymentController::class, 'registerManual'])->name('orders.payments.manual');
    });
});

require __DIR__.'/settings.php';
