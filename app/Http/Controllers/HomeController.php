<?php

namespace App\Http\Controllers;

use App\Enums\ProductType;
use App\Queries\Store\PublicCollections;
use App\Queries\Store\StorefrontCatalog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home for the Finisher Legacy Tag (docs/architecture/public-experience.md):
 * object → story → permanence. The only data it needs is real catalog
 * data — published collections and active tag products. Everything else
 * on the page is brand storytelling, and the example Legacy is labelled
 * as an example. No collection yet → the page says so honestly.
 */
class HomeController extends Controller
{
    public function index(PublicCollections $collections, StorefrontCatalog $catalog): Response
    {
        $tagProducts = $catalog->query()
            ->where('type', ProductType::LegacyTag->value)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        return Inertia::render('Home', [
            'collections' => $collections->cards(6),
            'tagProducts' => $catalog->cards($tagProducts),
        ]);
    }
}
