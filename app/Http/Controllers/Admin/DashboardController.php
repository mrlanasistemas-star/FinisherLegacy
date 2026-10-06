<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IncidentStatus;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PlateStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\AthleteProfile;
use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\EventIncident;
use App\Models\EventParticipant;
use App\Models\EventPreregistration;
use App\Models\InventoryLevel;
use App\Models\LegacyPlateEntitlement;
use App\Models\Order;
use App\Models\Plate;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Resumen. Every number is a live aggregate over the
 * existing commerce/production tables — nothing here is a sample figure.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = now()->toDateString();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'athletes' => AthleteProfile::count(),
                'events' => Event::count(),
                'participants' => EventParticipant::count(),
                'preregistrations' => EventPreregistration::count(),
                'plates_today' => Plate::whereDate('created_at', $today)->count(),
                'pending_production' => Plate::whereIn('status', [
                    PlateStatus::Draft, PlateStatus::Queued, PlateStatus::Processing,
                ])->count(),
                'delivered' => Plate::where('status', PlateStatus::Delivered)->count(),
                'open_incidents' => EventIncident::where('status', IncidentStatus::Open)->count(),
                'new_messages' => ContactMessage::query()->where('status', 'new')->count(),
            ],
            'productionByStatus' => Plate::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'plateInventory' => $this->plateInventory(),
            'inventory' => $this->inventoryRows(),
            'workflow' => $this->orderWorkflow(),
        ]);
    }

    /**
     * Legacy Plate stock (inventory levels of every Legacy Plate variant)
     * plus where the sold plates are in the production pipeline.
     *
     * @return array<string, int>
     */
    private function plateInventory(): array
    {
        $levels = InventoryLevel::query()
            ->whereHas('productVariant.product', fn ($q) => $q->where('type', ProductType::LegacyPlate))
            ->selectRaw('coalesce(sum(quantity_on_hand), 0) as on_hand, coalesce(sum(quantity_reserved), 0) as reserved')
            ->first();

        $entitlements = LegacyPlateEntitlement::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $count = fn (LegacyPlateEntitlementStatus ...$statuses) => (int) collect($statuses)->sum(fn ($s) => $entitlements[$s->value] ?? 0);

        return [
            'available' => max(0, (int) ($levels->on_hand ?? 0) - (int) ($levels->reserved ?? 0)),
            'reserved' => (int) ($levels->reserved ?? 0),
            'in_personalization' => $count(LegacyPlateEntitlementStatus::Paid, LegacyPlateEntitlementStatus::Linked, LegacyPlateEntitlementStatus::Queued),
            'delivered' => $count(LegacyPlateEntitlementStatus::Delivered, LegacyPlateEntitlementStatus::Produced),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function inventoryRows(): array
    {
        return InventoryLevel::query()
            ->join('product_variants', 'product_variants.id', '=', 'inventory_levels.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->groupBy('product_variants.id', 'product_variants.sku', 'product_variants.name', 'products.name', 'products.id')
            ->orderByRaw('sum(inventory_levels.quantity_on_hand) - sum(inventory_levels.quantity_reserved) asc')
            ->limit(8)
            ->get([
                'products.id as product_id',
                'product_variants.sku',
                'product_variants.name as variant',
                'products.name as product',
                DB::raw('sum(inventory_levels.quantity_on_hand) as on_hand'),
                DB::raw('sum(inventory_levels.quantity_reserved) as reserved'),
            ])
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'sku' => $row->sku,
                'product' => $row->product,
                'variant' => $row->variant,
                'available' => (int) $row->on_hand - (int) $row->reserved,
                'reserved' => (int) $row->reserved,
            ])
            ->values()
            ->all();
    }

    /**
     * Pedido → Grabado → Vinculación NFC → Envío, counted from the real
     * order / plate entitlement states.
     *
     * @return list<array{key: string, label: string, count: int}>
     */
    private function orderWorkflow(): array
    {
        return [
            [
                'key' => 'orders',
                'label' => 'Pedido',
                'count' => Order::query()
                    ->whereIn('status', [OrderStatus::Pending, OrderStatus::Confirmed])
                    ->where('payment_status', '!=', OrderPaymentStatus::Failed)
                    ->count(),
            ],
            [
                'key' => 'engraving',
                'label' => 'Grabado',
                'count' => LegacyPlateEntitlement::query()->where('status', LegacyPlateEntitlementStatus::Queued)->count(),
            ],
            [
                'key' => 'linking',
                'label' => 'Vinculación NFC',
                'count' => LegacyPlateEntitlement::query()->whereIn('status', [LegacyPlateEntitlementStatus::Paid, LegacyPlateEntitlementStatus::Linked])->count(),
            ],
            [
                'key' => 'shipping',
                'label' => 'Envío',
                'count' => LegacyPlateEntitlement::query()->where('status', LegacyPlateEntitlementStatus::Produced)->count(),
            ],
        ];
    }
}
