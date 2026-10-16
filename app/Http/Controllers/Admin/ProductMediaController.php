<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductMediaType;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'model' => ['nullable', 'file', 'max:'.$this->maxModelKilobytes(), $this->modelExtensionRule()],
            'poster' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
            'alt_text' => ['nullable', 'string', 'max:150'],
        ]);

        $files = array_filter([$request->file('file'), ...($request->file('files') ?? [])]);
        $model = $request->file('model');
        abort_if($files === [] && $model === null, 422, 'Selecciona al menos un archivo.');

        $disk = $this->disk();
        $nextSortOrder = ($product->media()->max('sort_order') ?? -1) + 1;
        $hasPrimary = $product->media()->where('is_primary', true)->exists();

        foreach (array_values($files) as $index => $file) {
            $type = str_starts_with((string) $file->getMimeType(), 'video/') ? ProductMediaType::Video : ProductMediaType::Image;

            $product->media()->create([
                'type' => $type,
                'disk' => $disk,
                'path' => $file->store($this->folder($product, $type), $disk),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'sort_order' => $nextSortOrder + $index,
                'is_primary' => ! $hasPrimary && $index === 0 && $type === ProductMediaType::Image,
                'alt_text' => $data['alt_text'] ?? null,
            ]);
        }

        if ($model instanceof UploadedFile) {
            $extension = strtolower($model->getClientOriginalExtension());

            $product->media()->create([
                'type' => ProductMediaType::Model3d,
                'disk' => $disk,
                'path' => $model->storeAs($this->folder($product, ProductMediaType::Model3d), Str::uuid().'.'.$extension, $disk),
                'mime' => $extension === 'glb' ? 'model/gltf-binary' : 'model/gltf+json',
                'size' => $model->getSize(),
                'sort_order' => $nextSortOrder + count($files),
                'is_primary' => false,
                'alt_text' => $data['alt_text'] ?? null,
                'poster_path' => $request->hasFile('poster')
                    ? $request->file('poster')->store($this->folder($product, ProductMediaType::Image), $disk)
                    : null,
            ]);
        }

        $count = count($files) + ($model !== null ? 1 : 0);
        Inertia::flash('toast', ['type' => 'success', 'message' => $count > 1 ? $count.' archivos agregados a la galería.' : 'Archivo agregado a la galería.']);

        return back();
    }

    /**
     * Poster (still frame) for a video or 3D model — what the store shows
     * before the heavy file loads (spec §22).
     */
    public function poster(Request $request, ProductMedia $media): RedirectResponse
    {
        $request->validate(['poster' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:4096']]);
        abort_if($media->type === ProductMediaType::Image, 422, 'Las imágenes no llevan póster.');

        $old = $media->poster_path;
        $media->update([
            'poster_path' => $request->file('poster')->store($this->folder($media->product, ProductMediaType::Image), $media->disk),
        ]);

        if ($old !== null) {
            Storage::disk($media->disk)->delete($old);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Póster actualizado.']);

        return back();
    }

    private function disk(): string
    {
        return (string) config('finisher.product_media.disk', 'product_media');
    }

    /**
     * `products/{product_uuid}/images|videos|models3d` (spec §65) — the
     * uuid is the identity; old rows keep whatever path they had.
     */
    private function folder(Product $product, ProductMediaType $type): string
    {
        return 'products/'.($product->uuid ?? $product->id).'/'.$type->folder();
    }

    private function maxModelKilobytes(): int
    {
        return (int) config('finisher.product_media.max_model_kb', 30 * 1024);
    }

    /**
     * GLB/GLTF have no reliable server-side MIME (often
     * application/octet-stream), so the extension is checked explicitly;
     * a GLB must also start with the binary glTF magic bytes.
     */
    private function modelExtensionRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $extension = strtolower($value->getClientOriginalExtension());

            if (! in_array($extension, ['glb', 'gltf'], true)) {
                $fail('El modelo 3D debe ser un archivo .glb o .gltf.');

                return;
            }

            $head = (string) @file_get_contents($value->getRealPath(), false, null, 0, 4);

            if ($extension === 'glb' && $head !== 'glTF') {
                $fail('El archivo .glb no es un modelo glTF válido.');
            }

            if ($extension === 'gltf' && json_decode((string) file_get_contents($value->getRealPath()), true) === null) {
                $fail('El archivo .gltf no es un JSON válido.');
            }
        };
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

    /**
     * Alt text and the "hover" role (the image a product card swaps to
     * on hover) — at most one hover image per product.
     */
    public function update(Request $request, ProductMedia $media): RedirectResponse
    {
        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:150'],
            'is_hover' => ['sometimes', 'boolean'],
        ]);

        DB::transaction(function () use ($media, $data) {
            if (($data['is_hover'] ?? false) === true) {
                $media->product->media()->whereKeyNot($media->id)->update(['is_hover' => false]);
            }

            $media->update($data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Imagen actualizada.']);

        return back();
    }

    /**
     * Swaps the file behind an existing gallery item, keeping its order,
     * primary/hover flags and alt text — the old file is removed from
     * storage only after the new one is safely written.
     */
    public function replace(Request $request, ProductMedia $media): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,png,webp,mp4,webm', 'max:51200'],
        ]);

        $file = $request->file('file');
        $oldPaths = array_filter([$media->path, $media->poster_path]);
        $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');

        $media->update([
            'type' => $isVideo ? ProductMediaType::Video : ProductMediaType::Image,
            'path' => $file->store($this->folder($media->product, $isVideo ? ProductMediaType::Video : ProductMediaType::Image), $media->disk),
            'poster_path' => null,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        Storage::disk($media->disk)->delete($oldPaths);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Archivo reemplazado.']);

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
