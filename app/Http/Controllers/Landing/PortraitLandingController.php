<?php

namespace App\Http\Controllers\Landing;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Portrait\PortraitProduct;
use Inertia\Inertia;
use Inertia\Response;

/**
 * /portarretrato — the public explainer of the Portarretrato inteligente.
 * The page links to the store product only when it is really published
 * (`frame_product_url`); otherwise it says the frame is not for sale yet.
 * Never a dead link.
 */
class PortraitLandingController extends Controller
{
    public function __invoke(): Response
    {
        $product = Product::query()
            ->whereIn('slug', PortraitProduct::slugs())
            ->where('active', true)
            ->where('status', ProductStatus::Active->value)
            ->where('type', '!=', ProductType::DigitalPhoto->value)
            ->orderBy('sort_order')
            ->first();

        return Inertia::render('landing/Portrait', [
            'frame_product_url' => $product !== null ? route('store.products.show', $product, false) : null,
        ]);
    }
}
