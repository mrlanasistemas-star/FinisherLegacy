<?php

namespace App\Http\Controllers\Admin\LegacyNfc;

use App\Actions\Nfc\AssignNfcTag;
use App\Enums\NfcTargetType;
use App\Exceptions\Nfc\NfcTagInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nfc\ConnectProductRequest;
use App\Models\NfcAssignment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Legacy + NFC → "Productos conectados": every product unit that
 * carries an NFC tag, connected or still waiting for its owner, plus the
 * "Conectar producto" action.
 */
class ConnectedProductController extends Controller
{
    public function index(Request $request): Response
    {
        $state = $request->string('state')->toString();

        $assignments = NfcAssignment::query()
            ->open()
            ->where('target_type', NfcTargetType::Product)
            ->when($state === 'connected', fn (Builder $q) => $q->whereNotNull('activated_at'))
            ->when($state === 'waiting', fn (Builder $q) => $q->whereNull('activated_at'))
            ->with(['tag', 'product.media', 'productVariant', 'athlete', 'ownedProduct'])
            ->latest('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (NfcAssignment $a) => [
                'id' => $a->id,
                'tag' => $a->tag?->shortToken(),
                'product' => $a->product?->name,
                'variant' => $a->productVariant?->name,
                'image_url' => $a->product?->primaryImageUrl(),
                'owner' => $a->athlete?->full_name,
                'connected' => $a->isClaimed(),
                'connected_at' => $a->activated_at?->toIso8601String(),
                'assigned_at' => $a->assigned_at->toIso8601String(),
                'last_tapped_at' => $a->tag?->last_tapped_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/legacy-nfc/ConnectedProducts', [
            'items' => $assignments,
            'filters' => ['state' => $state ?: null],
            'products' => Product::query()->orderBy('name')->limit(200)->get(['id', 'name'])
                ->map(fn (Product $p) => ['value' => $p->id, 'label' => $p->name]),
            'variants' => ProductVariant::query()->whereIn('product_id', Product::query()->select('id'))->orderBy('name')->limit(1000)->get(['id', 'product_id', 'name'])
                ->map(fn (ProductVariant $v) => ['value' => $v->id, 'label' => $v->name, 'product_id' => $v->product_id]),
            'can' => ['manage' => (bool) $request->user()->can('nfc.manage')],
        ]);
    }

    public function store(ConnectProductRequest $request, AssignNfcTag $assign): RedirectResponse
    {
        $tag = $request->resolveTag();
        $owned = $request->ownedProduct();

        try {
            $owned !== null
                ? $assign->toOwnedProduct($tag, $owned, $request->user())
                : $assign->toProduct($tag, Product::query()->findOrFail($request->integer('product_id')), $request->variant(), $request->user());
        } catch (NfcTagInUseException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $owned !== null
            ? 'Producto conectado a su dueño.'
            : 'Etiqueta lista: quien la toque primero podrá conectarla a su Legacy.']);

        return back();
    }
}
