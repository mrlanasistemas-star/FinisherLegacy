<?php

use App\Http\Controllers\MediaFileController;
use Illuminate\Support\Facades\Route;

/*
| Media delivery (A7, docs/SYNOLOGY.md). Authorization happens inside the
| controller: valid temporary signature OR MediaFilePolicy — so one route
| serves public, private and owner-only renditions. Throttled per IP.
*/
Route::get('media/f/{mediaFile:uuid}/{variant}', [MediaFileController::class, 'show'])
    ->where('variant', '[a-z0-9_]{1,32}')
    ->middleware('throttle:600,1')
    ->name('media.file');
