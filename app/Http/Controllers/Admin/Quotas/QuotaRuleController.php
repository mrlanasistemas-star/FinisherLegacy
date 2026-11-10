<?php

namespace App\Http\Controllers\Admin\Quotas;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\PhotoQuotaRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Upserts / removes the base photo limit of a product or a product type.
 * Lowering a limit never deletes photos: stories above it keep every
 * photo and only stop accepting new ones.
 */
class QuotaRuleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['product', 'type'])],
            'product_id' => ['required_if:scope,product', 'nullable', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('type', '!=', ProductType::DigitalPhoto->value))],
            'product_type' => ['required_if:scope,type', 'nullable', Rule::enum(ProductType::class)->except([ProductType::DigitalPhoto])],
            'included_photos' => ['required', 'integer', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $isProduct = $data['scope'] === 'product';
        $key = $isProduct
            ? PhotoQuotaRule::productKey((int) $data['product_id'])
            : PhotoQuotaRule::typeKey(ProductType::from((string) $data['product_type']));

        PhotoQuotaRule::query()->updateOrCreate(['scope_key' => $key], [
            'product_id' => $isProduct ? (int) $data['product_id'] : null,
            'product_type' => $isProduct ? null : $data['product_type'],
            'included_photos' => (int) $data['included_photos'],
            'notes' => $data['notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Límite guardado. Las fotos que ya existen no se tocan.']);

        return back();
    }

    public function destroy(PhotoQuotaRule $rule): RedirectResponse
    {
        $rule->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Límite eliminado: vuelve a aplicar el valor general.']);

        return back();
    }
}
