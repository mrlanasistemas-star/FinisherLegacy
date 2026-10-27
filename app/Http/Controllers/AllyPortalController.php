<?php

namespace App\Http\Controllers;

use App\Actions\SupportCases\OpenSupportCase;
use App\Enums\LegacyTagStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\SupportCaseType;
use App\Models\Ally;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\Order;
use App\Models\SupportCase;
use App\Support\Commerce\PosLocations;
use App\Support\LegacyTags\TagCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Portal del aliado (`ally.portal`): an ally's seller/manager sees ONLY the
 * allies they are linked to through ally_user — their locations' stock,
 * their sales and their incidents. Every query is scoped by
 * PosLocations::allyIds(); nothing here accepts another ally's id.
 */
class AllyPortalController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $allyIds = PosLocations::allyIds($request->user());
        $allies = Ally::query()->whereIn('id', $allyIds)->orderBy('name')->get(['id', 'name', 'city']);
        $locations = InventoryLocation::query()->whereIn('ally_id', $allyIds)->where('active', true)->orderBy('name')->get(['id', 'name', 'ally_id']);

        $stock = LegacyTag::query()->toBase()
            ->join('product_variants', 'product_variants.id', '=', 'legacy_tags.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereIn('legacy_tags.inventory_location_id', $locations->pluck('id'))
            ->where('legacy_tags.status', LegacyTagStatus::InStock->value)
            ->groupBy('legacy_tags.inventory_location_id', 'product_variants.sku', 'products.name')
            ->orderBy('products.name')
            ->get(['legacy_tags.inventory_location_id', 'product_variants.sku', 'products.name', DB::raw('count(*) as units')])
            ->groupBy('inventory_location_id');

        $monthOrders = Order::query()->whereIn('ally_id', $allyIds)
            ->whereIn('payment_status', [OrderPaymentStatus::Paid, OrderPaymentStatus::PartiallyRefunded])
            ->where('created_at', '>=', now()->startOfMonth());

        return Inertia::render('ally/Dashboard', [
            'allies' => $allies->map(fn (Ally $a) => ['id' => $a->id, 'name' => $a->name, 'city' => $a->city])->values(),
            'locations' => $locations->map(fn (InventoryLocation $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'ally' => $allies->firstWhere('id', $l->ally_id)?->name,
                'units' => (int) collect($stock->get($l->id, []))->sum('units'),
                'skus' => collect($stock->get($l->id, []))->map(fn (\stdClass $r) => ['sku' => $r->sku, 'name' => $r->name, 'units' => (int) $r->units])->values()->all(),
            ])->values(),
            'month' => [
                'orders' => (clone $monthOrders)->count(),
                'total_minor' => (int) (clone $monthOrders)->sum('total_minor'),
                'activated' => LegacyTag::query()->whereIn('ally_id', $allyIds)->where('status', LegacyTagStatus::Activated->value)
                    ->where('activated_at', '>=', now()->startOfMonth())->count(),
                'currency' => (string) config('finisher.commerce.default_currency', 'MXN'),
            ],
            'sales' => Order::query()->with('seller:id,first_name,last_name', 'inventoryLocation:id,name')->withSum('items', 'quantity')
                ->whereIn('ally_id', $allyIds)->latest('id')->limit(20)->get()
                ->map(fn (Order $o) => [
                    'number' => $o->order_number,
                    'total_minor' => (int) $o->total_minor,
                    'currency' => $o->currency,
                    'units' => (int) $o->getAttribute('items_sum_quantity'),
                    'seller' => $o->seller?->name,
                    'location' => $o->inventoryLocation?->name,
                    'created_at' => $o->created_at?->toIso8601String(),
                ])->values(),
            'incidents' => SupportCase::query()->whereIn('ally_id', $allyIds)->latest('id')->limit(10)->get()
                ->map(fn (SupportCase $c) => [
                    'number' => $c->number,
                    'subject' => $c->subject,
                    'type_label' => $c->type->label(),
                    'status' => $c->status->value,
                    'status_label' => $c->status->label(),
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values(),
            'incidentTypes' => array_values(array_filter(SupportCaseType::options(), fn ($o) => $o['value'] !== SupportCaseType::Ownership->value)),
        ]);
    }

    public function reportIncident(Request $request, OpenSupportCase $open): RedirectResponse
    {
        $allyIds = PosLocations::allyIds($request->user());

        $data = $request->validate([
            'ally_id' => ['required', 'integer'],
            'type' => ['required', Rule::enum(SupportCaseType::class)],
            'subject' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'tag_code' => ['nullable', 'string', 'max:300'],
            'contact_email' => ['nullable', 'email', 'max:190'],
        ]);

        abort_unless(in_array((int) $data['ally_id'], $allyIds, true), 403);

        $tag = null;

        if (filled($data['tag_code'] ?? null)) {
            $code = TagCode::normalize($data['tag_code']);
            $tag = $code ? LegacyTag::query()->with('location:id,ally_id')->where('public_code', $code)->first() : null;
            $belongs = $tag !== null && ((int) $tag->ally_id === (int) $data['ally_id'] || (int) $tag->location?->ally_id === (int) $data['ally_id']);

            if (! $belongs) {
                throw ValidationException::withMessages(['tag_code' => 'Ese código no corresponde a una pieza de tu punto de venta.']);
            }
        }

        $case = $open->handle([
            'type' => SupportCaseType::from($data['type']),
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'legacy_tag_id' => $tag?->id,
            'order_id' => $tag?->order_id,
            'ally_id' => (int) $data['ally_id'],
            'reporter_user_id' => $request->user()->id,
            'contact_email' => $data['contact_email'] ?? $request->user()->email,
        ], $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Recibimos tu reporte ({$case->number}). Te contactaremos pronto."]);

        return back();
    }
}
