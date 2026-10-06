<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AthleteEventMediaType;
use App\Enums\EventPhotoStatus;
use App\Http\Controllers\Controller;
use App\Models\AthleteEventMedia;
use App\Models\EventPhoto;
use App\Models\PhotoSale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Fotografías: the photographers' review board (Por
 * revisar → Publicadas / Rechazadas) plus sales figures and the athletes'
 * own event media overview.
 */
class EventPhotoController extends Controller
{
    public function index(): Response
    {
        $lane = fn (EventPhotoStatus $status) => [
            'key' => $status->value,
            'count' => EventPhoto::query()->where('status', $status)->count(),
            'items' => EventPhoto::query()
                ->where('status', $status)
                ->with(['photographer', 'eventEdition.event'])
                ->latest('id')
                ->limit(60)
                ->get()
                ->map(fn (EventPhoto $photo) => [
                    'uuid' => $photo->uuid,
                    'thumb_url' => $photo->thumbUrl(),
                    'preview_url' => $photo->previewUrl(),
                    'photographer' => $photo->photographer?->display_name,
                    'event' => $photo->eventEdition?->event?->name,
                    'price_minor' => $photo->price_minor,
                    'currency' => $photo->currency,
                    'bib_numbers' => $photo->bib_numbers ?? [],
                    'rejection_reason' => $photo->rejection_reason,
                ]),
        ];

        return Inertia::render('admin/photos/Index', [
            'board' => [
                $lane(EventPhotoStatus::Review),
                $lane(EventPhotoStatus::Published),
                $lane(EventPhotoStatus::Rejected),
            ],
            'sales' => [
                'count' => PhotoSale::query()->count(),
                'gross_minor' => (int) PhotoSale::query()->sum('gross_minor'),
                'platform_fee_minor' => (int) PhotoSale::query()->sum('platform_fee_minor'),
                'net_minor' => (int) PhotoSale::query()->sum('photographer_net_minor'),
            ],
            'stats' => [
                'images' => AthleteEventMedia::query()->where('type', AthleteEventMediaType::Image)->count(),
                'videos' => AthleteEventMedia::query()->where('type', AthleteEventMediaType::Video)->count(),
                'public' => AthleteEventMedia::query()->where('is_public', true)->count(),
                'private' => AthleteEventMedia::query()->where('is_public', false)->count(),
            ],
        ]);
    }

    public function review(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'uuids' => ['required', 'array', 'min:1', 'max:200'],
            'uuids.*' => ['string', 'exists:event_photos,uuid'],
            'action' => ['required', Rule::in(['publish', 'reject', 'review'])],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $status = match ($data['action']) {
            'publish' => EventPhotoStatus::Published,
            'reject' => EventPhotoStatus::Rejected,
            default => EventPhotoStatus::Review,
        };

        EventPhoto::query()->whereIn('uuid', $data['uuids'])->get()->each(fn (EventPhoto $photo) => $photo->update([
            'status' => $status,
            'published_at' => $status === EventPhotoStatus::Published ? ($photo->published_at ?? now()) : $photo->published_at,
            'rejection_reason' => $status === EventPhotoStatus::Rejected ? ($data['reason'] ?? 'No cumple con los lineamientos.') : null,
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => count($data['uuids']).' fotos: '.$status->label().'.']);

        return back();
    }
}
