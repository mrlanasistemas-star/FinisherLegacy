<?php

namespace App\Http\Controllers;

use App\Enums\EditionStatus;
use App\Enums\EventStatus;
use App\Http\Resources\EventEditionCardResource;
use App\Models\AthleteProfile;
use App\Models\EventEdition;
use App\Models\Product;
use App\Queries\Commerce\GetFeaturedProducts;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(GetFeaturedProducts $featuredProducts): Response
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
        ]);
    }
}
