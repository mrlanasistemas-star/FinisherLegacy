<?php

namespace App\Services\Commissions;

use App\Enums\PhotographerStatus;
use App\Models\PhotographerCommissionSetting;
use App\Models\PhotographerProfile;
use App\Models\User;
use App\Notifications\PhotographerCommissionChanged;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Writes photographer commission terms (admin only, gated by
 * `photographers.commissions.manage`). Every change is a NEW row — the
 * table is the history — audit-logged (activity log `commissions`) and,
 * when it changes what a photographer earns, notified to them with the
 * effective date. Effective dates are today (= right now) or a future
 * day (from 00:00 app time); never in the past, so no sale already made
 * could have been "under" a rate that did not exist yet. Past PhotoSales
 * are never recalculated.
 */
class CommissionSettingsManager
{
    public function __construct(private readonly CommissionResolver $resolver) {}

    /**
     * @param  array{commission_percent: float|int|string, gateway_fee_percent?: float|int|string|null, gateway_fee_fixed_minor?: int|string|null, gateway_fee_absorbed_by: string, effective_from?: string|null, notes?: string|null}  $data
     */
    public function scheduleGlobal(array $data, User $by): PhotographerCommissionSetting
    {
        $effectiveFrom = $this->effectiveFrom($data['effective_from'] ?? null);
        $before = $this->resolver->globalAt($effectiveFrom);
        $previousTerms = $this->resolver->termsFor(null, $effectiveFrom);

        $setting = PhotographerCommissionSetting::create([
            'uuid' => (string) Str::uuid(),
            'scope' => PhotographerCommissionSetting::SCOPE_GLOBAL,
            'commission_percent' => round((float) $data['commission_percent'], 2),
            'gateway_fee_percent' => $this->nullableFloat($data['gateway_fee_percent'] ?? null),
            'gateway_fee_fixed_minor' => $this->nullableInt($data['gateway_fee_fixed_minor'] ?? null),
            'gateway_fee_absorbed_by' => $data['gateway_fee_absorbed_by'],
            'effective_from' => $effectiveFrom,
            'notes' => $data['notes'] ?? null,
            'created_by' => $by->id,
        ]);

        $this->audit($by, $setting, $before, 'Programó la comisión global de fotógrafos');

        $rateChanged = abs($previousTerms->commissionPercent - (float) $setting->commission_percent) > 0.0001;
        $absorberChanged = $previousTerms->gatewayFeeAbsorbedBy !== $setting->gateway_fee_absorbed_by;

        if ($rateChanged || $absorberChanged) {
            $this->notifyGlobalChange($setting, $previousTerms->commissionPercent);
        }

        return $setting;
    }

    /**
     * A null percent removes the override from the effective date (the
     * photographer goes back to the global rate).
     */
    public function scheduleOverride(PhotographerProfile $profile, float|int|string|null $percent, ?string $effectiveFrom, ?string $notes, User $by): PhotographerCommissionSetting
    {
        $effectiveFrom = $this->effectiveFrom($effectiveFrom);
        $before = $this->resolver->overrideAt($profile->id, $effectiveFrom);
        $previousTerms = $this->resolver->termsFor($profile, $effectiveFrom);

        $setting = PhotographerCommissionSetting::create([
            'uuid' => (string) Str::uuid(),
            'scope' => PhotographerCommissionSetting::SCOPE_PHOTOGRAPHER,
            'photographer_profile_id' => $profile->id,
            'commission_percent' => $percent === null ? null : round((float) $percent, 2),
            'effective_from' => $effectiveFrom,
            'notes' => $notes,
            'created_by' => $by->id,
        ]);

        $this->audit($by, $setting, $before, $percent === null
            ? "Retiró la comisión personalizada de {$profile->display_name}"
            : "Programó una comisión personalizada para {$profile->display_name}");

        $newTerms = $this->resolver->termsFor($profile, $effectiveFrom);

        if (abs($newTerms->commissionPercent - $previousTerms->commissionPercent) > 0.0001 && $profile->user !== null) {
            $profile->user->notify(new PhotographerCommissionChanged(
                $newTerms->commissionPercent,
                $previousTerms->commissionPercent,
                $effectiveFrom,
                $percent === null ? PhotographerCommissionSetting::SCOPE_GLOBAL : PhotographerCommissionSetting::SCOPE_PHOTOGRAPHER,
                $newTerms->gatewayFeeAbsorbedBy,
            ));
        }

        return $setting;
    }

    /**
     * Only a change that has not taken effect yet can be cancelled; one
     * already in force is history and is superseded by a new row instead.
     */
    public function cancel(PhotographerCommissionSetting $setting, User $by): void
    {
        DB::transaction(function () use ($setting, $by) {
            $locked = PhotographerCommissionSetting::query()->whereKey($setting->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw ValidationException::withMessages([
                    'setting' => 'Solo se puede cancelar un cambio que aún no entra en vigor.',
                ]);
            }

            $locked->update(['cancelled_at' => now(), 'cancelled_by' => $by->id]);

            activity('commissions')
                ->causedBy($by)
                ->performedOn($locked)
                ->event('updated')
                ->withProperties(['attributes' => $this->snapshot($locked)])
                ->log('Canceló un cambio programado de comisión de fotógrafos');
        });
    }

    private function effectiveFrom(?string $date): CarbonInterface
    {
        if ($date === null || $date === '') {
            return now();
        }

        $day = Date::parse($date)->startOfDay();

        if ($day->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                'effective_from' => 'La fecha de vigencia no puede estar en el pasado.',
            ]);
        }

        return $day->isToday() ? now() : $day;
    }

    private function notifyGlobalChange(PhotographerCommissionSetting $setting, float $previousPercent): void
    {
        $at = $setting->effective_from;

        PhotographerProfile::query()
            ->where('status', PhotographerStatus::Approved)
            ->with('user')
            ->chunkById(200, function ($profiles) use ($at, $setting, $previousPercent) {
                $recipients = $profiles
                    // Photographers with their own override at that date keep
                    // their rate; only the gateway terms would reach them.
                    ->filter(fn (PhotographerProfile $p) => $p->user !== null
                        && $this->resolver->termsFor($p, $at)->source !== CommissionTerms::SOURCE_OVERRIDE)
                    ->map(fn (PhotographerProfile $p) => $p->user);

                if ($recipients->isNotEmpty()) {
                    Notification::send($recipients, new PhotographerCommissionChanged(
                        (float) $setting->commission_percent,
                        $previousPercent,
                        $at,
                        PhotographerCommissionSetting::SCOPE_GLOBAL,
                        (string) $setting->gateway_fee_absorbed_by,
                    ));
                }
            });
    }

    private function audit(User $by, PhotographerCommissionSetting $setting, ?PhotographerCommissionSetting $before, string $message): void
    {
        activity('commissions')
            ->causedBy($by)
            ->performedOn($setting)
            ->event('created')
            ->withProperties([
                'old' => $before !== null ? $this->snapshot($before) : [],
                'attributes' => $this->snapshot($setting),
            ])
            ->log($message);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(PhotographerCommissionSetting $setting): array
    {
        return [
            'scope' => $setting->scope,
            'photographer_profile_id' => $setting->photographer_profile_id,
            'commission_percent' => $setting->commission_percent,
            'gateway_fee_percent' => $setting->gateway_fee_percent,
            'gateway_fee_fixed_minor' => $setting->gateway_fee_fixed_minor,
            'gateway_fee_absorbed_by' => $setting->gateway_fee_absorbed_by,
            'effective_from' => $setting->effective_from->toIso8601String(),
            'notes' => $setting->notes,
            'cancelled_at' => $setting->cancelled_at?->toIso8601String(),
        ];
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null || $value === '' ? null : round((float) $value, 3);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
