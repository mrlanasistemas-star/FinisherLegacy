<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanyGalleryItem;
use App\Models\CompanyMilestone;
use App\Models\CompanySetting;
use App\Services\ImageProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Contenido: the company information, "Nuestro camino"
 * timeline and gallery behind the public Nosotros/Contacto pages. A small
 * purpose-built editor, not a CMS. Images are optimized through the same
 * ImageProcessingService as every other upload and stored on the public
 * disk (never base64 in the database).
 */
class CompanyContentController extends Controller
{
    public function __construct(private readonly ImageProcessingService $images) {}

    public function index(): Response
    {
        $saved = CompanySetting::query()->pluck('value', 'key');

        return Inertia::render('admin/content/Index', [
            'fields' => collect(CompanySetting::FIELDS)->map(fn (array $field, string $key) => [
                'key' => $key,
                'label' => $field['label'],
                'group' => $field['group'],
                'type' => $field['type'],
                'value' => $saved[$key] ?? '',
                'placeholder' => CompanySetting::DEFAULTS[$key] ?? '',
            ])->values(),
            'milestones' => CompanyMilestone::query()->ordered()->get()->map(fn (CompanyMilestone $m) => [
                'id' => $m->id,
                'period' => $m->period,
                'title' => $m->title,
                'description' => $m->description,
                'location' => $m->location,
                'image_url' => $m->imageUrl(),
                'sort_order' => $m->sort_order,
                'is_visible' => $m->is_visible,
            ]),
            'gallery' => CompanyGalleryItem::query()->ordered()->get()->map(fn (CompanyGalleryItem $item) => [
                'id' => $item->id,
                'image_url' => $item->imageUrl(),
                'title' => $item->title,
                'description' => $item->description,
                'sort_order' => $item->sort_order,
                'is_visible' => $item->is_visible,
            ]),
            'publicUrl' => route('about'),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (CompanySetting::FIELDS as $key => $field) {
            $rules["settings.{$key}"] = match ($field['type']) {
                'email' => ['nullable', 'email', 'max:190'],
                'url' => ['nullable', 'url:https,http', 'max:255'],
                'textarea' => ['nullable', 'string', 'max:3000'],
                default => ['nullable', 'string', 'max:190'],
            };
        }

        $data = $request->validate($rules);

        CompanySetting::store($data['settings'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Información de la empresa actualizada.']);

        return back();
    }

    public function storeMilestone(Request $request): RedirectResponse
    {
        $data = $this->validatedMilestone($request);
        $data['sort_order'] ??= (int) CompanyMilestone::query()->max('sort_order') + 1;
        $data['image_path'] = $request->hasFile('image') ? $this->storeImage($request->file('image'), 'company/milestones') : null;
        unset($data['image']);

        CompanyMilestone::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hito agregado a la trayectoria.']);

        return back();
    }

    public function updateMilestone(Request $request, CompanyMilestone $milestone): RedirectResponse
    {
        $data = $this->validatedMilestone($request);
        $data['sort_order'] ??= $milestone->sort_order;
        unset($data['image']);

        if ($request->hasFile('image')) {
            $old = $milestone->image_path;
            $data['image_path'] = $this->storeImage($request->file('image'), 'company/milestones');
            $this->deleteImage($old);
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($milestone->image_path);
            $data['image_path'] = null;
        }

        $milestone->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hito actualizado.']);

        return back();
    }

    public function destroyMilestone(CompanyMilestone $milestone): RedirectResponse
    {
        $this->deleteImage($milestone->image_path);
        $milestone->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hito eliminado.']);

        return back();
    }

    public function storeGalleryItems(Request $request): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:12'],
            'images.*' => ['image', 'mimes:jpeg,png,webp', 'max:'.config('finisher.image.max_original_kb')],
        ]);

        $next = (int) CompanyGalleryItem::query()->max('sort_order') + 1;

        foreach ($request->file('images') as $index => $file) {
            $processed = $this->images->process($file, 'company/gallery', withThumbnail: false);
            $this->deleteImage($processed['original_path']);

            CompanyGalleryItem::create([
                'image_path' => $processed['display_path'],
                'width' => $processed['width'],
                'height' => $processed['height'],
                'sort_order' => $next + $index,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fotografías agregadas a la galería.']);

        return back();
    }

    public function updateGalleryItem(Request $request, CompanyGalleryItem $item): RedirectResponse
    {
        $item->update($request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'is_visible' => ['boolean'],
        ]));

        return back();
    }

    public function destroyGalleryItem(CompanyGalleryItem $item): RedirectResponse
    {
        $this->deleteImage($item->image_path);
        $item->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fotografía eliminada.']);

        return back();
    }

    public function reorderMilestones(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:company_milestones,id'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['order'] as $index => $id) {
                CompanyMilestone::query()->whereKey($id)->update(['sort_order' => $index]);
            }
        });

        return back();
    }

    /** @return array<string, mixed> */
    private function validatedMilestone(Request $request): array
    {
        return $request->validate([
            'period' => ['required', 'string', 'max:40'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:150'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
            'is_visible' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:'.config('finisher.image.max_original_kb')],
        ]);
    }

    private function storeImage(UploadedFile $file, string $directory): string
    {
        $processed = $this->images->process($file, $directory, withThumbnail: false);
        $this->deleteImage($processed['original_path']);

        return $processed['display_path'];
    }

    private function deleteImage(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }
}
