<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AthleteEventMediaType;
use App\Enums\EventPhotoStatus;
use App\Http\Controllers\Controller;
use App\Models\AthleteEventMedia;
use App\Models\EventEdition;
use App\Models\EventPhoto;
use App\Models\PhotographerProfile;
use App\Models\PhotoSale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Fotografías: the photographers' review grid — status
 * tabs, filters (event, photographer, date, bib), pagination and bulk
 * approve / reject of up to 200 selected photos — plus sales figures and
 * the athletes' own event media overview.
 */
class EventPhotoController extends Controller
{
    private const PER_PAGE = [24, 50, 100];

    /**
     * Paginated, filterable review grid — never loads every photo at once.
     * Filters: status, event, photographer, upload date range and bib.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_column(EventPhotoStatus::cases(), 'value'))],
            'event_edition_id' => ['nullable', 'integer'],
            'photographer_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'bib' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', Rule::in(self::PER_PAGE)],
        ]);
        $status = $filters['status'] ?? EventPhotoStatus::Review->value;
        $perPage = (int) ($filters['per_page'] ?? 50);

        $base = EventPhoto::query()
            ->when($filters['event_edition_id'] ?? null, fn ($q, $id) => $q->where('event_edition_id', $id))
            ->when($filters['photographer_id'] ?? null, fn ($q, $id) => $q->where('photographer_profile_id', $id))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when(trim((string) ($filters['bib'] ?? '')), fn ($q, $bib) => $q->whereHas('bibs', fn ($b) => $b->where('bib_number', $bib)));

        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $photos = (clone $base)
            ->where('status', $status)
            ->with(['photographer', 'eventEdition.event'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (EventPhoto $photo) => [
                'uuid' => $photo->uuid,
                'thumb_url' => $photo->thumbUrl(),
                'preview_url' => $photo->previewUrl(),
                'photographer' => $photo->photographer?->display_name,
                'photographer_status' => $photo->photographer?->status->value,
                'event' => $photo->eventEdition?->event?->name,
                'price_minor' => $photo->price_minor,
                'currency' => $photo->currency,
                'bib_numbers' => $photo->bib_numbers ?? [],
                'rejection_reason' => $photo->rejection_reason,
                'uploaded_at' => $photo->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/photos/Index', [
            'photos' => $photos,
            'status' => $status,
            'counts' => collect(EventPhotoStatus::cases())->mapWithKeys(fn (EventPhotoStatus $s) => [$s->value => (int) ($counts[$s->value] ?? 0)]),
            'filters' => [
                'event_edition_id' => isset($filters['event_edition_id']) ? (int) $filters['event_edition_id'] : null,
                'photographer_id' => isset($filters['photographer_id']) ? (int) $filters['photographer_id'] : null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
                'bib' => $filters['bib'] ?? null,
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE,
            'events' => EventEdition::query()
                ->whereIn('id', EventPhoto::query()->select('event_edition_id')->distinct())
                ->with('event:id,name')
                ->orderByDesc('event_date')
                ->get(['id', 'event_id', 'name', 'event_date'])
                ->map(fn (EventEdition $e) => ['id' => $e->id, 'name' => trim($e->event->name.' · '.$e->name, ' ·')]),
            'photographers' => PhotographerProfile::query()->orderBy('display_name')->get(['id', 'display_name'])
                ->map(fn (PhotographerProfile $p) => ['id' => $p->id, 'name' => $p->display_name]),
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
