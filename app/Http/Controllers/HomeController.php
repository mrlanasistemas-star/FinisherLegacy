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
use App\Models\Sport;
use App\Queries\Commerce\GetFeaturedProducts;
use App\Queries\Social\MomentQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request, GetFeaturedProducts $featuredProducts, MomentQuery $moments): Response
    {
        $featuredEditions = EventEdition::query()
            ->whereHas('event', fn ($query) => $query->where('status', EventStatus::Published))
            ->where('status', EditionStatus::Published)
            ->where('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->with(['event.sport', 'races'])
            ->limit(3)
            ->get();

        $legacyProfile = AthleteProfile::query()
            ->where('profile_visibility', 'public')
            ->whereHas('user.medals')
            ->with(['user.medals' => fn ($query) => $query->where('visibility', 'public')->latest()->limit(4), 'mainSport'])
            ->first();

        // A real public athlete for the hero card, when one exists — the
        // page falls back to a clearly-labelled sample card otherwise.
        $heroAthlete = AthleteProfile::query()
            ->where('profile_visibility', 'public')
            ->whereNotNull('profile_photo_path')
            ->whereNotNull('bio')
            ->with(['user', 'mainSport'])
            ->latest('updated_at')
            ->first();

        // Public posts only — what a guest would see in Comunidad.
        $communityPosts = $moments->visibleTo($request->user())
            ->where('legacy_moments.visibility', 'public')
            ->orderByDesc('legacy_moments.id')
            ->limit(3)
            ->get();

        $legacyPlate = Product::query()
            ->where('type', ProductType::LegacyPlate)
            ->where('active', true)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->first(['slug']);

        return Inertia::render('Home', [
            'featuredEditions' => $featuredEditions->map(fn ($edition) => (new EventEditionCardResource($edition))->resolve()),
            'featuredProducts' => $featuredProducts->handle(5)->map(fn (Product $product) => GetFeaturedProducts::summarize($product)),
            'legacyProfile' => $legacyProfile ? [
                'username' => $legacyProfile->username,
                'name' => $legacyProfile->user->name,
                'city' => $legacyProfile->city,
                'country' => $legacyProfile->country,
                'sport' => $legacyProfile->mainSport?->name,
                'photo_url' => $legacyProfile->profile_photo_path ? asset('storage/'.$legacyProfile->profile_photo_path) : null,
                'medals_count' => $legacyProfile->user->medals()->count(),
                'medals' => $legacyProfile->user->medals->map(fn ($medal) => [
                    'title' => $medal->title,
                    'distance_label' => $medal->distance_label,
                ]),
            ] : null,
            'heroAthlete' => $heroAthlete && $heroAthlete->user ? [
                'username' => $heroAthlete->username,
                'name' => $heroAthlete->user->name,
                'sport' => $heroAthlete->mainSport?->name,
                'city' => $heroAthlete->city,
                'bio' => $heroAthlete->bio,
                'photo_url' => asset('storage/'.$heroAthlete->profile_photo_path),
            ] : null,
            'sports' => Sport::query()->where('active', true)->orderBy('sort_order')->get(['name', 'slug']),
            'communityPosts' => MomentResource::collection($communityPosts)->resolve($request),
            'legacyPlateSlug' => $legacyPlate?->slug,
        ]);
    }
}
