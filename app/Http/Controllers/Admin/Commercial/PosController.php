<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Actions\Commerce\ResolveProductPrice;
use App\Actions\LegacyTags\RecordInPersonSale;
use App\Enums\LegacyTagStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Commercial\RecordPosSaleRequest;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\ProductVariant;
use App\Support\Commerce\PosLocations;
use App\Support\LegacyTags\TagCode;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Venta rápida (docs/architecture/commerce-os.md §Venta rápida): the ≤90 s
 * in-person sale at a stand, event or ally counter. One controller serves
 * both /admin/venta-rapida (`pos.sell`, every active location) and
 * /aliado/venta-rapida (`ally.portal`, only the user's ally locations —
 * see PosLocations). The sale itself is App\Actions\LegacyTags\RecordInPersonSale.
 */
class PosController extends Controller
{
    /** Longest a sale may claim to have been open (KPI clamp). */
    private const MAX_SALE_SECONDS = 3600;

    public function show(Request $request, ResolveProductPrice $prices): Response
    {
        $allyPortal = $this->isAllyPortal($request);
        $locations = PosLocations::for($request->user(), $allyPortal)
            ->with(['ally:id,name', 'eventEdition'])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/pos/Index', [
            'mode' => $allyPortal ? 'ally' : 'admin',
            'locations' => $locations->map(fn (InventoryLocation $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'type' => $l->type->value,
                'type_label' => $l->type->label(),
                'ally' => $l->ally?->name,
                'channel' => RecordInPersonSale::channelFor($l)->value,
            ])->values(),
            'stock' => $this->stockByLocation($locations, $prices),
            'paymentMethods' => [
                ['value' => PaymentMethod::Cash->value, 'label' => 'Efectivo'],
                ['value' => PaymentMethod::MercadoPagoTerminal->value, 'label' => 'Terminal'],
            ],
            'targetSeconds' => (int) config('legacy.tags.kpi.sale_target_seconds', 90),
            'lastSale' => $request->session()->get('pos_sale'),
        ]);
    }

    /** Resolve one scanned/typed code against the chosen location (JSON). */
    public function lookup(Request $request, ResolveProductPrice $prices): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:300'],
            'location_id' => ['required', 'integer'],
        ]);

        $location = $this->allowedLocation($request, (int) $data['location_id']);
        $code = TagCode::normalize($data['code']);

        if ($code === null) {
            return response()->json(['message' => 'Ese código no corresponde a un Finisher Legacy Tag.'], 422);
        }

        $tag = LegacyTag::query()->with('productVariant.product', 'location:id,name')->where('public_code', $code)->first();

        if ($tag === null) {
            return response()->json(['message' => 'Código '.TagCode::format($code).' no encontrado.'], 404);
        }

        if ($tag->status !== LegacyTagStatus::InStock) {
            return response()->json(['message' => "La pieza {$tag->displayCode()} no está disponible ({$tag->status->label()})."], 422);
        }

        if ($tag->inventory_location_id !== $location->id) {
            $where = $tag->location->name ?? 'otra ubicación';

            return response()->json(['message' => "La pieza {$tag->displayCode()} está registrada en {$where}, no en {$location->name}."], 422);
        }

        $variant = $tag->productVariant;
        $price = $prices->handle($variant->product, $variant, $location->eventEdition, null, RecordInPersonSale::channelFor($location), $location->ally_id);

        return response()->json([
            'code' => $tag->public_code,
            'display_code' => $tag->displayCode(),
            'variant_id' => $variant->id,
            'sku' => $variant->sku,
            'name' => $variant->product->name,
            'unit_price_minor' => $price->amountMinor,
            'currency' => $price->currency,
        ]);
    }

    public function store(RecordPosSaleRequest $request, RecordInPersonSale $sale): RedirectResponse
    {
        $allyPortal = $this->isAllyPortal($request);
        $location = $this->allowedLocation($request, (int) $request->integer('location_id'));

        try {
            $order = $sale->handle(
                location: $location,
                seller: $request->user(),
                method: PaymentMethod::from($request->string('payment_method')->toString()),
                scannedCodes: array_values(array_filter((array) $request->input('codes', []), 'filled')),
                quantities: collect((array) $request->input('quantities', []))->mapWithKeys(fn ($q, $id) => [(int) $id => (int) $q])->filter()->all(),
                customer: (array) $request->input('customer', []),
                paymentReference: $request->input('payment_reference'),
                startedAt: $this->clampStartedAt($request->input('started_at')),
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['sale' => $e->getMessage()]);
        }

        $order->loadMissing('legacyTags.productVariant.product');

        $request->session()->flash('pos_sale', [
            'order_number' => $order->order_number,
            'total_minor' => (int) $order->total_minor,
            'currency' => $order->currency,
            'units' => $order->legacyTags->map(fn (LegacyTag $t) => [
                'display_code' => $t->displayCode(),
                'name' => $t->productVariant?->product?->name,
            ])->values()->all(),
            'seconds' => $order->sale_started_at !== null ? (int) $order->sale_started_at->diffInSeconds($order->created_at) : null,
        ]);

        return redirect()->route($allyPortal ? 'ally.pos.show' : 'admin.pos.show');
    }

    private function isAllyPortal(Request $request): bool
    {
        return $request->routeIs('ally.*');
    }

    private function allowedLocation(Request $request, int $id): InventoryLocation
    {
        $location = PosLocations::for($request->user(), $this->isAllyPortal($request))->with('eventEdition')->whereKey($id)->first();

        abort_if($location === null, 403, 'No puedes vender desde esa ubicación.');

        return $location;
    }

    /**
     * The client sends when the sale screen opened. Never trusted beyond
     * [now - 1 h, now]; anything unparsable or older is clamped.
     */
    private function clampStartedAt(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $started = CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }

        $now = CarbonImmutable::now();

        if ($started->greaterThan($now)) {
            return $now;
        }

        return $started->lessThan($now->subSeconds(self::MAX_SALE_SECONDS)) ? $now->subSeconds(self::MAX_SALE_SECONDS) : $started;
    }

    /**
     * Design tiles per location: in-stock units by variant with the price of
     * that location's channel/ally.
     *
     * @param  Collection<int, InventoryLocation>  $locations
     * @return array<int, list<array<string, mixed>>>
     */
    private function stockByLocation($locations, ResolveProductPrice $prices): array
    {
        if ($locations->isEmpty()) {
            return [];
        }

        $rows = LegacyTag::query()->toBase()
            ->whereIn('inventory_location_id', $locations->pluck('id'))
            ->where('status', LegacyTagStatus::InStock->value)
            ->groupBy('inventory_location_id', 'product_variant_id')
            ->get(['inventory_location_id', 'product_variant_id', DB::raw('count(*) as units')]);

        $variants = ProductVariant::query()
            ->with(['product.collection', 'product.media'])
            ->whereIn('id', $rows->pluck('product_variant_id')->unique())
            ->get()
            ->keyBy('id');

        $byId = $locations->keyBy('id');
        $stock = [];

        foreach ($rows as $row) {
            $location = $byId->get($row->inventory_location_id);
            $variant = $variants->get($row->product_variant_id);

            if ($location === null || $variant === null) {
                continue;
            }

            $price = $prices->handle($variant->product, $variant, $location->eventEdition, null, RecordInPersonSale::channelFor($location), $location->ally_id);

            $stock[$location->id][] = [
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'name' => $variant->product->name,
                'variant' => $variant->name,
                'collection' => $variant->product->collection?->name,
                'image_url' => $variant->product->primaryImageUrl(),
                'available' => (int) $row->units,
                'unit_price_minor' => $price->amountMinor,
                'currency' => $price->currency,
            ];
        }

        foreach ($stock as &$tiles) {
            usort($tiles, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));
        }

        return $stock;
    }
}
