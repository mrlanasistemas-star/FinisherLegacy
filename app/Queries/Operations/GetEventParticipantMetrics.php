<?php

namespace App\Queries\Operations;

use App\Models\EventEdition;
use App\Models\OrderItem;

/**
 * The KPI row above the Participants V2 table (product UX consolidation
 * brief §30). Product unit counts and revenue come from OrderItems on
 * *paid* Orders only (brief §65: "no contar carrito") — never from cart
 * contents, never from all Orders regardless of payment status.
 */
class GetEventParticipantMetrics
{
    private const TRACKED_PRODUCTS = [
        'trisuit' => 'trisuits',
        'fast-t1-socks' => 'fast_t1_socks',
        'chill-band' => 'chill_band',
        'racepack' => 'racepack',
    ];

    /**
     * @return array<string, mixed>
     */
    public function handle(EventEdition $edition): array
    {
        $participants = $edition->participants();

        $paidItems = OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('event_edition_id', $edition->id)
                ->where('payment_status', 'paid'))
            ->with('product:id,slug')
            ->get();

        $unitsBySlug = $paidItems
            ->groupBy(fn (OrderItem $item) => $item->product->slug)
            ->map(fn ($items) => $items->sum('quantity'));

        $productCounts = [];
        foreach (self::TRACKED_PRODUCTS as $slug => $key) {
            $productCounts[$key] = (int) ($unitsBySlug[$slug] ?? 0);
        }

        $revenueMinor = $paidItems->sum('line_total_minor');

        return [
            'participants' => (clone $participants)->count(),
            'finishers' => (clone $participants)->whereHas('result', fn ($q) => $q->whereIn('status', ['finished', 'verified']))->count(),
            'legacy_plates_bought' => (clone $participants)->whereHas('legacyPlateEntitlements')->count(),
            'legacy_plates_paid' => (clone $participants)->whereHas(
                'legacyPlateEntitlements',
                fn ($q) => $q->whereIn('status', ['paid', 'linked', 'queued', 'produced', 'delivered']),
            )->count(),
            ...$productCounts,
            'revenue_minor' => $revenueMinor,
        ];
    }
}
