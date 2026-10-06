<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponType;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Ofertas: automatic sale prices (no code) on all
 * products or on a selected set, inside an optional date window.
 * Applied server-side by ResolveProductPrice/PromotionResolver.
 */
class PromotionController extends Controller
{
    public function index(): Response
    {
        $promotions = Promotion::query()->with('products:id,name')->latest('id')->get();

        return Inertia::render('admin/promotions/Index', [
            'promotions' => $promotions->map(fn (Promotion $p) => [
                'id' => $p->id,
                'uuid' => $p->uuid,
                'name' => $p->name,
                'badge_label' => $p->badge_label,
                'description' => $p->description,
                'type' => $p->type->value,
                'value' => $p->value,
                'applies_to' => $p->applies_to,
                'product_ids' => $p->products->pluck('id'),
                'product_names' => $p->products->pluck('name'),
                'starts_at' => $p->starts_at?->format('Y-m-d'),
                'ends_at' => $p->ends_at?->format('Y-m-d'),
                'active' => $p->active,
                'state' => $this->state($p),
            ]),
            'products' => $this->productOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $productIds] = $this->validated($request);

        DB::transaction(function () use ($data, $productIds, $request) {
            $promotion = Promotion::create([...$data, 'uuid' => (string) Str::uuid(), 'created_by' => $request->user()->id]);
            $promotion->products()->sync($data['applies_to'] === 'products' ? $productIds : []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Oferta creada.']);

        return back();
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        [$data, $productIds] = $this->validated($request);

        DB::transaction(function () use ($promotion, $data, $productIds) {
            $promotion->update($data);
            $promotion->products()->sync($data['applies_to'] === 'products' ? $productIds : []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Oferta actualizada.']);

        return back();
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $promotion->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Oferta eliminada.']);

        return back();
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<int>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'badge_label' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::enum(CouponType::class)],
            'value' => [
                'required', 'integer', 'min:1',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($request->input('type') === CouponType::Percentage->value && $value > 90) {
                        $fail('Una oferta no puede pasar del 90%.');
                    }
                },
            ],
            'applies_to' => ['required', Rule::in(['all', 'products'])],
            'product_ids' => ['required_if:applies_to,products', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'active' => ['boolean'],
        ], ['product_ids.required_if' => 'Selecciona al menos un producto.']);

        $productIds = array_map('intval', $data['product_ids'] ?? []);
        unset($data['product_ids']);
        $data['starts_at'] = isset($data['starts_at']) ? now()->parse($data['starts_at'])->startOfDay() : null;
        $data['ends_at'] = isset($data['ends_at']) ? now()->parse($data['ends_at'])->endOfDay() : null;

        return [$data, $productIds];
    }

    private function state(Promotion $promotion): string
    {
        if (! $promotion->active) {
            return 'paused';
        }

        if ($promotion->starts_at !== null && $promotion->starts_at->isFuture()) {
            return 'scheduled';
        }

        if ($promotion->ends_at !== null && $promotion->ends_at->isPast()) {
            return 'ended';
        }

        return 'running';
    }

    /**
     * Product picker options with their main photo — shared with coupons.
     *
     * @return list<array<string, mixed>>
     */
    public static function productOptions(): array
    {
        return Product::query()
            ->where('type', '!=', ProductType::DigitalPhoto)
            ->with('media')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'image_url' => $p->primaryImageUrl(),
                'active' => $p->active,
            ])
            ->values()
            ->all();
    }
}
