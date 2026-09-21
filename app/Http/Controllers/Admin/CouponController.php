<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Store admin — Cupones (brief §41: "NO crear 5 módulos. Solo una
 * pantalla"). Thin CRUD, same shape as Admin\ProductController — the
 * actual eligibility rules live in App\Actions\Commerce\ValidateCoupon,
 * never here.
 */
class CouponController extends Controller
{
    public function index(Request $request): Response
    {
        $coupons = Coupon::query()
            ->withCount('redemptions')
            ->when($request->string('q')->toString(), fn ($q, $search) => $q->where('code', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $coupons->through(fn (Coupon $coupon) => [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'name' => $coupon->name,
            'type' => $coupon->type->value,
            'value' => $coupon->value,
            'currency' => $coupon->currency,
            'starts_at' => $coupon->starts_at?->toDateString(),
            'ends_at' => $coupon->ends_at?->toDateString(),
            'usage_limit_total' => $coupon->usage_limit_total,
            'usage_limit_per_user' => $coupon->usage_limit_per_user,
            'used_count' => $coupon->redemptions_count,
            'minimum_order_minor' => $coupon->minimum_order_minor,
            'active' => $coupon->active,
        ]);

        return Inertia::render('admin/coupons/Index', [
            'coupons' => $coupons,
            'filters' => ['q' => $request->string('q')->toString()],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['uuid'] = (string) Str::uuid();
        $data['created_by'] = $request->user()->id;

        Coupon::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cupón creado.']);

        return back();
    }

    public function update(Request $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validated($request, $coupon));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cupón actualizado.']);

        return back();
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        // Redemption rows restrictOnDelete a used coupon — deactivating is
        // the real "removal" path once a coupon has history (brief §41
        // wants this simple, not a soft-delete system).
        if ($coupon->redemptions()->exists()) {
            $coupon->update(['active' => false]);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'El cupón ya fue usado — se desactivó en lugar de eliminarse.']);

            return back();
        }

        $coupon->delete();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cupón eliminado.']);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Coupon $coupon = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::enum(CouponType::class)],
            'value' => [
                'required', 'integer', 'min:1',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    if ($request->input('type') === CouponType::Percentage->value && $value > 100) {
                        $fail('El porcentaje no puede ser mayor a 100.');
                    }
                },
            ],
            'currency' => ['nullable', 'string', 'size:3'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'minimum_order_minor' => ['nullable', 'integer', 'min:0'],
            'active' => ['boolean'],
        ]);
    }
}
