<?php

namespace App\Queries\Admin;

use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\ProductType;
use App\Enums\SalesChannel;
use App\Enums\SupportCaseType;
use App\Enums\TagAccessSource;
use App\Enums\TagScanEvent;
use App\Models\InventoryLevel;
use App\Models\LegacyTag;
use App\Models\LegacyTagEvent;
use App\Models\LegacyTagScan;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\SupportCase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Administración → Inicio: the Commerce + Activation OS numbers
 * (docs/architecture/commerce-os.md §Administración comercial).
 *
 * Every figure is a live aggregate. When there is nothing to measure the
 * value is `null` (rendered "—" / "Sin información todavía") — never a
 * zero pretending to be a rate, never a sample number. SQL is portable
 * (SQLite in tests, MySQL in production): no date arithmetic in SQL —
 * durations and medians are computed in PHP over bounded result sets.
 */
class CommerceDashboardMetrics
{
    public const RANGES = ['hoy', '7d', '30d', 'mes'];

    /** Upper bound of rows pulled into PHP for medians / windows. */
    private const SAMPLE_LIMIT = 20000;

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function window(string $range, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();

        $from = match ($range) {
            'hoy' => $now->startOfDay(),
            '7d' => $now->startOfDay()->subDays(6),
            'mes' => $now->startOfMonth(),
            default => $now->startOfDay()->subDays(29),
        };

        return [$from, $now];
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(string $range, ?CarbonImmutable $now = null): array
    {
        $range = in_array($range, self::RANGES, true) ? $range : '30d';
        [$from, $to] = self::window($range, $now);
        $currency = (string) config('finisher.commerce.default_currency', 'MXN');

        return [
            'range' => $range,
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'currency' => $currency,
            'sales' => $this->sales($from, $to, $currency),
            'channels' => $this->channels($from, $to, $currency),
            'inventory' => $this->inventory(),
            'activation' => $this->activation($from, $to, $now ?? CarbonImmutable::now()),
            'product' => $this->product($from, $to),
            'collections' => $this->collections($from, $to),
            'pos' => $this->pos($from, $to),
        ];
    }

    private function paidOrders(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return Order::query()
            ->toBase()
            ->whereIn('orders.payment_status', [OrderPaymentStatus::Paid->value, OrderPaymentStatus::PartiallyRefunded->value])
            ->where('orders.created_at', '>=', $from)
            ->where('orders.created_at', '<=', $to);
    }

    /** Physical units on paid orders (digital photos are not units). */
    private function physicalUnits(Builder $orders): Builder
    {
        return $orders
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereNull('order_items.event_photo_id')
            ->where('products.type', '!=', ProductType::DigitalPhoto->value);
    }

    /**
     * @return array<string, int|null>
     */
    private function sales(CarbonImmutable $from, CarbonImmutable $to, string $currency): array
    {
        $row = $this->paidOrders($from, $to)
            ->where('orders.currency', $currency)
            ->selectRaw('count(*) as orders, coalesce(sum(orders.total_minor), 0) as total')
            ->first();

        $orders = (int) ($row->orders ?? 0);
        $total = (int) ($row->total ?? 0);

        $units = (int) $this->physicalUnits($this->paidOrders($from, $to))->sum('order_items.quantity');

        return [
            'total_minor' => $orders > 0 ? $total : null,
            'orders' => $orders,
            'average_ticket_minor' => $orders > 0 ? intdiv($total, $orders) : null,
            'units' => $units,
            'other_currency_orders' => $this->paidOrders($from, $to)->where('orders.currency', '!=', $currency)->count(),
        ];
    }

    /**
     * @return list<array{channel: string, label: string, orders: int, total_minor: int, units: int, share: float|null}>
     */
    private function channels(CarbonImmutable $from, CarbonImmutable $to, string $currency): array
    {
        $totals = $this->paidOrders($from, $to)
            ->where('orders.currency', $currency)
            ->groupBy('orders.sales_channel')
            ->get(['orders.sales_channel', DB::raw('count(*) as orders'), DB::raw('sum(orders.total_minor) as total')])
            ->keyBy('sales_channel');

        $units = $this->physicalUnits($this->paidOrders($from, $to))
            ->groupBy('orders.sales_channel')
            ->get(['orders.sales_channel', DB::raw('sum(order_items.quantity) as units')])
            ->keyBy('sales_channel');

        $grand = (int) $totals->sum(fn (object $r) => (int) $r->total);

        return array_map(function (SalesChannel $channel) use ($totals, $units, $grand) {
            $total = (int) ($totals[$channel->value]->total ?? 0);

            return [
                'channel' => $channel->value,
                'label' => $channel->label(),
                'orders' => (int) ($totals[$channel->value]->orders ?? 0),
                'total_minor' => $total,
                'units' => (int) ($units[$channel->value]->units ?? 0),
                'share' => $grand > 0 ? round($total / $grand, 4) : null,
            ];
        }, SalesChannel::cases());
    }

    /**
     * @return array<string, mixed>
     */
    private function inventory(): array
    {
        $byStatus = LegacyTag::query()->toBase()
            ->whereIn('status', [LegacyTagStatus::InStock->value, LegacyTagStatus::Reserved->value])
            ->groupBy('status')
            ->pluck(DB::raw('count(*)'), 'status');

        $levels = InventoryLevel::query()->toBase()
            ->groupBy('product_variant_id')
            ->get(['product_variant_id', DB::raw('sum(quantity_on_hand) as on_hand'), DB::raw('sum(quantity_reserved) as reserved')])
            ->keyBy('product_variant_id');

        // Sellable physical SKUs only (inventory-tracked, not digital).
        $variants = ProductVariant::query()
            ->with('product:id,name,minimum_stock,type,tracks_inventory')
            ->where('active', true)
            ->whereHas('product', fn ($q) => $q->where('tracks_inventory', true)->where('type', '!=', ProductType::DigitalPhoto->value))
            ->limit(2000)
            ->get(['id', 'product_id', 'sku', 'name', 'minimum_stock']);

        $low = [];
        $soldOut = [];

        foreach ($variants as $variant) {
            $level = $levels->get($variant->id);
            $available = (int) ($level->on_hand ?? 0) - (int) ($level->reserved ?? 0);
            $row = [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->product?->name.($variant->name ? ' · '.$variant->name : ''),
                'available' => $available,
                'minimum' => $variant->minimumStock(),
            ];

            if ($available <= 0) {
                $soldOut[] = $row;
            } elseif ($available <= $row['minimum']) {
                $low[] = $row;
            }
        }

        usort($low, fn ($a, $b) => $a['available'] <=> $b['available']);

        return [
            'tracked_skus' => $variants->count(),
            'units_available' => (int) ($byStatus[LegacyTagStatus::InStock->value] ?? 0),
            'units_reserved' => (int) ($byStatus[LegacyTagStatus::Reserved->value] ?? 0),
            'low_stock_count' => count($low),
            'sold_out_count' => count($soldOut),
            'low_stock' => array_slice($low, 0, 8),
            'sold_out' => array_slice($soldOut, 0, 8),
        ];
    }

    /**
     * Cohort = tags sold in the window (replacements are not new sales).
     *
     * @return array<string, mixed>
     */
    private function activation(CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $now): array
    {
        $windowHours = (int) config('legacy.tags.kpi.activation_window_hours', 72);

        $cohort = LegacyTag::query()->toBase()
            ->whereNull('replaces_tag_id')
            ->whereNotNull('sold_at')
            ->where('sold_at', '>=', $from)
            ->where('sold_at', '<=', $to)
            ->limit(self::SAMPLE_LIMIT)
            ->get(['status', 'sold_at', 'activated_at']);

        $sold = $cohort->count();
        $activated = $cohort->filter(fn (object $t) => $t->activated_at !== null)->count();
        $pending = $cohort->filter(fn (object $t) => in_array($t->status, [LegacyTagStatus::Sold->value, LegacyTagStatus::Delivered->value], true))->count();

        $durations = $cohort
            ->filter(fn (object $t) => $t->activated_at !== null)
            ->map(fn (object $t) => max(0, CarbonImmutable::parse($t->activated_at)->getTimestamp() - CarbonImmutable::parse($t->sold_at)->getTimestamp()))
            ->values()->all();
        $durations = array_values($durations);

        // Only tags sold at least `windowHours` ago can be judged on the window.
        $matureCutoff = $now->subHours($windowHours)->getTimestamp();
        $mature = $cohort->filter(fn (object $t) => CarbonImmutable::parse($t->sold_at)->getTimestamp() <= $matureCutoff);
        $withinWindow = $mature->filter(fn (object $t) => $t->activated_at !== null
            && CarbonImmutable::parse($t->activated_at)->getTimestamp() - CarbonImmutable::parse($t->sold_at)->getTimestamp() <= $windowHours * 3600)->count();

        return [
            'sold' => $sold,
            'activated' => $activated,
            'pending' => $pending,
            'rate' => $sold > 0 ? round($activated / $sold, 4) : null,
            'window_hours' => $windowHours,
            'mature_sold' => $mature->count(),
            'within_window_rate' => $mature->count() > 0 ? round($withinWindow / $mature->count(), 4) : null,
            'median_seconds' => self::median($durations),
            'activations_in_range' => LegacyTag::query()->whereNotNull('activated_at')->whereBetween('activated_at', [$from, $to])->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function product(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $opens = LegacyTagScan::query()->toBase()
            ->where('event', TagScanEvent::TagOpened->value)
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('source')
            ->pluck(DB::raw('count(*)'), 'source');

        $events = LegacyTagEvent::query()->toBase()
            ->whereIn('type', [LegacyTagEventType::Returned->value, LegacyTagEventType::Replaced->value])
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('type')
            ->pluck(DB::raw('count(*)'), 'type');

        $cases = SupportCase::query()->whereBetween('created_at', [$from, $to]);

        return [
            'opens' => [
                'nfc' => (int) ($opens[TagAccessSource::Nfc->value] ?? 0),
                'code' => (int) ($opens[TagAccessSource::Code->value] ?? 0),
                // Old `?src=qr` links (the tag no longer has a printed QR) count as links.
                'link' => (int) ($opens[TagAccessSource::Link->value] ?? 0) + (int) ($opens[TagAccessSource::Qr->value] ?? 0),
            ],
            'opens_total' => (int) $opens->sum(),
            'tags_with_incidents' => (clone $cases)->whereNotNull('legacy_tag_id')->distinct()->count('legacy_tag_id'),
            'support_cases' => (clone $cases)->count(),
            'defects' => (clone $cases)->where('type', SupportCaseType::Defect->value)->count(),
            'returns' => (int) ($events[LegacyTagEventType::Returned->value] ?? 0),
            'replacements' => (int) ($events[LegacyTagEventType::Replaced->value] ?? 0),
        ];
    }

    /**
     * @return array{top: array<string, mixed>|null, ranking: list<array<string, mixed>>}
     */
    private function collections(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = LegacyTag::query()->toBase()
            ->join('product_variants', 'product_variants.id', '=', 'legacy_tags.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('collections', 'collections.id', '=', 'products.collection_id')
            ->whereNull('legacy_tags.replaces_tag_id')
            ->whereNotNull('legacy_tags.sold_at')
            ->where('legacy_tags.sold_at', '>=', $from)
            ->where('legacy_tags.sold_at', '<=', $to)
            ->groupBy('collections.id', 'collections.name', 'collections.slug')
            ->orderByDesc(DB::raw('count(*)'))
            ->limit(5)
            ->get([
                'collections.id', 'collections.name', 'collections.slug',
                DB::raw('count(*) as units'),
                DB::raw('sum(case when legacy_tags.activated_at is not null then 1 else 0 end) as activated'),
            ]);

        $available = LegacyTag::query()->toBase()
            ->join('product_variants', 'product_variants.id', '=', 'legacy_tags.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereIn('products.collection_id', $rows->pluck('id'))
            ->where('legacy_tags.status', LegacyTagStatus::InStock->value)
            ->groupBy('products.collection_id')
            ->pluck(DB::raw('count(*)'), 'products.collection_id');

        $ranking = $rows->map(fn (object $r) => [
            'id' => (int) $r->id,
            'name' => $r->name,
            'slug' => $r->slug,
            'units' => (int) $r->units,
            'activated' => (int) $r->activated,
            'activation_rate' => (int) $r->units > 0 ? round((int) $r->activated / (int) $r->units, 4) : null,
            'available' => (int) ($available[$r->id] ?? 0),
        ])->values()->all();

        return ['top' => $ranking[0] ?? null, 'ranking' => array_values($ranking)];
    }

    /**
     * In-person sale duration: screen opened (sale_started_at) → order created.
     *
     * @return array<string, mixed>
     */
    private function pos(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $target = (int) config('legacy.tags.kpi.sale_target_seconds', 90);

        $durations = Order::query()->toBase()
            ->where('sales_channel', '!=', SalesChannel::Online->value)
            ->whereNotNull('sale_started_at')
            ->whereBetween('created_at', [$from, $to])
            ->limit(self::SAMPLE_LIMIT)
            ->get(['sale_started_at', 'created_at'])
            ->map(fn (object $o) => max(0, CarbonImmutable::parse($o->created_at)->getTimestamp() - CarbonImmutable::parse($o->sale_started_at)->getTimestamp()))
            ->values()->all();
        $durations = array_values($durations);

        $count = count($durations);

        return [
            'target_seconds' => $target,
            'sales' => $count,
            'median_seconds' => self::median($durations),
            'within_target_rate' => $count > 0 ? round(count(array_filter($durations, fn (int $d) => $d <= $target)) / $count, 4) : null,
        ];
    }

    /**
     * @param  list<int|float>  $values
     */
    public static function median(array $values): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return (int) round($n % 2 === 1 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2);
    }
}
