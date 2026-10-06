<?php

namespace App\Http\Controllers;

use App\Http\Resources\Api\V1\AthleteResource;
use App\Http\Resources\EventEditionCardResource;
use App\Models\AthleteProfile;
use App\Models\Product;
use App\Queries\Commerce\GetFeaturedProducts;
use App\Services\EventCatalogService;
use App\Services\Social\SocialVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Navbar "Buscar" — the same rules as the API's global search
 * (Api\V1\Social\SearchController): athletes respect privacy and blocks
 * through SocialVisibility, events/products only return published rows.
 * Open to guests (public content only).
 */
class SearchController extends Controller
{
    private const int PER_TYPE = 8;

    public function __invoke(Request $request, SocialVisibility $visibility, EventCatalogService $events): Response
    {
        $viewer = $request->user();
        $term = trim(mb_substr($request->string('q')->toString(), 0, 60));
        $searched = mb_strlen($term) >= (int) config('finisher.social.search_min_length');

        $athletes = collect();
        $eventResults = collect();
        $products = collect();

        if ($searched) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
            $following = $visibility->followingIds($viewer);

            $athletes = $visibility->scopeDiscoverableProfiles(AthleteProfile::query(), $viewer)
                ->when($viewer !== null, fn (Builder $q) => $q->where('athlete_profiles.user_id', '!=', $viewer->id))
                ->where(fn (Builder $q) => $q
                    ->where('username', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)))
                ->with('user.athleteProfile.mainSport')
                ->orderBy('username')
                ->limit(self::PER_TYPE)
                ->get()
                ->map(function (AthleteProfile $profile) use ($following) {
                    $profile->user->setAttribute('is_following', in_array($profile->user_id, $following, true));

                    return $profile->user;
                });

            $eventResults = $events->publishedEditions(['q' => $term], perPage: self::PER_TYPE)->getCollection();

            $products = Product::query()
                ->with(['category', 'variants.inventoryLevels', 'media'])
                ->where('active', true)
                ->where('status', 'active')
                ->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('brand', 'like', $like))
                ->orderBy('name')
                ->limit(self::PER_TYPE)
                ->get();
        }

        return Inertia::render('search/Index', [
            'query' => $term,
            'searched' => $searched,
            'athletes' => AthleteResource::collection($athletes)->resolve($request),
            'events' => EventEditionCardResource::collection($eventResults)->resolve($request),
            'products' => $products->map(fn (Product $product) => GetFeaturedProducts::summarize($product))->values(),
        ]);
    }
}
