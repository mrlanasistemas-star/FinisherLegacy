<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Admin\SearchAdminRecords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON source of the admin command menu (⌘K). Same pattern as the
 * operator's participant search: a small same-origin JSON endpoint for an
 * Inertia page, never the public REST API.
 */
class GlobalSearchController extends Controller
{
    public function __invoke(Request $request, SearchAdminRecords $search): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:'.SearchAdminRecords::MAX_LENGTH]]);

        return response()->json([
            'data' => $search->handle($request->user(), $request->string('q')->toString()),
        ]);
    }
}
