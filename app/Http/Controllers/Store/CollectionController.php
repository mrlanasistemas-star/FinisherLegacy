<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Queries\Store\PublicCollections;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public collections (/colecciones): every Finisher Legacy Tag design
 * belongs to a collection (destination · discipline · edition). Only
 * published collections are visible; an unpublished one is a 404, never
 * a "coming soon" page that leaks a draft.
 */
class CollectionController extends Controller
{
    public function index(PublicCollections $collections): Response
    {
        return Inertia::render('collections/Index', [
            'collections' => $collections->cards(),
        ]);
    }

    public function show(Collection $collection, PublicCollections $collections): Response
    {
        abort_unless($collection->isPublished(), 404);

        $others = $collections->query()
            ->whereKeyNot($collection->id)
            ->limit(3)
            ->get()
            ->map(fn (Collection $other) => $collections->card($other))
            ->values()
            ->all();

        return Inertia::render('collections/Show', [
            'collection' => $collections->detail($collection),
            'otherCollections' => $others,
        ]);
    }
}
