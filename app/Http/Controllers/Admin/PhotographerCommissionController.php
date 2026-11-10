<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Photos\ReversePhotoSale;
use App\Enums\PhotographerStatus;
use App\Http\Controllers\Controller;
use App\Models\PhotographerCommissionSetting;
use App\Models\PhotographerProfile;
use App\Models\PhotoSale;
use App\Services\Commissions\CommissionResolver;
use App\Services\Commissions\CommissionSettingsManager;
use App\Services\Commissions\PhotographerEarningsReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Fotógrafos → Comisiones (permission
 * `photographers.commissions.manage`): the global Finisher Legacy
 * commission, the OpenPay gateway fee and who absorbs it, per-photographer
 * overrides — all effective-dated, append-only and audit-logged — plus
 * the sales/commission/net report with the payout-due summary and the
 * manual reversal of a refunded sale.
 */
class PhotographerCommissionController extends Controller
{
    public function __construct(
        private readonly CommissionResolver $resolver,
        private readonly CommissionSettingsManager $manager,
    ) {}

    public function index(): Response
    {
        $terms = $this->resolver->termsFor(null, null, 'openpay');
        $global = $this->resolver->globalAt(now());

        return Inertia::render('admin/photographers/Commissions', [
            'current' => [
                'commission_percent' => $terms->commissionPercent,
                'source' => $terms->source,
                'effective_from' => $terms->effectiveFrom?->toIso8601String(),
                'gateway_fee_percent' => $global?->gateway_fee_percent !== null ? (float) $global->gateway_fee_percent : null,
                'gateway_fee_fixed_minor' => $global?->gateway_fee_fixed_minor,
                'gateway_fee_absorbed_by' => $terms->gatewayFeeAbsorbedBy,
                'gateway_fee_configured' => $terms->gatewayFeeConfigured,
                'effective_gateway_fee_percent' => $terms->gatewayFeePercent,
                'effective_gateway_fee_fixed_minor' => $terms->gatewayFeeFixedMinor,
                'gateway_fee_vat_percent' => $terms->gatewayFeeVatPercent,
            ],
            'upcoming' => $this->resolver->upcomingFor(null),
            'history' => PhotographerCommissionSetting::query()
                ->where('scope', PhotographerCommissionSetting::SCOPE_GLOBAL)
                ->with('author:id,first_name,last_name')
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (PhotographerCommissionSetting $s) => $this->settingPayload($s)),
            'overrides' => PhotographerCommissionSetting::query()
                ->where('scope', PhotographerCommissionSetting::SCOPE_PHOTOGRAPHER)
                ->with(['author:id,first_name,last_name', 'photographer:id,uuid,display_name'])
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn (PhotographerCommissionSetting $s) => [
                    ...$this->settingPayload($s),
                    'photographer' => $s->photographer ? ['uuid' => $s->photographer->uuid, 'display_name' => $s->photographer->display_name] : null,
                ]),
            'photographers' => PhotographerProfile::query()
                ->whereIn('status', [PhotographerStatus::Approved, PhotographerStatus::Pending])
                ->orderBy('display_name')
                ->get(['id', 'uuid', 'display_name'])
                ->map(function (PhotographerProfile $p) {
                    $terms = $this->resolver->termsFor($p);

                    return [
                        'uuid' => $p->uuid,
                        'display_name' => $p->display_name,
                        'commission_percent' => $terms->commissionPercent,
                        'has_override' => $terms->source === 'override',
                    ];
                }),
            'defaults' => [
                'commission_percent' => (float) config('finisher.photos.platform_commission_percent'),
                'gateway_fee_absorbed_by' => (string) config('finisher.photos.gateway_fee_absorbed_by'),
            ],
        ]);
    }

    public function storeGlobal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:90'],
            'gateway_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'gateway_fee_fixed_minor' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'gateway_fee_absorbed_by' => ['required', Rule::in([PhotographerCommissionSetting::ABSORBED_BY_PHOTOGRAPHER, PhotographerCommissionSetting::ABSORBED_BY_FINISHER])],
            'effective_from' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'effective_from.after_or_equal' => 'La fecha de vigencia no puede estar en el pasado.',
        ]);

        $setting = $this->manager->scheduleGlobal($data, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => $setting->effective_from->isFuture()
            ? 'Cambio programado a partir del '.$setting->effective_from->format('d/m/Y').'.'
            : 'Comisión global actualizada.']);

        return back();
    }

    public function storeOverride(Request $request, PhotographerProfile $photographer): RedirectResponse
    {
        $data = $request->validate([
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:90'],
            'effective_from' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'effective_from.after_or_equal' => 'La fecha de vigencia no puede estar en el pasado.',
        ]);

        $this->manager->scheduleOverride(
            $photographer,
            $data['commission_percent'] ?? null,
            $data['effective_from'] ?? null,
            $data['notes'] ?? null,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => ($data['commission_percent'] ?? null) === null
            ? "{$photographer->display_name} vuelve a la comisión global."
            : "Comisión personalizada guardada para {$photographer->display_name}."]);

        return back();
    }

    public function cancel(Request $request, PhotographerCommissionSetting $setting): RedirectResponse
    {
        $this->manager->cancel($setting, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cambio programado cancelado.']);

        return back();
    }

    public function report(Request $request, PhotographerEarningsReport $report): Response
    {
        $filters = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        $from = isset($filters['desde']) ? Carbon::parse($filters['desde'])->startOfDay() : null;
        $to = isset($filters['hasta']) ? Carbon::parse($filters['hasta'])->endOfDay() : null;
        $rows = $report->rows($from, $to);

        return Inertia::render('admin/photographers/CommissionReport', [
            'rows' => $rows,
            'totals' => $report->totals($rows),
            'filters' => ['desde' => $filters['desde'] ?? null, 'hasta' => $filters['hasta'] ?? null],
        ]);
    }

    public function reverseSale(Request $request, PhotoSale $sale, ReversePhotoSale $reverse): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], ['reason.required' => 'Indica el motivo (por ejemplo, el reembolso parcial del pedido).']);

        if ($sale->refunded_at !== null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Esta venta ya estaba revertida.']);

            return back();
        }

        $reverse->handle($sale, $data['reason'], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Venta revertida: la ganancia del fotógrafo se descuenta.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function settingPayload(PhotographerCommissionSetting $s): array
    {
        return [
            'uuid' => $s->uuid,
            'commission_percent' => $s->commission_percent !== null ? (float) $s->commission_percent : null,
            'gateway_fee_percent' => $s->gateway_fee_percent !== null ? (float) $s->gateway_fee_percent : null,
            'gateway_fee_fixed_minor' => $s->gateway_fee_fixed_minor,
            'gateway_fee_absorbed_by' => $s->gateway_fee_absorbed_by,
            'effective_from' => $s->effective_from->toIso8601String(),
            'notes' => $s->notes,
            'author' => $s->author?->name,
            'created_at' => $s->created_at->toIso8601String(),
            'cancelled_at' => $s->cancelled_at?->toIso8601String(),
            'pending' => $s->isPending(),
        ];
    }
}
