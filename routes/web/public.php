<?php

use App\Http\Controllers\Store\ProductController as StoreProductController;
use Illuminate\Support\Facades\Route;

/*
| Public web (owner spec §6, §17-§22, §98-§103): "Cómo funciona" and store
| browsing. Cart / checkout / orders live in routes/web.php (commerce).
*/

Route::inertia('como-funciona', 'HowItWorks')->name('how-it-works');
// The page used to live at /how-it-works — keep old links working.
Route::permanentRedirect('how-it-works', '/como-funciona');

Route::prefix('tienda')->group(function () {
    Route::get('/', [StoreProductController::class, 'index'])->name('store.products.index');
    // Fixed segments first so they never resolve as a product slug.
    Route::get('catalogo', [StoreProductController::class, 'catalog'])->name('store.catalog');
    Route::get('categoria/{category:slug}', [StoreProductController::class, 'category'])->name('store.categories.show');
    Route::get('{product:slug}', [StoreProductController::class, 'show'])->name('store.products.show');
});
