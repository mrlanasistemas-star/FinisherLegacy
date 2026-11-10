<?php

namespace App\Actions\Photos;

use App\Models\PhotographerPayout;
use App\Models\PhotographerProfile;
use App\Models\PhotoSale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registers a transfer made to a photographer (outside the system, to
 * their CLABE). Per currency it settles every sale whose NET is due and
 * deducts every clawback (refunded sale already paid out):
 *
 *   amount = Σ photographer_net_minor (payout due) − Σ reversed_net_minor (clawbacks)
 *
 * Totals always use the frozen net, never the gross. Refunded sales that
 * were never paid out are excluded (payout_status = reversed). If the
 * clawbacks exceed what is due, nothing is recorded: the balance is
 * negative and waits for new sales.
 */
class RegisterPhotographerPayout
{
    /**
     * @param  array{reference?: string|null, notes?: string|null, paid_at?: string|null}  $data
     * @return Collection<int, PhotographerPayout>
     */
    public function handle(PhotographerProfile $photographer, array $data, User $by): Collection
    {
        return DB::transaction(function () use ($photographer, $data, $by) {
            $due = PhotoSale::query()
                ->where('photographer_profile_id', $photographer->id)
                ->payoutDue()
                ->lockForUpdate()
                ->get();
            $clawbacks = PhotoSale::query()
                ->where('photographer_profile_id', $photographer->id)
                ->clawbackDue()
                ->lockForUpdate()
                ->get();

            $currencies = $due->pluck('currency')->merge($clawbacks->pluck('currency'))->unique();

            return $currencies->map(function (string $currency) use ($due, $clawbacks, $photographer, $data, $by) {
                $sales = $due->where('currency', $currency);
                $reversals = $clawbacks->where('currency', $currency);
                $net = (int) $sales->sum('photographer_net_minor');
                $reversed = (int) $reversals->sum('reversed_net_minor');

                if ($sales->isEmpty() || $net - $reversed < 0) {
                    throw ValidationException::withMessages([
                        'payout' => 'El saldo en '.$currency.' es negativo o no hay ventas por pagar: las devoluciones ('
                            .number_format($reversed / 100, 2).') superan lo pendiente ('.number_format($net / 100, 2).').',
                    ]);
                }

                $payout = PhotographerPayout::create([
                    'uuid' => (string) Str::uuid(),
                    'photographer_profile_id' => $photographer->id,
                    'amount_minor' => $net - $reversed,
                    'currency' => $currency,
                    'sales_count' => $sales->count(),
                    'reversals_minor' => $reversed,
                    'reversals_count' => $reversals->count(),
                    'reference' => $data['reference'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'paid_at' => isset($data['paid_at']) ? now()->parse($data['paid_at']) : now(),
                    'recorded_by' => $by->id,
                ]);

                PhotoSale::query()->whereIn('id', $sales->pluck('id'))->update([
                    'payout_status' => PhotoSale::PAYOUT_PAID,
                    'paid_out_at' => $payout->paid_at,
                    'photographer_payout_id' => $payout->id,
                ]);

                if ($reversals->isNotEmpty()) {
                    PhotoSale::query()->whereIn('id', $reversals->pluck('id'))->update([
                        'reversal_payout_id' => $payout->id,
                    ]);
                }

                activity('commissions')
                    ->causedBy($by)
                    ->performedOn($payout)
                    ->event('created')
                    ->withProperties([
                        'amount_minor' => $payout->amount_minor,
                        'sales_count' => $payout->sales_count,
                        'reversals_minor' => $reversed,
                        'currency' => $currency,
                    ])
                    ->log("Registró un pago a {$photographer->display_name}");

                return $payout;
            })->values();
        });
    }
}
