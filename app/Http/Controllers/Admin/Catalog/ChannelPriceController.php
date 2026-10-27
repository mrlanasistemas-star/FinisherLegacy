<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Enums\ProductPriceType;
use App\Enums\SalesChannel;
use App\Http\Controllers\Controller;
use App\Models\Ally;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Precios por canal: ProductPriceSchedule rows scoped by `sales_channel`
 * (and optionally one ally) that App\Actions\Commerce\ResolveProductPrice
 * reads. Rows are never deleted — they are disabled, so past sales keep an
 * explainable price; every change is written to the activity log.
 */
class ChannelPriceController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'product_variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
            'sales_channel' => ['required', 'string', Rule::enum(SalesChannel::class)],
            'ally_id' => ['nullable', 'integer', 'exists:allies,id'],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:100000000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        if (! empty($data['ally_id']) && $data['sales_channel'] !== SalesChannel::Ally->value) {
            throw ValidationException::withMessages(['ally_id' => 'Solo el canal «Aliado» puede tener un aliado específico.']);
        }

        $currency = $product->variants()->value('currency') ?? config('finisher.commerce.default_currency', 'MXN');

        $schedule = $product->priceSchedules()->create([
            'product_variant_id' => $data['product_variant_id'] ?? null,
            'price_type' => ProductPriceType::Standard,
            'sales_channel' => $data['sales_channel'],
            'ally_id' => $data['ally_id'] ?? null,
            'amount_minor' => $data['amount_minor'],
            'currency' => $currency,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'active' => true,
        ]);

        activity()->causedBy($request->user())->performedOn($product)
            ->withProperties(['schedule_id' => $schedule->id, ...$data])
            ->log('products.channel_price.created');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Precio por canal agregado.']);

        return back();
    }

    /** Enable / disable one channel price (the only edit — amounts are immutable history). */
    public function toggle(Request $request, ProductPriceSchedule $schedule): RedirectResponse
    {
        abort_if($schedule->sales_channel === null, 404);

        $data = $request->validate(['active' => ['required', 'boolean']]);
        $schedule->update(['active' => $data['active']]);

        $product = $schedule->product;
        activity()->causedBy($request->user())->performedOn($product ?? $schedule)
            ->withProperties(['schedule_id' => $schedule->id, 'active' => $data['active'], 'amount_minor' => $schedule->amount_minor, 'sales_channel' => $schedule->sales_channel])
            ->log($data['active'] ? 'products.channel_price.enabled' : 'products.channel_price.disabled');

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['active'] ? 'Precio reactivado.' : 'Precio desactivado.']);

        return back();
    }

    /**
     * Props for the product page's "Precios por canal" panel.
     *
     * @return array<string, mixed>
     */
    public static function panelFor(Product $product): array
    {
        $schedules = ProductPriceSchedule::query()
            ->where('product_id', $product->id)
            ->whereNotNull('sales_channel')
            ->with('productVariant:id,sku,name')
            ->leftJoin('allies', 'allies.id', '=', 'product_price_schedules.ally_id')
            ->select('product_price_schedules.*', 'allies.name as ally_name')
            ->orderByDesc('product_price_schedules.active')
            ->orderBy('product_price_schedules.sales_channel')
            ->latest('product_price_schedules.id')
            ->get();

        return [
            'prices' => $schedules->map(fn (ProductPriceSchedule $s) => [
                'id' => $s->id,
                'sales_channel' => $s->sales_channel,
                'channel_label' => SalesChannel::tryFrom((string) $s->sales_channel)?->label() ?? $s->sales_channel,
                'ally' => $s->getAttribute('ally_name'),
                'variant' => $s->productVariant ? $s->productVariant->sku : null,
                'amount_minor' => $s->amount_minor,
                'currency' => $s->currency,
                'starts_at' => $s->starts_at?->toDateString(),
                'ends_at' => $s->ends_at?->toDateString(),
                'active' => $s->active,
            ])->values()->all(),
            'channels' => SalesChannel::options(),
            'allies' => Ally::query()->orderBy('name')->limit(500)->get(['id', 'name'])
                ->map(fn (Ally $a) => ['value' => $a->id, 'label' => $a->name])->all(),
        ];
    }
}
