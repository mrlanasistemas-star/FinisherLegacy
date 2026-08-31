<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegacyPlateModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalog + "Personalización" in one screen (brief §12-§13): the pieces
 * arrive pre-manufactured, so there is no Figma-style design surface here
 * — just the model's real photo, its engraving_area, and simple numeric
 * inputs for where each of the 5 whitelisted dynamic fields sits. Never
 * writes decoration/background — that physically already exists on the
 * piece.
 */
class LegacyPlateModelController extends Controller
{
    public function index(): Response
    {
        $models = LegacyPlateModel::query()->withCount('plates')->orderBy('name')->get();

        return Inertia::render('admin/legacy-plate-models/Index', [
            'models' => $models->map(fn (LegacyPlateModel $model) => [
                'id' => $model->id,
                'uuid' => $model->uuid,
                'name' => $model->name,
                'slug' => $model->slug,
                'sku' => $model->sku,
                'description' => $model->description,
                'width_mm' => (float) $model->width_mm,
                'height_mm' => (float) $model->height_mm,
                'active' => $model->active,
                'preview_image_url' => $model->preview_image_path ? Storage::disk('public')->url($model->preview_image_path) : null,
                'plates_count' => $model->plates_count,
            ]),
        ]);
    }

    public function show(LegacyPlateModel $legacyPlateModel): Response
    {
        $legacyPlateModel->loadMissing('fields');

        return Inertia::render('admin/legacy-plate-models/Show', [
            'model' => [
                'id' => $legacyPlateModel->id,
                'name' => $legacyPlateModel->name,
                'slug' => $legacyPlateModel->slug,
                'sku' => $legacyPlateModel->sku,
                'description' => $legacyPlateModel->description,
                'width_mm' => (float) $legacyPlateModel->width_mm,
                'height_mm' => (float) $legacyPlateModel->height_mm,
                'engraving_area' => $legacyPlateModel->engraving_area,
                'active' => $legacyPlateModel->active,
                'preview_image_url' => $legacyPlateModel->preview_image_path ? Storage::disk('public')->url($legacyPlateModel->preview_image_path) : null,
            ],
            'fields' => $legacyPlateModel->fields->map(fn ($field) => [
                'id' => $field->id,
                'field_key' => $field->field_key->value,
                'x' => (float) $field->x,
                'y' => (float) $field->y,
                'width' => (float) $field->width,
                'height' => (float) $field->height,
                'font_size' => $field->font_size !== null ? (float) $field->font_size : null,
                'alignment' => $field->alignment->value,
                'max_chars' => $field->max_chars,
                'required' => $field->required,
                'visible' => $field->visible,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->modelAttributes($request);
        $data['uuid'] = (string) Str::uuid();
        $data['slug'] = Str::slug($data['name']);

        LegacyPlateModel::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Modelo creado.']);

        return back();
    }

    public function update(Request $request, LegacyPlateModel $legacyPlateModel): RedirectResponse
    {
        $legacyPlateModel->update($this->modelAttributes($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Modelo actualizado.']);

        return back();
    }

    /**
     * Bulk-saves every field's position/size in one request — the
     * "Personalización" surface (brief §13/§15).
     */
    public function updateFields(Request $request, LegacyPlateModel $legacyPlateModel): RedirectResponse
    {
        $data = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*.id' => ['required', 'integer', 'exists:legacy_plate_model_fields,id'],
            'fields.*.x' => ['required', 'numeric'],
            'fields.*.y' => ['required', 'numeric'],
            'fields.*.width' => ['required', 'numeric', 'min:0.1'],
            'fields.*.height' => ['required', 'numeric', 'min:0.1'],
            'fields.*.font_size' => ['nullable', 'numeric', 'min:0.1'],
            'fields.*.alignment' => ['required', 'string', Rule::in(['left', 'center', 'right'])],
            'fields.*.max_chars' => ['nullable', 'integer', 'min:1'],
            'fields.*.required' => ['boolean'],
            'fields.*.visible' => ['boolean'],
        ]);

        DB::transaction(function () use ($data, $legacyPlateModel) {
            foreach ($data['fields'] as $field) {
                $legacyPlateModel->fields()->whereKey($field['id'])->update([
                    'x' => $field['x'], 'y' => $field['y'], 'width' => $field['width'], 'height' => $field['height'],
                    'font_size' => $field['font_size'] ?? null, 'alignment' => $field['alignment'],
                    'max_chars' => $field['max_chars'] ?? null, 'required' => $field['required'] ?? false,
                    'visible' => $field['visible'] ?? true,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Zona de grabado actualizada.']);

        return back();
    }

    /**
     * Validates the raw form input, then shapes it into exactly
     * LegacyPlateModel's fillable attributes — never passes
     * engraving_x/y/width/height or the raw `preview_image` file through
     * to mass assignment.
     *
     * @return array<string, mixed>
     */
    private function modelAttributes(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:500'],
            'width_mm' => ['required', 'numeric', 'min:1'],
            'height_mm' => ['required', 'numeric', 'min:1'],
            'engraving_x' => ['required', 'numeric', 'min:0'],
            'engraving_y' => ['required', 'numeric', 'min:0'],
            'engraving_width' => ['required', 'numeric', 'min:1'],
            'engraving_height' => ['required', 'numeric', 'min:1'],
            'active' => ['boolean'],
            'preview_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:4096'],
        ]);

        $attributes = [
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'width_mm' => $data['width_mm'],
            'height_mm' => $data['height_mm'],
            'engraving_area' => [
                'x' => $data['engraving_x'],
                'y' => $data['engraving_y'],
                'width' => $data['engraving_width'],
                'height' => $data['engraving_height'],
            ],
            'active' => $data['active'] ?? true,
        ];

        if ($request->hasFile('preview_image')) {
            $attributes['preview_image_path'] = $request->file('preview_image')->store('legacy-plate-models', 'public');
        }

        return $attributes;
    }
}
