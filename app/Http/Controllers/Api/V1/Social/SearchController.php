<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AthleteResource;
use App\Http\Resources\Api\V1\ProductSummaryResource;
use App\Http\Resources\EventEditionCardResource;
use App\Models\AthleteProfile;
use App\Models\Product;
use App\Services\EventCatalogService;
use App\Services\Social\SocialVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `GET /search?q=&type=all|athletes|events|products` — one global search
 * for the app. Athletes respect privacy/blocks (SocialVisibility);
 * events/products only ever return published/active rows. Small fixed
 * result sets per type (it's a search box, not a listing).
 */
class SearchController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    private const PER_TYPE = 10;

    public function __invoke(Request $request, SocialVisibility $visibility, EventCatalogService $events): JsonResponse
    {
        $viewer = $this->sanctumUser($request);

        $data = $request->validate([
            'q' => ['required', 'string', 'min:'.config('finisher.social.search_min_length'), 'max:60'],
            'type' => ['nullable', Rule::in(['all', 'athletes', 'events', 'products'])],
        ], [
            'q.min' => 'Escribe al menos :min letras.',
        ]);

        $term = trim($data['q']);
        $type = $data['type'] ?? 'all';
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $following = $visibility->followingIds($viewer);

        $athletes = in_array($type, ['all', 'athletes'], true)
            ? $visibility->scopeDiscoverableProfiles(AthleteProfile::query(), $viewer)
                ->where('athlete_profiles.user_id', '!=', $viewer->id)
                ->where(fn (Builder $q) => $q
                    ->where('username', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)))
                ->with('user.athleteProfile')
                ->orderBy('username')
                ->limit(self::PER_TYPE)
                ->get()
                ->map(function (AthleteProfile $profile) use ($following) {
                    $profile->user->setAttribute('is_following', in_array($profile->user_id, $following, true));

                    return $profile->user;
                })
            : collect();

        $eventResults = in_array($type, ['all', 'events'], true)
            ? $events->publishedEditions(['q' => $term], perPage: self::PER_TYPE)->getCollection()
            : collect();

        $products = in_array($type, ['all', 'products'], true)
            ? Product::query()
                ->with(['category', 'variants.inventoryLevels', 'media'])
                ->where('active', true)
                ->where('status', 'active')
                ->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('brand', 'like', $like))
                ->orderBy('name')
                ->limit(self::PER_TYPE)
                ->get()
            : collect();

        return $this->respond([
            'query' => $term,
            'athletes' => AthleteResource::collection($athletes),
            'events' => EventEditionCardResource::collection($eventResults),
            'products' => ProductSummaryResource::collection($products),
        ]);
    }
}
