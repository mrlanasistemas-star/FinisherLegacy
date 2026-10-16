<?php

namespace App\Http\Controllers;

use App\Enums\EditionStatus;
use App\Enums\EventStatus;
use App\Enums\ProductType;
use App\Http\Resources\Api\V1\MomentResource;
use App\Http\Resources\EventEditionCardResource;
use App\Models\AthleteProfile;
use App\Models\EventEdition;
use App\Models\Product;
use App\Queries\Social\MomentQuery;
use App\Queries\Store\StorefrontCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Home as a story (owner spec §38-§44): hero, what it is, the five-step
 * journey, the NFC moment, the store, community and a final CTA. Only
 * real data is passed — every block that has nothing real to show
 * renders its own honest empty state.
 */
class HomeController extends Controller
{
    public function index(Request $request, StorefrontCatalog $catalog, MomentQuery $moments): Response
    {
        $featuredEditions = EventEdition::query()
            ->whereHas('event', fn ($query) => $query->where('status', EventStatus::Published))
            ->where('status', EditionStatus::Published)
            ->where('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->with(['event.sport', 'races'])
            ->limit(3)
            ->get();

        // Public posts only — what a guest would see in Comunidad.
        $communityPosts = $moments->visibleTo($request->user())
            ->where('legacy_moments.visibility', 'public')
            ->orderByDesc('legacy_moments.id')
            ->limit(6)
            ->get();

        $communityAthletes = AthleteProfile::query()
            ->where('profile_visibility', 'public')
            ->whereNotNull('profile_photo_path')
            ->whereNotNull('username')
            ->with(['user:id,first_name,last_name', 'mainSport:id,name'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        // Store band: the Legacy Plate leads, then the rest of the catalog.
        $products = $catalog->query()
            ->orderByRaw('type = ? desc', [ProductType::LegacyPlate->value])
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get();

        $legacyPlate = Product::query()
            ->where('type', ProductType::LegacyPlate)
            ->where('active', true)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->first(['slug']);

        return Inertia::render('Home', [
            'featuredEditions' => $featuredEditions->map(fn ($edition) => (new EventEditionCardResource($edition))->resolve()),
            'featuredProducts' => $catalog->cards($products),
            'communityPosts' => MomentResource::collection($communityPosts)->resolve($request),
            'communityAthletes' => $communityAthletes
                ->filter(fn (AthleteProfile $profile) => $profile->user !== null)
                ->map(fn (AthleteProfile $profile) => [
                    'username' => $profile->username,
                    'name' => $profile->user->name,
                    'sport' => $profile->mainSport?->name,
                    'city' => $profile->city,
                    'photo_url' => asset('storage/'.$profile->profile_photo_path),
                ])
                ->values(),
            'legacyPlateSlug' => $legacyPlate?->slug,
        ]);
    }
}
