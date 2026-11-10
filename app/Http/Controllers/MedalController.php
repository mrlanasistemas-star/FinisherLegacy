<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMedalRequest;
use App\Http\Requests\UpdateMedalRequest;
use App\Models\EventEdition;
use App\Models\EventRace;
use App\Models\Medal;
use App\Models\MedalImage;
use App\Services\MedalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MedalController extends Controller
{
    public function __construct(private readonly MedalService $medals) {}

    public function index(Request $request): Response
    {
        $medals = $request->user()->medals()
            ->with('images')
            ->orderByDesc('event_date')
            ->get();

        return Inertia::render('dashboard/medals/Index', [
            'medals' => $medals->map(fn (Medal $medal) => $this->cardPayload($medal)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('dashboard/medals/Create');
    }

    public function store(StoreMedalRequest $request): RedirectResponse
    {
        $medal = $this->medals->create(
            $request->user(),
            $request->safe()->except(['front_image', 'back_image', 'gallery_images']),
            $request->file('front_image'),
            $request->file('back_image'),
            $request->file('gallery_images', []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Medalla agregada a tu colección.')]);

        return to_route('dashboard.medals.show', $medal);
    }

    public function show(Medal $medal): Response
    {
        $this->authorize('view', $medal);

        $medal->load(['images', 'eventEdition.event', 'eventRace']);

        return Inertia::render('dashboard/medals/Show', [
            'medal' => $this->detailPayload($medal),
        ]);
    }

    public function edit(Medal $medal): Response
    {
        $this->authorize('update', $medal);

        $medal->load('images');

        return Inertia::render('dashboard/medals/Edit', [
            'medal' => $this->detailPayload($medal),
        ]);
    }

    public function update(UpdateMedalRequest $request, Medal $medal): RedirectResponse
    {
        $this->authorize('update', $medal);

        $this->medals->update(
            $medal,
            $request->safe()->except(['front_image', 'back_image', 'gallery_images']),
            $request->file('front_image'),
            $request->file('back_image'),
            $request->file('gallery_images', []),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Medalla actualizada.')]);

        return to_route('dashboard.medals.show', $medal);
    }

    public function destroy(Medal $medal): RedirectResponse
    {
        $this->authorize('delete', $medal);

        $this->medals->delete($medal);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Medalla archivada de tu colección.')]);

        return to_route('dashboard.medals.index');
    }

    public function destroyGalleryImage(Medal $medal, MedalImage $medalImage): RedirectResponse
    {
        $this->authorize('update', $medal);

        abort_unless($medalImage->medal_id === $medal->id, 404);

        $this->medals->removeGalleryImage($medal, $medalImage->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Imagen eliminada.')]);

        return back();
    }

    /**
     * Lightweight search used by the "origin" step of the medal wizard to find
     * a published event/edition/race already in Finisher Legacy.
     */
    public function searchEvents(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $editions = EventEdition::query()
            ->whereHas('event', fn ($query) => $query->where('status', 'published'))
            ->where('status', 'published')
            ->when(
                $search !== '',
                fn ($query) => $query->whereHas('event', fn ($eventQuery) => $eventQuery->where('name', 'like', "%{$search}%"))
            )
            ->with(['event', 'races' => fn ($query) => $query->where('active', true)])
            ->orderByDesc('event_date')
            ->limit(15)
            ->get();

        return response()->json($editions->map(fn (EventEdition $edition) => [
            'event_id' => $edition->event_id,
            'event_edition_id' => $edition->id,
            'name' => $edition->event->name,
            'year' => $edition->year,
            'event_date' => $edition->event_date->toDateString(),
            'city' => $edition->city,
            'country' => $edition->country,
            'races' => $edition->races->map(fn (EventRace $race) => ['id' => $race->id, 'name' => $race->name]),
        ]));
    }

    /**
     * Checks whether the current athlete already has a verified result for the
     * chosen race, so the wizard can prefill (and lock) the official result.
     */
    public function matchParticipant(Request $request): JsonResponse
    {
        $request->validate([
            'event_race_id' => ['required', 'integer', 'exists:event_races,id'],
        ]);

        $match = $this->medals->matchParticipant($request->user(), (int) $request->integer('event_race_id'));

        if (! $match) {
            return response()->json(['matched' => false]);
        }

        return response()->json([
            'matched' => true,
            'official_time' => $match->result->official_time,
            'pace' => $match->result->pace,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cardPayload(Medal $medal): array
    {
        $frontImage = $medal->images->firstWhere('type', 'front');

        return [
            'id' => $medal->id,
            'title' => $medal->title,
            'distance_label' => $medal->distance_label,
            'event_date' => $medal->event_date?->toDateString(),
            'visibility' => $medal->visibility->value,
            'thumbnail_url' => $frontImage?->thumbnail_path
                ? Storage::disk('public')->url($frontImage->thumbnail_path)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(Medal $medal): array
    {
        $frontImage = $medal->images->firstWhere('type', 'front');
        $backImage = $medal->images->firstWhere('type', 'back');
        $galleryImages = $medal->images->where('type', 'gallery')->sortBy('sort_order')->values();
        $plate = $medal->plates()->first();
        $legacyCode = $medal->legacyCodes()->first();

        return [
            'id' => $medal->id,
            'title' => $medal->title,
            'event_name_manual' => $medal->event_name_manual,
            'event_date' => $medal->event_date?->toDateString(),
            'distance_label' => $medal->distance_label,
            'official_time' => $medal->official_time,
            'pace' => $medal->pace,
            'city' => $medal->city,
            'country' => $medal->country,
            'story' => $medal->story,
            'visibility' => $medal->visibility->value,
            'is_official' => $medal->event_participant_id !== null,
            'event_name' => $medal->eventEdition?->event?->name,
            'race_name' => $medal->eventRace?->name,
            'front_image_url' => $frontImage?->optimized_path
                ? Storage::disk('public')->url($frontImage->optimized_path)
                : null,
            'back_image_url' => $backImage?->optimized_path
                ? Storage::disk('public')->url($backImage->optimized_path)
                : null,
            'gallery_images' => $galleryImages->map(fn (MedalImage $image) => [
                'id' => $image->id,
                'url' => $image->optimized_path ? Storage::disk('public')->url($image->optimized_path) : null,
            ])->values(),
            'gallery_slots_remaining' => max(0, config('finisher.medal.gallery.max_files') - $galleryImages->count()),
            'plate' => $plate ? [
                'serial_number' => $plate->serial_number,
                'status' => $plate->status->value,
            ] : null,
            'legacy_code' => $legacyCode ? [
                'code' => $legacyCode->code,
                'status' => $legacyCode->status->value,
            ] : null,
        ];
    }
}
