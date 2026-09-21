<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedalRequest;
use App\Http\Requests\UpdateMedalRequest;
use App\Http\Resources\Api\V1\MedalResource;
use App\Models\Medal;
use App\Services\MedalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MedalController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function __construct(private readonly MedalService $medals) {}

    public function index(Request $request): JsonResponse
    {
        $medals = $this->sanctumUser($request)->medals()
            ->with(['images', 'eventEdition.event', 'eventRace'])
            ->orderByDesc('event_date')
            ->paginate(20);

        return MedalResource::collection($medals)->response();
    }

    public function store(StoreMedalRequest $request): JsonResponse
    {
        $medal = $this->medals->create(
            $this->sanctumUser($request),
            $request->safe()->except(['front_image', 'back_image', 'gallery_images']),
            $request->file('front_image'),
            $request->file('back_image'),
            $request->file('gallery_images', []),
        );

        return (new MedalResource($medal->load(['eventEdition.event', 'eventRace'])))
            ->additional(['message' => 'Medalla agregada a tu colección.', 'meta' => (object) []])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Medal $medal): JsonResponse
    {
        Gate::forUser($this->sanctumUser($request))->authorize('view', $medal);

        $medal->load(['images', 'eventEdition.event', 'eventRace']);

        return (new MedalResource($medal))
            ->additional(['message' => null, 'meta' => (object) []])
            ->response();
    }

    public function update(UpdateMedalRequest $request, Medal $medal): JsonResponse
    {
        Gate::forUser($this->sanctumUser($request))->authorize('update', $medal);

        $updated = $this->medals->update(
            $medal,
            $request->safe()->except(['front_image', 'back_image', 'gallery_images']),
            $request->file('front_image'),
            $request->file('back_image'),
            $request->file('gallery_images', []),
        );

        return (new MedalResource($updated->load(['eventEdition.event', 'eventRace'])))
            ->additional(['message' => 'Medalla actualizada.', 'meta' => (object) []])
            ->response();
    }

    public function destroy(Request $request, Medal $medal): JsonResponse
    {
        Gate::forUser($this->sanctumUser($request))->authorize('delete', $medal);

        $this->medals->delete($medal);

        return response()->json([
            'data' => null,
            'message' => 'Medalla archivada de tu colección.',
            'meta' => (object) [],
        ]);
    }
}
