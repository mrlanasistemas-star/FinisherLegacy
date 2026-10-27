<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Enums\AllyStatus;
use App\Enums\AllyType;
use App\Enums\InventoryLocationType;
use App\Enums\LegacyTagStatus;
use App\Enums\OrderPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Commercial\AllyRequest;
use App\Models\Ally;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\Order;
use App\Models\SupportCase;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Comercial → Aliados: partner businesses that resell Finisher Legacy Tags
 * (gyms, stores, organizers…). An ally is a company, never a user; its
 * sellers link through ally_user and sell only from its `ally` locations.
 */
class AllyController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->trim()->toString(),
            'type' => AllyType::tryFrom((string) $request->query('type'))?->value,
            'status' => AllyStatus::tryFrom((string) $request->query('status'))?->value,
            'city' => $request->string('city')->trim()->toString(),
        ];

        $allies = Ally::query()
            ->withCount(['locations', 'users'])
            ->when($filters['q'], fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('contact_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")))
            ->when($filters['type'], fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'], fn ($q, $status) => $q->where('status', $status))
            ->when($filters['city'], fn ($q, $city) => $q->where('city', $city))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $stock = LegacyTag::query()->toBase()
            ->join('inventory_locations', 'inventory_locations.id', '=', 'legacy_tags.inventory_location_id')
            ->whereIn('inventory_locations.ally_id', $allies->pluck('id'))
            ->where('legacy_tags.status', LegacyTagStatus::InStock->value)
            ->groupBy('inventory_locations.ally_id')
            ->pluck(DB::raw('count(*)'), 'inventory_locations.ally_id');

        return Inertia::render('admin/allies/Index', [
            'allies' => $allies->through(fn (Ally $ally) => [
                ...$this->summary($ally),
                'locations_count' => $ally->locations_count,
                'users_count' => $ally->users_count,
                'units_in_stock' => (int) ($stock[$ally->id] ?? 0),
            ]),
            'filters' => $filters,
            'types' => AllyType::options(),
            'statuses' => AllyStatus::options(),
            'cities' => Ally::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'canManage' => $request->user()->can('allies.manage'),
        ]);
    }

    public function show(Request $request, Ally $ally): Response
    {
        $ally->load(['users' => fn ($q) => $q->orderBy('first_name'), 'locations' => fn ($q) => $q->orderBy('name')]);
        $memberRoles = DB::table('ally_user')->where('ally_id', $ally->id)->pluck('role', 'user_id');

        $stockRows = LegacyTag::query()->toBase()
            ->join('product_variants', 'product_variants.id', '=', 'legacy_tags.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereIn('legacy_tags.inventory_location_id', $ally->locations->pluck('id'))
            ->where('legacy_tags.status', LegacyTagStatus::InStock->value)
            ->groupBy('legacy_tags.inventory_location_id', 'product_variants.sku', 'products.name')
            ->orderBy('products.name')
            ->get(['legacy_tags.inventory_location_id', 'product_variants.sku', 'products.name', DB::raw('count(*) as units')])
            ->groupBy('inventory_location_id');

        $tagCounts = LegacyTag::query()->toBase()
            ->where('ally_id', $ally->id)
            ->groupBy('status')
            ->pluck(DB::raw('count(*)'), 'status');

        $paid = Order::query()->where('ally_id', $ally->id)
            ->whereIn('payment_status', [OrderPaymentStatus::Paid, OrderPaymentStatus::PartiallyRefunded]);

        // `metadata` is JSON; matched as text (portable) then confirmed in PHP.
        $unreported = LegacyTag::query()
            ->with(['productVariant:id,sku,product_id', 'productVariant.product:id,name', 'location:id,name'])
            ->where('ally_id', $ally->id)
            ->where('metadata', 'like', '%unreported_sale%')
            ->latest('sold_at')
            ->limit(100)
            ->get()
            ->filter(fn (LegacyTag $t) => ($t->metadata['unreported_sale'] ?? false) === true)
            ->values();

        return Inertia::render('admin/allies/Show', [
            'ally' => [
                ...$this->summary($ally),
                'state' => $ally->state,
                'address' => $ally->address,
                'commercial_terms' => $ally->commercial_terms,
                'notes' => $ally->notes,
                'created_at' => $ally->created_at?->toIso8601String(),
            ],
            'users' => $ally->users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => (string) ($memberRoles[$u->id] ?? 'seller'),
            ])->values(),
            'locations' => $ally->locations->map(fn (InventoryLocation $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'address' => $l->address,
                'active' => $l->active,
                'units' => (int) collect($stockRows->get($l->id, []))->sum('units'),
                'skus' => collect($stockRows->get($l->id, []))->map(fn (\stdClass $r) => [
                    'sku' => $r->sku,
                    'name' => $r->name,
                    'units' => (int) $r->units,
                ])->values()->all(),
            ])->values(),
            'stats' => [
                'orders' => (clone $paid)->count(),
                'sales_minor' => (int) (clone $paid)->sum('total_minor'),
                'units_sold' => (int) collect([LegacyTagStatus::Sold, LegacyTagStatus::Delivered, LegacyTagStatus::Activated])->sum(fn ($s) => $tagCounts[$s->value] ?? 0),
                'units_activated' => (int) ($tagCounts[LegacyTagStatus::Activated->value] ?? 0),
                'unreported' => $unreported->count(),
                'currency' => (string) config('finisher.commerce.default_currency', 'MXN'),
            ],
            'orders' => Order::query()->with('seller:id,first_name,last_name', 'inventoryLocation:id,name')->withSum('items', 'quantity')
                ->where('ally_id', $ally->id)->latest('id')->limit(15)->get()
                ->map(fn (Order $o) => [
                    'uuid' => $o->uuid,
                    'number' => $o->order_number,
                    'total_minor' => (int) $o->total_minor,
                    'currency' => $o->currency,
                    'channel' => $o->sales_channel->value,
                    'payment_status' => $o->payment_status->value,
                    'seller' => $o->seller?->name,
                    'location' => $o->inventoryLocation?->name,
                    'units' => (int) $o->getAttribute('items_sum_quantity'),
                    'created_at' => $o->created_at?->toIso8601String(),
                ])->values(),
            'unreported' => $unreported->map(fn (LegacyTag $t) => [
                'uuid' => $t->uuid,
                'serial' => $t->serial,
                'display_code' => $t->displayCode(),
                'name' => $t->productVariant?->product?->name,
                'sku' => $t->productVariant?->sku,
                'location' => $t->location?->name,
                'sold_at' => $t->sold_at?->toIso8601String(),
                'activated_at' => $t->activated_at?->toIso8601String(),
            ])->values(),
            'supportCases' => SupportCase::query()->where('ally_id', $ally->id)->latest('id')->limit(10)->get()
                ->map(fn (SupportCase $c) => [
                    'uuid' => $c->uuid,
                    'number' => $c->number,
                    'subject' => $c->subject,
                    'type' => $c->type->label(),
                    'status' => $c->status->value,
                    'status_label' => $c->status->label(),
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values(),
            'types' => AllyType::options(),
            'statuses' => AllyStatus::options(),
            'canManage' => $request->user()->can('allies.manage'),
        ]);
    }

    public function store(AllyRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $ally = Ally::create([
            ...$data,
            'uuid' => (string) Str::uuid(),
            'slug' => $this->uniqueSlug($data['name']),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Aliado {$ally->name} creado."]);

        return redirect()->route('admin.allies.show', $ally);
    }

    public function update(AllyRequest $request, Ally $ally): RedirectResponse
    {
        $ally->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Aliado actualizado.']);

        return back();
    }

    /**
     * Link an existing account as this ally's seller/manager. An account
     * without staff access also receives the `ally` role (portal only).
     */
    public function attachUser(Request $request, Ally $ally): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', Rule::in(['seller', 'manager'])],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'No existe una cuenta con ese correo. Pide a la persona que se registre primero.']);
        }

        $ally->users()->syncWithoutDetaching([$user->id => ['role' => $data['role']]]);
        $ally->users()->updateExistingPivot($user->id, ['role' => $data['role']]);

        if (! $user->can('dashboard.admin.view') && ! $user->hasRole('ally')) {
            $user->assignRole('ally');
        }

        activity()->causedBy($request->user())->performedOn($ally)
            ->withProperties(['user_id' => $user->id, 'role' => $data['role']])
            ->log('allies.user_attached');

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} ahora vende para {$ally->name}."]);

        return back();
    }

    public function detachUser(Request $request, Ally $ally, User $user): RedirectResponse
    {
        $ally->users()->detach($user->id);

        // Portal-only accounts lose the portal with their last ally.
        if ($user->hasRole('ally') && $user->allies()->count() === 0) {
            $user->removeRole('ally');
        }

        activity()->causedBy($request->user())->performedOn($ally)
            ->withProperties(['user_id' => $user->id])
            ->log('allies.user_detached');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Usuario desvinculado del aliado.']);

        return back();
    }

    public function storeLocation(Request $request, Ally $ally): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        InventoryLocation::create([
            'name' => $data['name'],
            'slug' => $this->uniqueLocationSlug($ally->slug.'-'.$data['name']),
            'type' => InventoryLocationType::Ally,
            'ally_id' => $ally->id,
            'address' => $data['address'] ?? null,
            'active' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ubicación del aliado creada.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Ally $ally): array
    {
        return [
            'id' => $ally->id,
            'uuid' => $ally->uuid,
            'name' => $ally->name,
            'slug' => $ally->slug,
            'type' => $ally->type->value,
            'type_label' => $ally->type->label(),
            'status' => $ally->status->value,
            'status_label' => $ally->status->label(),
            'contact_name' => $ally->contact_name,
            'email' => $ally->email,
            'phone' => $ally->phone,
            'city' => $ally->city,
            'commission_percent' => $ally->commission_percent !== null ? (float) $ally->commission_percent : null,
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'aliado';
        $slug = $base;
        $i = 2;

        while (Ally::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function uniqueLocationSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 80, '') ?: 'ubicacion';
        $slug = $base;
        $i = 2;

        while (InventoryLocation::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
