<?php

namespace App\Http\Controllers\Quotas;

use App\Actions\Quotas\CheckoutPhotoExpansion;
use App\Enums\StoryQuotaKind;
use App\Http\Controllers\Controller;
use App\Models\LegacyStory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Owner buys more space for a story — photos, videos or voice messages
 * (`kind`, default photos) — or renews a prepaid term (same call with the
 * pack of the plan): creates a pending Order and sends the owner to the
 * regular order page to pay. Nothing is granted here — only a
 * server-confirmed payment grants the capacity.
 */
class PhotoExpansionCheckoutController extends Controller
{
    public function __invoke(Request $request, LegacyStory $story, CheckoutPhotoExpansion $checkout): RedirectResponse
    {
        abort_unless($story->isOwnedBy($request->user()), 404);

        $data = $request->validate([
            'pack' => ['nullable', 'integer'],
            'kind' => ['nullable', Rule::enum(StoryQuotaKind::class)],
        ]);

        $kind = StoryQuotaKind::tryFrom((string) ($data['kind'] ?? '')) ?? StoryQuotaKind::Photos;
        $order = $checkout->handle($request->user(), $story, isset($data['pack']) ? (int) $data['pack'] : null, $kind);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tu pedido está listo: completa el pago para ampliar el espacio de tu recuerdo.']);

        return to_route('store.orders.show', $order);
    }
}
