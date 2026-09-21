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
    /**
     * A single multi-file upload — never unbounded (brief §26: "NO subir
     * 50 archivos indiscriminadamente"). `files[]` is the batch path;
     * `file` still works standalone for any existing single-file caller.
     */
    private const int MAX_FILES_PER_UPLOAD = 10;

    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['nullable', 'file', 'mimes:jpeg,png,webp,mp4,webm', 'max:51200'],
            'files' => ['nullable', 'array', 'max:'.self::MAX_FILES_PER_UPLOAD],
            'files.*' => ['file', 'mimes:jpeg,png,webp,mp4,webm', 'max:51200'],
            'alt_text' => ['nullable', 'string', 'max:150'],
        ]);

        $files = array_filter([$request->file('file'), ...($request->file('files') ?? [])]);
        abort_if($files === [], 422, 'Selecciona al menos un archivo.');

        $disk = (string) config('finisher.product_media.disk', 'product_media');
        $nextSortOrder = ($product->media()->max('sort_order') ?? -1) + 1;
        $hasPrimary = $product->media()->where('is_primary', true)->exists();

        foreach (array_values($files) as $index => $file) {
            $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
            $path = $file->store('products/'.$product->id, $disk);

            $product->media()->create([
                'type' => $isVideo ? ProductMediaType::Video : ProductMediaType::Image,
                'disk' => $disk,
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'sort_order' => $nextSortOrder + $index,
                'is_primary' => ! $hasPrimary && $index === 0,
                'alt_text' => $data['alt_text'] ?? null,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => count($files) > 1 ? count($files).' archivos agregados a la galería.' : 'Archivo agregado a la galería.']);

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
