<?php

namespace App\Services\Commissions;

use App\Models\PhotoSale;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Admin reports over the frozen PhotoSale snapshots (never recomputed
 * from today's rate): per photographer and currency — gross, Finisher
 * commission, gateway fee, photographer net, refunds (reversed net and
 * commission) — and the payout-due summary:
 *
 *   due = Σ net of sales pending payout (not refunded) − Σ clawbacks
 *         (reversed net of refunded sales that were already paid out)
 *
 * All amounts are minor units. Optional date range filters on the sale
 * date (created_at = payment time).
 */
class PhotographerEarningsReport
{
    /**
     * @return list<array{photographer_profile_id: int, display_name: string, uuid: string, currency: string, sales_count: int, gross_minor: int, commission_minor: int, gateway_fee_minor: int, net_minor: int, refunded_count: int, reversed_net_minor: int, reversed_commission_minor: int, paid_out_minor: int, pending_minor: int, clawback_minor: int, due_minor: int, has_payout_data: bool}>
     */
    public function rows(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $rows = PhotoSale::query()
            ->join('photographer_profiles as pp', 'pp.id', '=', 'photo_sales.photographer_profile_id')
            ->when($from, fn ($q) => $q->where('photo_sales.created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('photo_sales.created_at', '<=', $to))
            ->groupBy('photo_sales.photographer_profile_id', 'pp.display_name', 'pp.uuid', 'photo_sales.currency')
            ->orderBy('pp.display_name')
            ->select([
                'photo_sales.photographer_profile_id',
                'pp.display_name',
                'pp.uuid',
                DB::raw('max(case when pp.payout_clabe is not null then 1 else 0 end) as has_payout_data'),
                'photo_sales.currency',
                DB::raw('count(*) as sales_count'),
                DB::raw('sum(gross_minor) as gross_minor'),
                DB::raw('sum(platform_fee_minor) as commission_minor'),
                DB::raw('sum(processor_fee_minor) as gateway_fee_minor'),
                DB::raw('sum(photographer_net_minor) as net_minor'),
                DB::raw('sum(case when refunded_at is not null then 1 else 0 end) as refunded_count'),
                DB::raw('sum(coalesce(reversed_net_minor, 0)) as reversed_net_minor'),
                DB::raw('sum(coalesce(reversed_commission_minor, 0)) as reversed_commission_minor'),
                DB::raw("sum(case when payout_status = 'paid' then photographer_net_minor else 0 end) as paid_out_minor"),
                DB::raw("sum(case when payout_status = 'pending' and refunded_at is null then photographer_net_minor else 0 end) as pending_minor"),
                DB::raw("sum(case when payout_status = 'paid' and refunded_at is not null and reversal_payout_id is null then coalesce(reversed_net_minor, 0) else 0 end) as clawback_minor"),
            ])
            ->toBase()
            ->get();

        return array_values($rows->map(function (object $row) {
            $pending = (int) $row->pending_minor;
            $clawback = (int) $row->clawback_minor;

            return [
                'photographer_profile_id' => (int) $row->photographer_profile_id,
                'display_name' => (string) $row->display_name,
                'uuid' => (string) $row->uuid,
                'currency' => (string) $row->currency,
                'sales_count' => (int) $row->sales_count,
                'gross_minor' => (int) $row->gross_minor,
                'commission_minor' => (int) $row->commission_minor,
                'gateway_fee_minor' => (int) $row->gateway_fee_minor,
                'net_minor' => (int) $row->net_minor,
                'refunded_count' => (int) $row->refunded_count,
                'reversed_net_minor' => (int) $row->reversed_net_minor,
                'reversed_commission_minor' => (int) $row->reversed_commission_minor,
                'paid_out_minor' => (int) $row->paid_out_minor,
                'pending_minor' => $pending,
                'clawback_minor' => $clawback,
                'due_minor' => $pending - $clawback,
                'has_payout_data' => (bool) $row->has_payout_data,
            ];
        })->all());
    }

    /**
     * Totals per currency over rows().
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array<string, int>>
     */
    public function totals(array $rows): array
    {
        $keys = ['sales_count', 'gross_minor', 'commission_minor', 'gateway_fee_minor', 'net_minor', 'reversed_net_minor',
            'reversed_commission_minor', 'paid_out_minor', 'pending_minor', 'clawback_minor', 'due_minor'];
        $totals = [];

        foreach ($rows as $row) {
            $currency = (string) $row['currency'];
            $totals[$currency] ??= array_fill_keys($keys, 0);

            foreach ($keys as $key) {
                $totals[$currency][$key] += (int) $row[$key];
            }
        }

        return $totals;
    }
}
