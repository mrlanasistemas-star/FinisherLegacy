<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductMediaType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Store V2 product gallery (product UX consolidation brief §106-§110) —
 * images and video, reorderable, one primary. Additive to
 * Product.image_path, never replaces it (brief §107).
 */
class ProductMediaController extends Controller
{
    private const DISK = 'public';

    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,png,webp,mp4,webm', 'max:51200'],
            'alt_text' => ['nullable', 'string', 'max:150'],
        ]);

        $file = $request->file('file');
        $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
        $path = $file->store('products/'.$product->id, self::DISK);

        $product->media()->create([
            'type' => $isVideo ? ProductMediaType::Video : ProductMediaType::Image,
            'disk' => self::DISK,
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'sort_order' => ($product->media()->max('sort_order') ?? -1) + 1,
            'is_primary' => $product->media()->count() === 0,
            'alt_text' => $data['alt_text'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Archivo agregado a la galería.']);

        return back();
    }

    public function reorder(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:product_media,id'],
        ]);

        DB::transaction(function () use ($data, $product) {
            foreach ($data['order'] as $index => $mediaId) {
                $product->media()->whereKey($mediaId)->update(['sort_order' => $index]);
            }
        });

        return back();
    }

    public function setPrimary(ProductMedia $media): RedirectResponse
    {
        DB::transaction(function () use ($media) {
            $media->product->media()->update(['is_primary' => false]);
            $media->update(['is_primary' => true]);
        });

        return back();
    }

    public function destroy(ProductMedia $media): RedirectResponse
    {
        Storage::disk($media->disk)->delete(array_filter([$media->path, $media->poster_path]));
        $wasPrimary = $media->is_primary;
        $product = $media->product;
        $media->delete();

        // Never leave a gallery with media but no primary — the next item
        // (if any) inherits it.
        if ($wasPrimary) {
            $product->media()->first()?->update(['is_primary' => true]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Archivo eliminado.']);

        return back();
    }
}
