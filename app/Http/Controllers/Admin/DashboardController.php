<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventPhotoStatus;
use App\Enums\IncidentStatus;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PhotographerStatus;
use App\Enums\PlateStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\AthleteProfile;
use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\EventIncident;
use App\Models\EventParticipant;
use App\Models\EventPhoto;
use App\Models\EventPreregistration;
use App\Models\InventoryLevel;
use App\Models\LegacyPlateEntitlement;
use App\Models\Order;
use App\Models\PhotographerProfile;
use App\Models\PhotoSale;
use App\Models\Plate;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Resumen. Every number is a live aggregate over the
 * existing commerce / production / photo tables — nothing here is a sample
 * figure. Production stages are PRESENTATION labels over the internal
 * entitlement / plate states (never renamed): see productionPipeline().
 */
class DashboardController extends Controller
{
    /** Variants at or below this many available units count as "stock bajo". */
    private const LOW_STOCK = 5;

    private const PERIODS = [7, 30, 90];

    public function index(Request $request): Response
    {
        $days = in_array((int) $request->query('period'), self::PERIODS, true) ? (int) $request->query('period') : 30;
        $from = CarbonImmutable::now()->startOfDay()->subDays($days - 1);
        $previousFrom = $from->subDays($days);
        $currency = (string) config('finisher.commerce.default_currency', 'MXN');

        $sales = $this->salesTotals($from, null);
        $previousSales = $this->salesTotals($previousFrom, $from);
        $lowStock = $this->lowStockRows();

        return Inertia::render('admin/Dashboard', [
            'period' => $days,
            'periods' => self::PERIODS,
            'currency' => $currency,
            'kpis' => [
                'pending_orders' => Order::query()
                    ->whereIn('status', [OrderStatus::Pending, OrderStatus::Confirmed])
                    ->whereNotIn('payment_status', [OrderPaymentStatus::Failed, OrderPaymentStatus::Cancelled])
                    ->count(),
                'sales' => $sales,
                'sales_previous' => $previousSales,
                'plates_in_production' => Plate::query()->whereIn('status', [
                    PlateStatus::Draft, PlateStatus::PendingConfirmation, PlateStatus::Queued,
                    PlateStatus::Processing, PlateStatus::Produced, PlateStatus::QualityCheck,
                ])->count(),
                'photos_pending' => EventPhoto::query()->where('status', EventPhotoStatus::Review)->count(),
                'photographers_pending' => PhotographerProfile::query()->where('status', PhotographerStatus::Pending)->count(),
                'low_stock' => $this->lowStockCount(),
            ],
            'salesSeries' => $this->salesSeries($from, $days, $currency),
            'production' => $this->productionPipeline(),
            'recentOrders' => $this->recentOrders(),
            'activeOffers' => Promotion::query()->running()->withCount('products')->orderBy('ends_at')->limit(6)->get()
                ->map(fn (Promotion $promotion) => [
                    'id' => $promotion->id,
                    'name' => $promotion->name,
                    'label' => $promotion->label(),
                    'applies_to' => $promotion->applies_to,
                    'products_count' => $promotion->products_count,
                    'ends_at' => $promotion->ends_at?->toDateString(),
                ]),
            'lowStock' => $lowStock,
            'photos' => $this->photoSummary($from),
            'general' => [
                'athletes' => AthleteProfile::count(),
                'events' => Event::count(),
                'participants' => EventParticipant::count(),
                'preregistrations' => EventPreregistration::count(),
                'open_incidents' => EventIncident::where('status', IncidentStatus::Open)->count(),
                'new_messages' => ContactMessage::query()->where('status', 'new')->count(),
            ],
        ]);
    }

    /**
     * Paid sales per currency between two instants (null = until now).
     *
     * @return list<array{currency: string, total_minor: int, orders: int}>
     */
    private function salesTotals(CarbonImmutable $from, ?CarbonImmutable $until): array
    {
        return array_values(Order::query()
            ->whereIn('payment_status', [OrderPaymentStatus::Paid, OrderPaymentStatus::PartiallyRefunded])
            ->where('created_at', '>=', $from)
            ->when($until, fn ($q) => $q->where('created_at', '<', $until))
            ->toBase()
            ->groupBy('currency')
            ->orderByDesc(DB::raw('sum(total_minor)'))
            ->get(['currency', DB::raw('sum(total_minor) as total_minor'), DB::raw('count(*) as orders')])
            ->map(fn (object $row) => [
                'currency' => (string) $row->currency,
                'total_minor' => (int) $row->total_minor,
                'orders' => (int) $row->orders,
            ])
            ->values()
            ->all());
    }

    /**
     * One bar per day of the period, default currency only (other
     * currencies are reported in the KPI, never summed together).
     *
     * @return list<array{date: string, total_minor: int, orders: int}>
     */
    private function salesSeries(CarbonImmutable $from, int $days, string $currency): array
    {
        $rows = Order::query()
            ->whereIn('payment_status', [OrderPaymentStatus::Paid, OrderPaymentStatus::PartiallyRefunded])
            ->where('currency', $currency)
            ->where('created_at', '>=', $from)
            ->toBase()
            ->groupBy(DB::raw('date(created_at)'))
            ->get([DB::raw('date(created_at) as day'), DB::raw('sum(total_minor) as total_minor'), DB::raw('count(*) as orders')])
            ->keyBy(fn (object $row) => substr((string) $row->day, 0, 10));

        return array_values(collect(range(0, $days - 1))->values()->map(function (int $i) use ($from, $rows) {
            $date = $from->addDays($i)->toDateString();

            return [
                'date' => $date,
                'total_minor' => (int) ($rows[$date]->total_minor ?? 0),
                'orders' => (int) ($rows[$date]->orders ?? 0),
            ];
        })->all());
    }

    /**
     * Legacy Plate production as the business reads it. Labels map onto
     * the existing internal states without renaming them:
     *   Pedido            ← entitlement pending_payment
     *   Personalización   ← entitlement paid + linked (waiting to produce)
     *   Impresión         ← plate draft / pending_confirmation / queued / processing
     *   NFC · clip        ← plate produced (printed; NFC + clip assembly)
     *   Control de calidad← plate quality_check
     *   Envío             ← plate ready
     *
     * @return array{stages: list<array{key: string, label: string, count: int, states: string}>, delivered: int}
     */
    private function productionPipeline(): array
    {
        $entitlements = LegacyPlateEntitlement::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $plates = Plate::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $e = fn (LegacyPlateEntitlementStatus ...$s) => (int) collect($s)->sum(fn ($x) => $entitlements[$x->value] ?? 0);
        $p = fn (PlateStatus ...$s) => (int) collect($s)->sum(fn ($x) => $plates[$x->value] ?? 0);

        return [
            'stages' => [
                ['key' => 'order', 'label' => 'Pedido', 'count' => $e(LegacyPlateEntitlementStatus::PendingPayment), 'states' => 'Pago pendiente'],
                ['key' => 'personalization', 'label' => 'Personalización', 'count' => $e(LegacyPlateEntitlementStatus::Paid, LegacyPlateEntitlementStatus::Linked), 'states' => 'Pagada / vinculada'],
                ['key' => 'printing', 'label' => 'Impresión', 'count' => $p(PlateStatus::Draft, PlateStatus::PendingConfirmation, PlateStatus::Queued, PlateStatus::Processing), 'states' => 'En cola / imprimiendo'],
                ['key' => 'nfc_clip', 'label' => 'NFC · clip / ensamblaje', 'count' => $p(PlateStatus::Produced), 'states' => 'Producida'],
                ['key' => 'qc', 'label' => 'Control de calidad', 'count' => $p(PlateStatus::QualityCheck), 'states' => 'Revisión'],
                ['key' => 'shipping', 'label' => 'Envío', 'count' => $p(PlateStatus::Ready), 'states' => 'Lista para enviar'],
            ],
            'delivered' => $p(PlateStatus::Delivered),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrders(): array
    {
        return array_values(Order::query()
            ->with('user:id,name')
            ->withCount('items')
            ->latest()
            ->limit(8)
            ->get()
            ->values()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'uuid' => $order->uuid,
                'number' => $order->order_number,
                'customer' => $order->customer_snapshot['name'] ?? data_get($order, 'user.name') ?? 'Cliente',
                'total_minor' => (int) $order->total_minor,
                'currency' => $order->currency,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'items' => $order->items_count,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->all());
    }

    private function lowStockCount(): int
    {
        return DB::query()
            ->fromSub(
                InventoryLevel::query()
                    ->groupBy('product_variant_id')
                    ->havingRaw('sum(quantity_on_hand) - sum(quantity_reserved) <= ?', [self::LOW_STOCK])
                    ->select('product_variant_id'),
                'low',
            )
            ->count();
    }

    /**
     * Variants with the least available stock, with their product image.
     *
     * @return list<array<string, mixed>>
     */
    private function lowStockRows(): array
    {
        $rows = InventoryLevel::query()
            ->toBase()
            ->join('product_variants', 'product_variants.id', '=', 'inventory_levels.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.type', '!=', ProductType::DigitalPhoto->value)
            ->groupBy('product_variants.id', 'product_variants.sku', 'product_variants.name', 'products.name', 'products.id')
            ->havingRaw('sum(inventory_levels.quantity_on_hand) - sum(inventory_levels.quantity_reserved) <= ?', [self::LOW_STOCK])
            ->orderByRaw('sum(inventory_levels.quantity_on_hand) - sum(inventory_levels.quantity_reserved) asc')
            ->limit(8)
            ->get([
                'products.id as product_id',
                'product_variants.sku',
                'product_variants.name as variant',
                'products.name as product',
                DB::raw('sum(inventory_levels.quantity_on_hand) as on_hand'),
                DB::raw('sum(inventory_levels.quantity_reserved) as reserved'),
            ]);

        $products = Product::query()->with('media')->whereIn('id', $rows->pluck('product_id'))->get()->keyBy('id');

        return array_values($rows->map(fn (object $row) => [
            'product_id' => (int) $row->product_id,
            'sku' => $row->sku,
            'product' => $row->product,
            'variant' => $row->variant,
            'image_url' => $products->get((int) $row->product_id)?->primaryImageUrl(),
            'available' => (int) $row->on_hand - (int) $row->reserved,
            'reserved' => (int) $row->reserved,
        ])->values()->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function photoSummary(CarbonImmutable $from): array
    {
        $byStatus = EventPhoto::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $sales = PhotoSale::query()
            ->where('created_at', '>=', $from)
            ->toBase()
            ->groupBy('currency')
            ->get(['currency', DB::raw('count(*) as sold'), DB::raw('sum(gross_minor) as gross_minor'), DB::raw('sum(photographer_net_minor) as net_minor')]);

        return [
            'pending' => (int) ($byStatus[EventPhotoStatus::Review->value] ?? 0),
            'published' => (int) ($byStatus[EventPhotoStatus::Published->value] ?? 0),
            'rejected' => (int) ($byStatus[EventPhotoStatus::Rejected->value] ?? 0),
            'sales' => $sales->map(fn (object $row) => [
                'currency' => (string) $row->currency,
                'sold' => (int) $row->sold,
                'gross_minor' => (int) $row->gross_minor,
                'net_minor' => (int) $row->net_minor,
            ])->values()->all(),
            'photographers_pending' => PhotographerProfile::query()->where('status', PhotographerStatus::Pending)->latest()->limit(4)->get(['id', 'uuid', 'display_name', 'city', 'created_at'])
                ->map(fn (PhotographerProfile $p) => [
                    'id' => $p->id,
                    'uuid' => $p->uuid,
                    'name' => $p->display_name,
                    'city' => $p->city,
                    'created_at' => $p->created_at?->toIso8601String(),
                ])->all(),
            'recent_pending' => EventPhoto::query()->where('status', EventPhotoStatus::Review)->latest()->limit(8)->get()
                ->map(fn (EventPhoto $photo) => [
                    'uuid' => $photo->uuid,
                    'thumb_url' => $photo->thumbUrl(),
                ])->all(),
        ];
    }
}
