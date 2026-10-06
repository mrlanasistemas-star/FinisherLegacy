<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventPhotoStatus;
use App\Enums\PhotographerStatus;
use App\Http\Controllers\Controller;
use App\Models\PhotographerProfile;
use App\Models\PhotoSale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Fotógrafos: approve applicants, suspend, and settle
 * payouts (marks every pending sale of a photographer as paid, after the
 * transfer to their CLABE was made outside the system).
 */
class PhotographerController extends Controller
{
    public function index(): Response
    {
        $profiles = PhotographerProfile::query()
            ->with('user')
            ->withCount([
                'photos as published_count' => fn ($q) => $q->where('status', EventPhotoStatus::Published),
                'photos as review_count' => fn ($q) => $q->where('status', EventPhotoStatus::Review),
                'sales',
            ])
            ->withSum('sales as gross_minor', 'gross_minor')
            ->withSum('sales as platform_fee_minor', 'platform_fee_minor')
            ->withSum(['sales as pending_minor' => fn ($q) => $q->where('payout_status', 'pending')], 'photographer_net_minor')
            ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 else 2 end")
            ->latest('id')
            ->get();

        return Inertia::render('admin/photographers/Index', [
            'photographers' => $profiles->map(fn (PhotographerProfile $p) => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'display_name' => $p->display_name,
                'name' => $p->user?->name,
                'email' => $p->user?->email,
                'city' => $p->city,
                'phone' => $p->phone,
                'instagram_url' => $p->instagram_url,
                'portfolio_url' => $p->portfolio_url,
                'bio' => $p->bio,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'created_at' => $p->created_at?->toIso8601String(),
                'published_count' => (int) $p->published_count,
                'review_count' => (int) $p->review_count,
                'sales_count' => (int) $p->sales_count,
                'gross_minor' => (int) $p->gross_minor,
                'platform_fee_minor' => (int) $p->platform_fee_minor,
                'pending_minor' => (int) $p->pending_minor,
                'payout_holder' => $p->payout_holder,
                'payout_bank' => $p->payout_bank,
                'payout_clabe_masked' => $p->maskedClabe(),
            ]),
            'totals' => [
                'pending_applicants' => $profiles->where('status', PhotographerStatus::Pending)->count(),
                'gross_minor' => (int) PhotoSale::query()->sum('gross_minor'),
                'platform_fee_minor' => (int) PhotoSale::query()->sum('platform_fee_minor'),
                'pending_payout_minor' => (int) PhotoSale::query()->where('payout_status', 'pending')->sum('photographer_net_minor'),
            ],
            'commissionPercent' => (float) config('finisher.photos.platform_commission_percent'),
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

    public function markPaid(PhotographerProfile $photographer): RedirectResponse
    {
        $count = PhotoSale::query()
            ->where('photographer_profile_id', $photographer->id)
            ->where('payout_status', 'pending')
            ->update(['payout_status' => 'paid', 'paid_out_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $count > 0 ? "{$count} ventas marcadas como pagadas." : 'No había ventas pendientes de pago.']);

        return back();
    }
}
