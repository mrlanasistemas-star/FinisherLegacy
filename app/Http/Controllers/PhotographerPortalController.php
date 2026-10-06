<?php

namespace App\Http\Controllers;

use App\Actions\Photos\UploadEventPhotos;
use App\Enums\EditionStatus;
use App\Enums\EventPhotoStatus;
use App\Models\EventEdition;
use App\Models\EventPhoto;
use App\Models\PhotographerProfile;
use App\Models\PhotoSale;
use App\Services\Photos\PhotoFeeCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal del fotógrafo: upload event photos (free), set prices, see every
 * sale with the exact split — what the buyer paid, what the card
 * processor kept, Finisher Legacy's commission and the photographer's net
 * — and what is still pending payout. Everything is scoped to the signed
 * in photographer's own profile.
 */
class PhotographerPortalController extends Controller
{
    public function __construct(private readonly PhotoFeeCalculator $fees) {}

    public function dashboard(Request $request): Response
    {
        $profile = $this->profile($request);

        if ($profile === null) {
            return Inertia::render('photographer/Dashboard', ['profile' => null, 'fees' => $this->fees->rules()]);
        }

        $sales = PhotoSale::query()->where('photographer_profile_id', $profile->id);
        $photos = EventPhoto::query()->where('photographer_profile_id', $profile->id);

        return Inertia::render('photographer/Dashboard', [
            'profile' => $this->profilePayload($profile),
            'fees' => $this->fees->rules(),
            'stats' => [
                'published' => (clone $photos)->where('status', EventPhotoStatus::Published)->count(),
                'review' => (clone $photos)->where('status', EventPhotoStatus::Review)->count(),
                'rejected' => (clone $photos)->where('status', EventPhotoStatus::Rejected)->count(),
                'sales' => (clone $sales)->count(),
                'gross_minor' => (int) (clone $sales)->sum('gross_minor'),
                'platform_fee_minor' => (int) (clone $sales)->sum('platform_fee_minor'),
                'processor_fee_minor' => (int) (clone $sales)->sum('processor_fee_minor'),
                'net_minor' => (int) (clone $sales)->sum('photographer_net_minor'),
                'pending_payout_minor' => (int) (clone $sales)->where('payout_status', 'pending')->sum('photographer_net_minor'),
                'paid_out_minor' => (int) (clone $sales)->where('payout_status', 'paid')->sum('photographer_net_minor'),
            ],
            'monthly' => $this->monthly($profile),
            'recentSales' => $this->salesQuery($profile)->limit(8)->get()->map(fn (PhotoSale $sale) => $this->salePayload($sale)),
        ]);
    }

    public function photos(Request $request): Response
    {
        $profile = $this->requireProfile($request);
        $editionId = $request->integer('evento') ?: null;
        $status = EventPhotoStatus::tryFrom($request->string('estado')->toString());

        $photos = EventPhoto::query()
            ->where('photographer_profile_id', $profile->id)
            ->when($editionId, fn ($q) => $q->where('event_edition_id', $editionId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with('eventEdition.event')
            ->withCount('sales')
            ->latest('id')
            ->paginate(48)
            ->withQueryString()
            ->through(fn (EventPhoto $photo) => [
                'uuid' => $photo->uuid,
                'thumb_url' => $photo->thumbUrl(),
                'preview_url' => $photo->previewUrl(),
                'event' => $photo->eventEdition?->event?->name,
                'event_edition_id' => $photo->event_edition_id,
                'price_minor' => $photo->price_minor,
                'currency' => $photo->currency,
                'status' => $photo->status->value,
                'rejection_reason' => $photo->rejection_reason,
                'bib_numbers' => $photo->bib_numbers ?? [],
                'sales_count' => $photo->sales_count,
                'net_minor' => $this->fees->split($photo->price_minor)['photographer_net_minor'],
            ]);

        return Inertia::render('photographer/Photos', [
            'profile' => $this->profilePayload($profile),
            'photos' => $photos,
            'events' => $this->eventOptions(),
            'filters' => ['evento' => $editionId, 'estado' => $status?->value],
            'fees' => $this->fees->rules(),
            'limits' => [
                'max_files' => (int) config('finisher.photos.max_files_per_upload'),
                'max_file_kb' => (int) config('finisher.photos.max_file_kb'),
            ],
        ]);
    }

    public function upload(Request $request, UploadEventPhotos $upload): RedirectResponse
    {
        $profile = $this->requireProfile($request);
        abort_unless($profile->isApproved(), 403, 'Tu perfil de fotógrafo aún no está aprobado.');

        $rules = $this->fees->rules();
        $data = $request->validate([
            'event_edition_id' => ['required', 'integer', 'exists:event_editions,id'],
            'price_minor' => ['required', 'integer', 'min:'.$rules['min_price_minor'], 'max:'.$rules['max_price_minor']],
            'bibs' => ['nullable', 'string', 'max:500'],
            'files' => ['required', 'array', 'min:1', 'max:'.config('finisher.photos.max_files_per_upload')],
            'files.*' => ['image', 'mimes:jpeg,png,webp', 'max:'.config('finisher.photos.max_file_kb')],
        ], [
            'files.required' => 'Selecciona al menos una fotografía.',
            'files.*.max' => 'Una de las fotos supera el tamaño máximo.',
            'price_minor.min' => 'El precio mínimo por foto es de $'.number_format($rules['min_price_minor'] / 100, 2).'.',
        ]);

        $edition = EventEdition::query()->findOrFail($data['event_edition_id']);
        $bibs = preg_split('/[\s,;]+/', (string) ($data['bibs'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $created = $upload->handle($profile, $edition, array_values($request->file('files')), (int) $data['price_minor'], $bibs);

        if ($profile->default_price_minor !== (int) $data['price_minor']) {
            $profile->update(['default_price_minor' => (int) $data['price_minor']]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => count($created).' fotos enviadas a revisión.']);

        return back();
    }

    public function updatePhoto(Request $request, EventPhoto $photo): RedirectResponse
    {
        $profile = $this->requireProfile($request);
        abort_unless($photo->photographer_profile_id === $profile->id, 404);

        $rules = $this->fees->rules();
        $data = $request->validate([
            'price_minor' => ['required', 'integer', 'min:'.$rules['min_price_minor'], 'max:'.$rules['max_price_minor']],
            'bib_numbers' => ['nullable', 'array', 'max:30'],
            'bib_numbers.*' => ['string', 'max:20'],
        ]);

        $photo->update(['price_minor' => $data['price_minor']]);
        $photo->syncBibs($data['bib_numbers'] ?? []);

        return back();
    }

    public function destroyPhoto(Request $request, EventPhoto $photo): RedirectResponse
    {
        $profile = $this->requireProfile($request);
        abort_unless($photo->photographer_profile_id === $profile->id, 404);

        if ($photo->sales()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Esta foto ya se vendió: no puede eliminarse (los compradores conservan su descarga).']);

            return back();
        }

        Storage::disk('event_photo_originals')->delete($photo->original_path);
        Storage::disk('public')->delete([$photo->preview_path, $photo->thumb_path]);
        $photo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Foto eliminada.']);

        return back();
    }

    public function sales(Request $request): Response
    {
        $profile = $this->requireProfile($request);

        return Inertia::render('photographer/Sales', [
            'profile' => $this->profilePayload($profile),
            'sales' => $this->salesQuery($profile)->paginate(30)->through(fn (PhotoSale $sale) => $this->salePayload($sale)),
            'fees' => $this->fees->rules(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $profile = $this->requireProfile($request);

        $profile->update($request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
            'payout_holder' => ['nullable', 'string', 'max:150'],
            'payout_bank' => ['nullable', 'string', 'max:120'],
            'payout_clabe' => ['nullable', 'digits:18'],
        ], ['payout_clabe.digits' => 'La CLABE debe tener 18 dígitos.']));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perfil actualizado.']);

        return back();
    }

    private function profile(Request $request): ?PhotographerProfile
    {
        return $request->user()->photographerProfile()->first();
    }

    private function requireProfile(Request $request): PhotographerProfile
    {
        $profile = $this->profile($request);
        abort_if($profile === null, 404);

        return $profile;
    }

    /** @return Builder<PhotoSale> */
    private function salesQuery(PhotographerProfile $profile)
    {
        return PhotoSale::query()
            ->where('photographer_profile_id', $profile->id)
            ->with('photo.eventEdition.event')
            ->latest('id');
    }

    /**
     * Last 6 months of net earnings, oldest first (for the chart).
     *
     * @return list<array{month: string, net_minor: int, sales: int}>
     */
    private function monthly(PhotographerProfile $profile): array
    {
        $rows = PhotoSale::query()
            ->where('photographer_profile_id', $profile->id)
            ->where('created_at', '>=', now()->startOfMonth()->subMonths(5))
            ->get(['created_at', 'photographer_net_minor'])
            ->groupBy(fn (PhotoSale $s) => $s->created_at->format('Y-m'));

        $out = [];
        for ($i = 5; $i >= 0; $i--) {
            $key = now()->startOfMonth()->subMonths($i)->format('Y-m');
            $out[] = [
                'month' => $key,
                'net_minor' => (int) ($rows[$key] ?? collect())->sum('photographer_net_minor'),
                'sales' => ($rows[$key] ?? collect())->count(),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(PhotographerProfile $profile): array
    {
        return [
            'uuid' => $profile->uuid,
            'display_name' => $profile->display_name,
            'slug' => $profile->slug,
            'bio' => $profile->bio,
            'city' => $profile->city,
            'phone' => $profile->phone,
            'instagram_url' => $profile->instagram_url,
            'portfolio_url' => $profile->portfolio_url,
            'status' => $profile->status->value,
            'status_label' => $profile->status->label(),
            'default_price_minor' => $profile->default_price_minor ?? (int) config('finisher.photos.default_price_minor'),
            'payout_holder' => $profile->payout_holder,
            'payout_bank' => $profile->payout_bank,
            'payout_clabe_masked' => $profile->maskedClabe(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salePayload(PhotoSale $sale): array
    {
        return [
            'uuid' => $sale->uuid,
            'thumb_url' => $sale->photo?->thumbUrl(),
            'event' => $sale->photo?->eventEdition?->event?->name,
            'gross_minor' => $sale->gross_minor,
            'processor_fee_minor' => $sale->processor_fee_minor,
            'platform_fee_minor' => $sale->platform_fee_minor,
            'net_minor' => $sale->photographer_net_minor,
            'commission_percent' => (float) $sale->commission_percent,
            'currency' => $sale->currency,
            'payout_status' => $sale->payout_status,
            'created_at' => $sale->created_at->toIso8601String(),
        ];
    }

    /**
     * @return list<array{id: int, label: string, date: string|null}>
     */
    private function eventOptions(): array
    {
        return EventEdition::query()
            ->where('status', EditionStatus::Published)
            ->with('event:id,name')
            ->orderByDesc('event_date')
            ->limit(150)
            ->get()
            ->map(fn (EventEdition $edition) => [
                'id' => $edition->id,
                'label' => trim(($edition->event?->name ?? 'Evento').' — '.$edition->name),
                'date' => $edition->event_date?->toDateString(),
            ])
            ->values()
            ->all();
    }
}
