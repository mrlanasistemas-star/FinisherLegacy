<?php

use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Store\CollectionController;
use Illuminate\Support\Facades\Route;

// Owned by the collections work stream (docs/architecture/commerce-os.md,
// docs/architecture/public-experience.md).

Route::get('colecciones', [CollectionController::class, 'index'])->name('collections.index');
Route::get('colecciones/{collection:slug}', [CollectionController::class, 'show'])->name('collections.show');

// Dynamic sitemap: marketing pages + published collections + active
// products + public events. Never athlete profiles or stories.
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
