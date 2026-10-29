<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\LegacyTags\CreateLot;
use App\Actions\LegacyTags\ReceiveLot;
use App\Enums\InventoryLotStatus;
use App\Enums\LegacyTagStatus;
use App\Http\Controllers\Controller;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\LegacyTag;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Support\LegacyTags\TagCode;
use App\Support\LegacyTags\TagUrls;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inventario → Lotes: a manufacturing batch creates its codes BEFORE
 * fabrication (CreateLot), the factory gets them as CSV, and the lot is
 * received into a location (ReceiveLot, defective pieces disabled). The
 * actions write the audit log; this controller only validates and maps.
 */
class InventoryLotController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim($request->string('q')->toString()),
            'status' => $request->string('status')->toString() ?: null,
            'variant' => $request->integer('variant') ?: null,
            'supplier' => $request->integer('supplier') ?: null,
        ];

        $lots = InventoryLot::query()
            ->with(['productVariant.product:id,name', 'supplier:id,name', 'inventoryLocation:id,name'])
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', "%{$filters['q']}%")->orWhere('reference', 'like', "%{$filters['q']}%")))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['variant'], fn ($q, $v) => $q->where('product_variant_id', $v))
            ->when($filters['supplier'], fn ($q, $s) => $q->where('supplier_id', $s))
            ->latest('id')
            ->paginate(20)->withQueryString()
            ->through(fn (InventoryLot $lot) => $this->payload($lot));

        return Inertia::render('admin/inventory/lots/Index', [
            'lots' => $lots,
            'filters' => $filters,
            'statuses' => InventoryLotStatus::options(),
            'variants' => $this->variantOptions(),
            'suppliers' => $this->supplierOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/inventory/lots/Create', [
            'variants' => $this->variantOptions(),
            'suppliers' => $this->supplierOptions(),
            'maxQuantity' => CreateLot::MAX_QUANTITY,
        ]);
    }

    public function store(Request $request, CreateLot $createLot): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CreateLot::MAX_QUANTITY],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'unit_cost_minor' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'ordered_at' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $variant = ProductVariant::query()->with('product')->whereKey($data['product_variant_id'])->firstOrFail();

        if (! $variant->product?->tracks_units) {
            throw ValidationException::withMessages(['product_variant_id' => 'Ese producto no maneja piezas únicas (activa «Cada pieza es única» en el producto).']);
        }

        $lot = $createLot->handle($variant, (int) $data['quantity'], [
            'supplier_id' => $data['supplier_id'] ?? null,
            'unit_cost_minor' => $data['unit_cost_minor'] ?? null,
            'ordered_at' => $data['ordered_at'] ?? null,
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Lote {$lot->code} creado con {$lot->quantity} códigos."]);

        return redirect()->route('admin.catalog.lots.show', $lot->uuid);
    }

    public function show(Request $request, InventoryLot $lot): Response
    {
        $lot->load(['productVariant.product:id,name', 'supplier:id,name', 'inventoryLocation:id,name', 'createdBy:id,first_name,last_name']);

        $status = LegacyTagStatus::tryFrom($request->string('status')->toString());

        $tags = LegacyTag::query()
            ->where('inventory_lot_id', $lot->id)
            ->with('location:id,name')
            ->when($status, fn ($q) => $q->where('status', $status->value))
            ->orderBy('id')
            ->paginate(50)->withQueryString()
            ->through(fn (LegacyTag $t) => [
                'uuid' => $t->uuid,
                'serial' => $t->serial,
                'display_code' => $t->displayCode(),
                'status' => $t->status->value,
                'location' => $t->location?->name,
            ]);

        $counts = LegacyTag::query()->where('inventory_lot_id', $lot->id)
            ->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('admin/inventory/lots/Show', [
            'lot' => $this->payload($lot) + [
                'notes' => $lot->notes,
                'issues' => $lot->issues,
                'created_by' => $lot->createdBy?->name,
                'unit_cost_minor' => $lot->unit_cost_minor,
                'currency' => $lot->currency,
            ],
            'tags' => $tags,
            'counts' => $counts,
            'filters' => ['status' => $status?->value],
            'locations' => InventoryLocation::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'type'])
                ->map(fn (InventoryLocation $l) => ['value' => $l->id, 'label' => $l->name.' · '.$l->type->label()])->all(),
        ]);
    }

    /** Codes for the factory: one row per piece, ready to encode the NFC chip (the tag has no printed QR). */
    public function export(Request $request, InventoryLot $lot): StreamedResponse
    {
        activity()->causedBy($request->user())->performedOn($lot)->log('tags.lot.codes_exported');

        $filename = 'codigos-'.$lot->code.'.csv';

        return response()->streamDownload(function () use ($lot) {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['serial', 'public_code', 'display_code', 'nfc_url']);

            LegacyTag::query()->where('inventory_lot_id', $lot->id)->orderBy('id')
                ->chunk(500, function (EloquentCollection $tags) use ($out) {
                    foreach ($tags as $tag) {
                        fputcsv($out, [$tag->serial, $tag->public_code, TagCode::format($tag->public_code), TagUrls::nfc($tag)]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function receive(Request $request, InventoryLot $lot, ReceiveLot $receiveLot): RedirectResponse
    {
        $data = $request->validate([
            'inventory_location_id' => ['required', 'integer', Rule::exists('inventory_locations', 'id')->where('active', true)],
            'defective' => ['nullable', 'string', 'max:20000'],
            'issues' => ['nullable', 'string', 'max:2000'],
        ]);

        // Accepts codes (any format, even pasted URLs) or serials, one per line / comma.
        $defective = collect(preg_split('/[\s,;]+/', (string) ($data['defective'] ?? '')) ?: [])
            ->map(fn (string $v) => trim($v))
            ->filter()
            ->map(fn (string $v) => str_contains(strtoupper($v), (string) config('legacy.tags.serial_prefix', 'FLT').'-') ? strtoupper($v) : (TagCode::normalize($v) ?? strtoupper($v)))
            ->unique()->values();

        if ($defective->isNotEmpty()) {
            $known = LegacyTag::query()->where('inventory_lot_id', $lot->id)
                ->where(fn ($q) => $q->whereIn('public_code', $defective)->orWhereIn('serial', $defective))
                ->get(['public_code', 'serial']);
            $unknown = $defective->reject(fn ($v) => $known->contains(fn ($t) => $t->public_code === $v || $t->serial === $v));

            if ($unknown->isNotEmpty()) {
                throw ValidationException::withMessages(['defective' => 'No pertenecen a este lote: '.$unknown->take(10)->implode(', ')]);
            }
        }

        try {
            $lot = $receiveLot->handle($lot, InventoryLocation::query()->whereKey($data['inventory_location_id'])->firstOrFail(), $request->user(), array_values($defective->all()), $data['issues'] ?? null);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['inventory_location_id' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Lote recibido: {$lot->quantity_received} piezas en inventario."]);

        return back();
    }

    /** @return array<string, mixed> */
    private function payload(InventoryLot $lot): array
    {
        return [
            'uuid' => $lot->uuid,
            'code' => $lot->code,
            'status' => $lot->status->value,
            'status_label' => $lot->status->label(),
            'sku' => $lot->productVariant?->sku,
            'variant' => $lot->productVariant?->name,
            'product' => $lot->productVariant?->product?->name,
            'product_id' => $lot->productVariant?->product_id,
            'supplier' => $lot->supplier?->name,
            'location' => $lot->inventoryLocation?->name,
            'quantity' => $lot->quantity,
            'quantity_received' => $lot->quantity_received,
            'reference' => $lot->reference,
            'ordered_at' => $lot->ordered_at?->toDateString(),
            'received_at' => $lot->received_at?->toIso8601String(),
        ];
    }

    /** @return list<array{value: int, label: string, description: string}> */
    private function variantOptions(): array
    {
        return array_values(ProductVariant::query()
            ->whereHas('product', fn ($q) => $q->where('tracks_units', true))
            ->with('product:id,name')
            ->orderBy('sku')->limit(1000)->get(['id', 'sku', 'name', 'product_id'])
            ->map(fn (ProductVariant $v) => ['value' => $v->id, 'label' => $v->sku, 'description' => trim(($v->product->name ?? '').' · '.$v->name)])
            ->all());
    }

    /** @return list<array{value: int, label: string}> */
    private function supplierOptions(): array
    {
        return array_values(Supplier::query()->orderBy('name')->get(['id', 'name'])->map(fn (Supplier $s) => ['value' => $s->id, 'label' => $s->name])->all());
    }
}
