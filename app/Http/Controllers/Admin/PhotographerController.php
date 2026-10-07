<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventPhotoStatus;
use App\Enums\PhotographerStatus;
use App\Http\Controllers\Controller;
use App\Models\EventPhoto;
use App\Models\PhotographerPayout;
use App\Models\PhotographerProfile;
use App\Models\PhotoSale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Fotógrafos: list (filters + totals), detail (Perfil /
 * Fotografías / Ventas / Pagos), approve / suspend / reactivate, and
 * register payouts — each transfer made outside the system (to the
 * photographer's CLABE) becomes an auditable PhotographerPayout that
 * settles their pending sales. The CLABE is never shown in full.
 */
class PhotographerController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PhotographerStatus::class)],
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $profiles = $this->withTotals(PhotographerProfile::query())
            ->with('user')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when(trim((string) ($filters['q'] ?? '')), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('display_name', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$term}%"))))
            ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 else 2 end")
            ->latest('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (PhotographerProfile $p) => $this->row($p));

        return Inertia::render('admin/photographers/Index', [
            'photographers' => $profiles,
            'filters' => ['status' => $filters['status'] ?? null, 'q' => $filters['q'] ?? null],
            'counts' => PhotographerProfile::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'totals' => [
                'pending_applicants' => PhotographerProfile::query()->where('status', PhotographerStatus::Pending)->count(),
                'gross_minor' => (int) PhotoSale::query()->sum('gross_minor'),
                'platform_fee_minor' => (int) PhotoSale::query()->sum('platform_fee_minor'),
                'pending_payout_minor' => (int) PhotoSale::query()->where('payout_status', 'pending')->sum('photographer_net_minor'),
            ],
            'commissionPercent' => (float) config('finisher.photos.platform_commission_percent'),
        ]);
    }

    public function show(PhotographerProfile $photographer): Response
    {
        $profile = $this->withTotals(PhotographerProfile::query()->whereKey($photographer->id))->with('user')->firstOrFail();

        return Inertia::render('admin/photographers/Show', [
            'photographer' => [
                ...$this->row($profile),
                'bio' => $profile->bio,
                'phone' => $profile->phone,
                'instagram_url' => $profile->instagram_url,
                'portfolio_url' => $profile->portfolio_url,
                'approved_at' => $profile->approved_at?->toIso8601String(),
                'default_price_minor' => $profile->default_price_minor,
                'payout_holder' => $profile->payout_holder,
                'payout_bank' => $profile->payout_bank,
                'payout_clabe_masked' => $profile->maskedClabe(),
            ],
            'photos' => EventPhoto::query()
                ->where('photographer_profile_id', $profile->id)
                ->with('eventEdition.event')
                ->latest('id')
                ->paginate(24, ['*'], 'photos_page')
                ->through(fn (EventPhoto $photo) => [
                    'uuid' => $photo->uuid,
                    'thumb_url' => $photo->thumbUrl(),
                    'status' => $photo->status->value,
                    'event' => $photo->eventEdition?->event?->name,
                    'price_minor' => $photo->price_minor,
                    'currency' => $photo->currency,
                    'bib_numbers' => $photo->bib_numbers ?? [],
                ]),
            'sales' => PhotoSale::query()
                ->where('photographer_profile_id', $profile->id)
                ->with(['photo', 'order'])
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (PhotoSale $sale) => [
                    'uuid' => $sale->uuid,
                    'order_number' => $sale->order?->order_number,
                    'thumb_url' => $sale->photo?->thumbUrl(),
                    'gross_minor' => $sale->gross_minor,
                    'platform_fee_minor' => $sale->platform_fee_minor,
                    'processor_fee_minor' => $sale->processor_fee_minor,
                    'net_minor' => $sale->photographer_net_minor,
                    'currency' => $sale->currency,
                    'payment_provider' => $sale->payment_provider,
                    'payout_status' => $sale->payout_status,
                    'created_at' => $sale->created_at->toIso8601String(),
                ]),
            'payouts' => $profile->payouts()->with('recorder:id,name')->latest('paid_at')->get()
                ->map(fn (PhotographerPayout $payout) => [
                    'uuid' => $payout->uuid,
                    'amount_minor' => $payout->amount_minor,
                    'currency' => $payout->currency,
                    'sales_count' => $payout->sales_count,
                    'reference' => $payout->reference,
                    'notes' => $payout->notes,
                    'paid_at' => $payout->paid_at->toIso8601String(),
                    'recorded_by' => $payout->recorder?->name,
                ]),
        ]);
    }

    public function updateStatus(Request $request, PhotographerProfile $photographer): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(PhotographerStatus::class)],
        ]);

        $status = PhotographerStatus::from($data['status']);

        $photographer->update([
            'status' => $status,
            'approved_at' => $status === PhotographerStatus::Approved ? ($photographer->approved_at ?? now()) : $photographer->approved_at,
            'approved_by' => $status === PhotographerStatus::Approved ? $request->user()->id : $photographer->approved_by,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$photographer->display_name}: {$status->label()}."]);

        return back();
    }

    /**
     * Registers a transfer: settles every pending sale (per currency) and
     * records what it covered. Nothing pending = nothing recorded.
     */
    public function markPaid(Request $request, PhotographerProfile $photographer): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $payouts = DB::transaction(function () use ($data, $photographer, $request) {
            $pending = PhotoSale::query()
                ->where('photographer_profile_id', $photographer->id)
                ->where('payout_status', 'pending')
                ->lockForUpdate()
                ->get();

            return $pending->groupBy('currency')->map(function ($sales, $currency) use ($data, $photographer, $request) {
                $payout = PhotographerPayout::create([
                    'uuid' => (string) Str::uuid(),
                    'photographer_profile_id' => $photographer->id,
                    'amount_minor' => (int) $sales->sum('photographer_net_minor'),
                    'currency' => $currency,
                    'sales_count' => $sales->count(),
                    'reference' => $data['reference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'paid_at' => isset($data['paid_at']) ? now()->parse($data['paid_at']) : now(),
                    'recorded_by' => $request->user()->id,
                ]);

                PhotoSale::query()->whereIn('id', $sales->pluck('id'))->update([
                    'payout_status' => 'paid',
                    'paid_out_at' => $payout->paid_at,
                    'photographer_payout_id' => $payout->id,
                ]);

                return $payout;
            })->values();
        });

        $count = (int) $payouts->sum('sales_count');

        Inertia::flash('toast', ['type' => 'success', 'message' => $count > 0 ? "Pago registrado: {$count} ventas liquidadas." : 'No había ventas pendientes de pago.']);

        return back();
    }

    /**
     * @param  Builder<PhotographerProfile>  $query
     * @return Builder<PhotographerProfile>
     */
    private function withTotals(Builder $query): Builder
    {
        return $query
            ->withCount([
                'photos',
                'photos as published_count' => fn ($q) => $q->where('status', EventPhotoStatus::Published),
                'photos as review_count' => fn ($q) => $q->where('status', EventPhotoStatus::Review),
                'sales',
            ])
            ->withSum('sales as gross_minor', 'gross_minor')
            ->withSum('sales as platform_fee_minor', 'platform_fee_minor')
            ->withSum(['sales as pending_minor' => fn ($q) => $q->where('payout_status', 'pending')], 'photographer_net_minor')
            ->withSum(['sales as paid_minor' => fn ($q) => $q->where('payout_status', 'paid')], 'photographer_net_minor');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(PhotographerProfile $p): array
    {
        return [
            'id' => $p->id,
            'uuid' => $p->uuid,
            'display_name' => $p->display_name,
            'name' => $p->user?->name,
            'email' => $p->user?->email,
            'city' => $p->city,
            'status' => $p->status->value,
            'status_label' => $p->status->label(),
            'created_at' => $p->created_at?->toIso8601String(),
            'photos_count' => (int) $p->photos_count,
            'published_count' => (int) $p->getAttribute('published_count'),
            'review_count' => (int) $p->getAttribute('review_count'),
            'sales_count' => (int) $p->sales_count,
            'gross_minor' => (int) $p->getAttribute('gross_minor'),
            'platform_fee_minor' => (int) $p->getAttribute('platform_fee_minor'),
            'pending_minor' => (int) $p->getAttribute('pending_minor'),
            'paid_minor' => (int) $p->getAttribute('paid_minor'),
            'has_payout_data' => $p->payout_clabe !== null,
        ];
    }
}
