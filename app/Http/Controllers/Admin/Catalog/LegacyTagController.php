<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Actions\LegacyTags\ManageTag;
use App\Enums\LegacyTagStatus;
use App\Enums\SalesChannel;
use App\Enums\TagAccessSource;
use App\Enums\TagScanEvent;
use App\Exceptions\ProductOutOfStockException;
use App\Http\Controllers\Controller;
use App\Models\Ally;
use App\Models\Collection;
use App\Models\InventoryLocation;
use App\Models\InventoryLot;
use App\Models\LegacyTag;
use App\Models\LegacyTagEvent;
use App\Models\LegacyTagScan;
use App\Models\ProductVariant;
use App\Models\SupportCase;
use App\Services\LegacyTags\TagLifecycle;
use App\Services\QrCodeService;
use App\Support\LegacyTags\TagCode;
use App\Support\LegacyTags\TagUrls;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * Legacy → Tags: the per-unit registry (one row per physical piece) and the
 * full traceability of one unit. Every state change goes through
 * App\Actions\LegacyTags\ManageTag / TagLifecycle — never assigned here.
 */
class LegacyTagController extends Controller
{
    private const array SORTS = ['id', 'serial', 'status', 'sold_at', 'activated_at', 'received_at'];

    private const array DATE_FIELDS = ['created_at', 'received_at', 'sold_at', 'activated_at'];

    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $tags = $this->query($filters)
            ->with([
                'productVariant:id,sku,name,product_id', 'productVariant.product:id,name',
                'location:id,name', 'ally:id,name', 'order:id,uuid,order_number', 'owner:id,full_name,email',
            ])
            ->orderBy($filters['sort'], $filters['dir'])
            ->orderBy('id', $filters['dir'])
            ->paginate(30)->withQueryString()
            ->through(fn (LegacyTag $t) => [
                'uuid' => $t->uuid,
                'serial' => $t->serial,
                'display_code' => $t->displayCode(),
                'status' => $t->status->value,
                'sku' => $t->productVariant?->sku,
                'design' => $t->productVariant?->product?->name,
                'location' => $t->location?->name,
                'channel' => $t->sales_channel?->label(),
                'ally' => $t->ally?->name,
                'order' => $t->order ? ['uuid' => $t->order->uuid, 'number' => $t->order->order_number] : null,
                'owner' => $t->owner ? ['id' => $t->owner->id, 'name' => $t->owner->full_name, 'email' => $t->owner->email] : null,
                'activated_at' => $t->activated_at?->toIso8601String(),
            ]);

        $counts = LegacyTag::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('admin/tags/Index', [
            'tags' => $tags,
            'filters' => $filters,
            'counts' => $counts,
            'options' => [
                'statuses' => LegacyTagStatus::options(),
                'channels' => SalesChannel::options(),
                'variants' => ProductVariant::query()->whereHas('product', fn ($q) => $q->where('tracks_units', true))
                    ->with('product:id,name')->orderBy('sku')->limit(1000)->get(['id', 'sku', 'name', 'product_id'])
                    ->map(fn (ProductVariant $v) => ['value' => $v->id, 'label' => $v->sku, 'description' => $v->product?->name])->all(),
                'collections' => Collection::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
                'locations' => InventoryLocation::query()->orderBy('name')->get(['id', 'name'])->map(fn ($l) => ['value' => $l->id, 'label' => $l->name])->all(),
                'lots' => InventoryLot::query()->latest('id')->limit(300)->get(['id', 'code'])->map(fn ($l) => ['value' => $l->id, 'label' => $l->code])->all(),
                'allies' => Ally::query()->orderBy('name')->limit(500)->get(['id', 'name'])->map(fn ($a) => ['value' => $a->id, 'label' => $a->name])->all(),
            ],
        ]);
    }

    public function show(Request $request, LegacyTag $tag): Response
    {
        $tag->load([
            'productVariant.product.collection:id,name',
            'lot.supplier:id,name', 'location:id,name,type', 'ally:id,name',
            'order.seller:id,first_name,last_name', 'owner:id,full_name,email',
            'story:id,public_id,legacy_tag_id,title,visibility,completed_at',
            'replaces:id,uuid,serial', 'replacement:id,uuid,serial,replaces_tag_id',
        ]);

        $events = LegacyTagEvent::query()->where('legacy_tag_id', $tag->id)
            ->with(['fromLocation:id,name', 'toLocation:id,name', 'actor:id,first_name,last_name', 'order:id,uuid,order_number'])
            ->orderByDesc('id')->limit(200)->get();

        $opens = LegacyTagScan::query()->toBase()->where('legacy_tag_id', $tag->id)
            ->where('event', TagScanEvent::TagOpened->value)
            ->selectRaw('source, count(*) as c, max(created_at) as last_at')
            ->groupBy('source')->get();

        $supportRoute = Route::has('admin.support.show') ? 'admin.support.show' : null;
        $cases = SupportCase::query()->where('legacy_tag_id', $tag->id)->orWhere('replacement_tag_id', $tag->id)->latest('id')->limit(20)->get();

        return Inertia::render('admin/tags/Show', [
            'tag' => [
                'uuid' => $tag->uuid,
                'serial' => $tag->serial,
                'public_code' => $tag->public_code,
                'display_code' => $tag->displayCode(),
                'status' => $tag->status->value,
                'nfc_url' => TagUrls::nfc($tag),
                'qr_url' => TagUrls::qr($tag),
                'disabled_reason' => $tag->disabled_reason,
                'notes' => $tag->notes,
                'is_sold' => $tag->status->isSold(),
                'is_terminal' => $tag->status->isTerminal(),
                'product' => $tag->productVariant?->product ? ['id' => $tag->productVariant->product->id, 'name' => $tag->productVariant->product->name, 'collection' => $tag->productVariant->product->collection?->name] : null,
                'sku' => $tag->productVariant?->sku,
                'variant' => $tag->productVariant?->name,
                'lot' => $tag->lot ? ['uuid' => $tag->lot->uuid, 'code' => $tag->lot->code, 'supplier' => $tag->lot->supplier?->name] : null,
                'location' => $tag->location ? ['id' => $tag->location->id, 'name' => $tag->location->name, 'type' => $tag->location->type->label()] : null,
                'channel' => $tag->sales_channel?->label(),
                'ally' => $tag->ally?->name,
                'order' => $tag->order ? [
                    'uuid' => $tag->order->uuid,
                    'number' => $tag->order->order_number,
                    'seller' => $tag->order->seller?->name,
                    'delivery_method' => $tag->order->delivery_method?->label(),
                ] : null,
                'owner' => $tag->owner ? ['id' => $tag->owner->id, 'name' => $tag->owner->full_name, 'email' => $tag->owner->email] : null,
                'story' => $tag->story ? [
                    'public_id' => $tag->story->public_id,
                    'title' => $tag->story->title,
                    'visibility' => $tag->story->visibility->value,
                    'url' => route('legacy.story.show', $tag->story->public_id),
                ] : null,
                'replaces' => $tag->replaces?->only(['uuid', 'serial']),
                'replacement' => $tag->replacement?->only(['uuid', 'serial']),
                'dates' => [
                    'created_at' => $tag->created_at->toIso8601String(),
                    'received_at' => $tag->received_at?->toIso8601String(),
                    'sold_at' => $tag->sold_at?->toIso8601String(),
                    'delivered_at' => $tag->delivered_at?->toIso8601String(),
                    'activated_at' => $tag->activated_at?->toIso8601String(),
                    'first_opened_at' => $tag->first_opened_at?->toIso8601String(),
                    'last_opened_at' => $tag->last_opened_at?->toIso8601String(),
                ],
                'open_count' => $tag->open_count,
            ],
            'events' => $events->map(fn (LegacyTagEvent $e) => [
                'id' => $e->id,
                'type' => $e->type->value,
                'label' => $e->type->label(),
                'from_status' => $e->from_status?->value,
                'to_status' => $e->to_status?->value,
                'from_location' => $e->fromLocation?->name,
                'to_location' => $e->toLocation?->name,
                'order' => $e->order ? ['uuid' => $e->order->uuid, 'number' => $e->order->order_number] : null,
                'actor' => $e->actor?->name,
                'notes' => $e->notes,
                'created_at' => $e->created_at->toIso8601String(),
            ])->values(),
            'scans' => collect(TagAccessSource::cases())->map(fn (TagAccessSource $s) => [
                'source' => $s->value,
                'label' => $s->label(),
                'count' => (int) ($opens->firstWhere('source', $s->value)->c ?? 0),
                'last_at' => ($last = $opens->firstWhere('source', $s->value)?->last_at) ? Carbon::parse($last)->toIso8601String() : null,
            ])->values(),
            'supportCases' => $cases->map(fn (SupportCase $c) => [
                'uuid' => $c->uuid,
                'number' => $c->number,
                'subject' => $c->subject,
                'type' => $c->type->label(),
                'status' => $c->status->label(),
                'created_at' => $c->created_at->toIso8601String(),
                'url' => $supportRoute ? route($supportRoute, $c->uuid) : null,
            ])->values(),
            'locations' => InventoryLocation::query()->where('active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($l) => ['value' => $l->id, 'label' => $l->name])->all(),
            'can' => ['manage' => $request->user()->can('tags.manage')],
        ]);
    }

    public function qr(LegacyTag $tag, QrCodeService $qr): HttpResponse
    {
        return response($qr->svg(TagUrls::qr($tag), 320), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function disable(Request $request, LegacyTag $tag, ManageTag $manage): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return $this->run(fn () => $manage->disable($tag, $request->user(), $data['reason']), 'Pieza desactivada.');
    }

    public function enable(Request $request, LegacyTag $tag, ManageTag $manage): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return $this->run(function () use ($tag, $request, $manage, $data) {
            DB::transaction(function () use ($tag, $request, $manage, $data) {
                $tag = $manage->enable($tag, $request->user());
                app(TagLifecycle::class)->note($tag, 'Reactivación: '.$data['reason'], $request->user());
            });
        }, 'Pieza reactivada.');
    }

    public function replace(Request $request, LegacyTag $tag, ManageTag $manage): RedirectResponse
    {
        $data = $request->validate([
            'replacement' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        $replacement = $this->findByCodeOrSerial($data['replacement']);

        if ($replacement === null) {
            return back()->withErrors(['replacement' => 'No encontramos esa pieza. Escanea o escribe su código o serie.']);
        }

        if ($replacement->id === $tag->id) {
            return back()->withErrors(['replacement' => 'La pieza de reemplazo debe ser otra.']);
        }

        return $this->run(fn () => $manage->replace($tag, $replacement, $request->user(), $data['reason']), "Pieza reemplazada por {$replacement->serial}.", 'replacement');
    }

    public function returnTag(Request $request, LegacyTag $tag, ManageTag $manage): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'restock_location_id' => ['nullable', 'integer', Rule::exists('inventory_locations', 'id')->where('active', true)],
        ]);

        $restock = isset($data['restock_location_id']) ? InventoryLocation::query()->whereKey($data['restock_location_id'])->first() : null;

        return $this->run(fn () => $manage->return($tag, $request->user(), $data['reason'], $restock), $restock ? 'Devolución registrada y pieza de vuelta en inventario.' : 'Devolución registrada.');
    }

    public function revoke(Request $request, LegacyTag $tag, ManageTag $manage): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return $this->run(fn () => $manage->revokeOwnership($tag, $request->user(), $data['reason']), 'Propiedad revocada.');
    }

    public function note(Request $request, LegacyTag $tag, TagLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'min:2', 'max:500']]);

        DB::transaction(function () use ($tag, $lifecycle, $data, $request) {
            $locked = LegacyTag::query()->lockForUpdate()->findOrFail($tag->id);
            $lifecycle->note($locked, $data['notes'], $request->user());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Nota agregada al historial.']);

        return back();
    }

    private function run(callable $action, string $message, string $errorKey = 'reason'): RedirectResponse
    {
        try {
            $action();
        } catch (InvalidArgumentException $e) {
            return back()->withErrors([$errorKey => $e->getMessage()]);
        } catch (ProductOutOfStockException) {
            return back()->withErrors([$errorKey => 'El stock registrado en esa ubicación no alcanza; revisa el inventario.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    private function findByCodeOrSerial(string $input): ?LegacyTag
    {
        $serial = strtoupper(trim($input));
        $code = TagCode::normalize($input);

        return LegacyTag::query()
            ->where('serial', $serial)
            ->when($code !== null, fn ($q) => $q->orWhere('public_code', $code))
            ->first();
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $str = fn (string $key) => ($v = trim($request->string($key)->toString())) === '' ? null : $v;
        $int = fn (string $key) => $request->integer($key) ?: null;

        return [
            'q' => $str('q'),
            'status' => LegacyTagStatus::tryFrom((string) $str('status'))?->value,
            'variant' => $int('variant'),
            'collection' => $int('collection'),
            'location' => $int('location'),
            'lot' => $int('lot'),
            'channel' => SalesChannel::tryFrom((string) $str('channel'))?->value,
            'ally' => $int('ally'),
            'activated' => in_array($str('activated'), ['si', 'no'], true) ? $str('activated') : null,
            'date_field' => in_array($str('date_field'), self::DATE_FIELDS, true) ? $str('date_field') : 'sold_at',
            'from' => $this->date($str('from')),
            'to' => $this->date($str('to')),
            'sort' => in_array($str('sort'), self::SORTS, true) ? $str('sort') : 'id',
            'dir' => $str('dir') === 'asc' ? 'asc' : 'desc',
        ];
    }

    private function date(?string $value): ?string
    {
        return $value !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<LegacyTag>
     */
    private function query(array $f): Builder
    {
        return LegacyTag::query()
            ->when($f['q'], function (Builder $q, string $search) {
                $code = TagCode::normalize($search);
                $q->where(function (Builder $w) use ($search, $code) {
                    $w->where('serial', 'like', '%'.strtoupper($search).'%')
                        ->when($code !== null, fn ($x) => $x->orWhere('public_code', $code))
                        ->orWhereHas('order', fn ($o) => $o->where('order_number', $search))
                        ->orWhereHas('owner', fn ($o) => $o->where('email', $search));
                });
            })
            ->when($f['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($f['variant'], fn ($q, $v) => $q->where('product_variant_id', $v))
            ->when($f['collection'], fn ($q, $v) => $q->whereHas('productVariant.product', fn ($p) => $p->where('collection_id', $v)))
            ->when($f['location'], fn ($q, $v) => $q->where('inventory_location_id', $v))
            ->when($f['lot'], fn ($q, $v) => $q->where('inventory_lot_id', $v))
            ->when($f['channel'], fn ($q, $v) => $q->where('sales_channel', $v))
            ->when($f['ally'], fn ($q, $v) => $q->where('ally_id', $v))
            ->when($f['activated'] === 'si', fn ($q) => $q->whereNotNull('activated_at'))
            ->when($f['activated'] === 'no', fn ($q) => $q->whereNull('activated_at'))
            ->when($f['from'], fn ($q, $v) => $q->where($f['date_field'], '>=', $v.' 00:00:00'))
            ->when($f['to'], fn ($q, $v) => $q->where($f['date_field'], '<=', $v.' 23:59:59'));
    }
}
