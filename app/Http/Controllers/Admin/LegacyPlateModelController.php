<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LegacyPlateFieldKey;
use App\Http\Controllers\Controller;
use App\Models\LegacyPlateModel;
use App\Models\LegacyPlateModelField;
use App\Models\Plate;
use App\Support\LegacyPlateLayouts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Legacy Plate layouts — exactly three FRONT designs (Legacy Plate V3).
 * The back of the plate is the stainless money clip: it is shown as a
 * physical reference only and never accepts fields. The Show screen is a
 * visual print editor for the front; the Index also edits the physical
 * specification (size, clip, depth, materials) shared by the layouts.
 */
class LegacyPlateModelController extends Controller
{
    public function index(): Response
    {
        $models = LegacyPlateModel::query()
            ->with('fields')
            ->withCount('plates')
            ->orderByRaw('layout_slot is null, layout_slot')
            ->orderBy('id')
            ->get();

        return Inertia::render('admin/legacy-plate-models/Index', [
            'models' => $models->map(fn (LegacyPlateModel $model) => [
                'id' => $model->id,
                'uuid' => $model->uuid,
                'layout_slot' => $model->layout_slot,
                'name' => $model->name,
                'slug' => $model->slug,
                'sku' => $model->sku,
                'description' => $model->description,
                'width_mm' => (float) $model->width_mm,
                'height_mm' => (float) $model->height_mm,
                'active' => $model->active,
                'plates_count' => $model->plates_count,
                'viewer' => $model->toViewerArray(),
            ]),
            'maxLayouts' => LegacyPlateModel::MAX_LAYOUTS,
            'spec' => $models->first()?->specArray() ?? [...LegacyPlateLayouts::SPEC, 'spec_version' => LegacyPlateLayouts::SPEC_VERSION],
            'producedWithOldSpec' => Plate::query()->whereNotNull('legacy_plate_model_id')->where(fn ($q) => $q->whereNull('layout_version')->orWhere('layout_version', '!=', LegacyPlateLayouts::SPEC_VERSION))->count(),
            'canCreate' => LegacyPlateModel::query()->count() < LegacyPlateModel::MAX_LAYOUTS,
        ]);
    }

    public function show(LegacyPlateModel $legacyPlateModel): Response
    {
        $this->ensureFields($legacyPlateModel);
        $legacyPlateModel->load('fields');

        return Inertia::render('admin/legacy-plate-models/Show', [
            'model' => [
                'id' => $legacyPlateModel->id,
                'layout_slot' => $legacyPlateModel->layout_slot,
                'name' => $legacyPlateModel->name,
                'sku' => $legacyPlateModel->sku,
                'description' => $legacyPlateModel->description,
                'width_mm' => (float) $legacyPlateModel->width_mm,
                'height_mm' => (float) $legacyPlateModel->height_mm,
                'active' => $legacyPlateModel->active,
                ...$legacyPlateModel->toViewerArray(),
            ],
            'fields' => $legacyPlateModel->fields->sortBy('sort_order')->values()->map(fn (LegacyPlateModelField $field) => [
                'id' => $field->id,
                'field_key' => $field->field_key->value,
                'label' => $field->field_key->label(),
                'face' => 'front',
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
        if (LegacyPlateModel::query()->count() >= LegacyPlateModel::MAX_LAYOUTS) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Solo existen tres layouts de Legacy Plate. Edita uno de los existentes.']);

            return back();
        }

        $data = $this->modelAttributes($request);
        $data['uuid'] = (string) Str::uuid();
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['layout_slot'] = collect(range(1, LegacyPlateModel::MAX_LAYOUTS))
            ->diff(LegacyPlateModel::query()->pluck('layout_slot')->filter())
            ->first();
        $data['layout_style'] ??= LegacyPlateLayouts::STYLES[($data['layout_slot'] ?? 1) - 1] ?? 'nucleo';
        $data['spec_version'] = LegacyPlateLayouts::SPEC_VERSION;

        $model = LegacyPlateModel::create($data);
        $this->ensureFields($model);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Layout creado. Ahora acomoda sus campos.']);

        return to_route('admin.legacy-plate-models.show', $model);
    }

    public function update(Request $request, LegacyPlateModel $legacyPlateModel): RedirectResponse
    {
        $data = $this->modelAttributes($request, $legacyPlateModel);

        foreach (['preview_image_path', 'front_artwork_path'] as $column) {
            if (isset($data[$column]) && $legacyPlateModel->{$column}) {
                Storage::disk('public')->delete($legacyPlateModel->{$column});
            }
        }

        foreach (['front_artwork_path' => 'remove_front_artwork'] as $column => $flag) {
            if ($request->boolean($flag) && ! isset($data[$column]) && $legacyPlateModel->{$column}) {
                Storage::disk('public')->delete($legacyPlateModel->{$column});
                $data[$column] = null;
            }
        }

        $legacyPlateModel->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Layout actualizado.']);

        return back();
    }

    /**
     * The physical specification is the same piece for every layout: it is
     * saved on all of them at once. Default V3 = 70 × 45 mm, clip 52 × 20 ×
     * 1 mm, ≈ 6 mm total depth.
     */
    public function updateSpec(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'width_mm' => ['required', 'numeric', 'min:20', 'max:200'],
            'height_mm' => ['required', 'numeric', 'min:20', 'max:200'],
            'clip_length_mm' => ['required', 'numeric', 'min:5', 'lte:width_mm'],
            'clip_height_mm' => ['required', 'numeric', 'min:3', 'lte:height_mm'],
            'clip_thickness_mm' => ['required', 'numeric', 'min:0.2', 'max:5'],
            'total_depth_mm' => ['required', 'numeric', 'min:1', 'max:30'],
            'body_material' => ['required', 'string', 'max:80'],
            'clip_material' => ['required', 'string', 'max:80'],
        ]);

        DB::transaction(function () use ($data) {
            foreach (LegacyPlateModel::query()->get() as $model) {
                $model->update([
                    ...$data,
                    'spec_version' => LegacyPlateLayouts::SPEC_VERSION,
                    'engraving_area' => ['x' => 3, 'y' => 3, 'width' => $data['width_mm'] - 6, 'height' => $data['height_mm'] - 6],
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Especificación física actualizada en los tres layouts. Revisa la posición de los campos si cambiaste el tamaño.']);

        return back();
    }

    /**
     * Bulk-saves every FRONT field (position, size, type, visibility) in one
     * request — what the visual editor sends on "Guardar". There is no back
     * face to save to: the back is the clip.
     */
    public function updateFields(Request $request, LegacyPlateModel $legacyPlateModel): RedirectResponse
    {
        $data = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*.id' => ['required', 'integer', Rule::exists('legacy_plate_model_fields', 'id')->where('legacy_plate_model_id', $legacyPlateModel->id)],
            'fields.*.face' => ['nullable', 'string', Rule::in(['front'])],
            'fields.*.x' => ['required', 'numeric', 'min:0'],
            'fields.*.y' => ['required', 'numeric', 'min:0'],
            'fields.*.width' => ['required', 'numeric', 'min:0.5'],
            'fields.*.height' => ['required', 'numeric', 'min:0.5'],
            'fields.*.font_size' => ['nullable', 'numeric', 'min:0.5', 'max:40'],
            'fields.*.alignment' => ['required', 'string', Rule::in(['left', 'center', 'right'])],
            'fields.*.max_chars' => ['nullable', 'integer', 'min:1'],
            'fields.*.required' => ['boolean'],
            'fields.*.visible' => ['boolean'],
        ]);

        DB::transaction(function () use ($data, $legacyPlateModel) {
            foreach ($data['fields'] as $field) {
                $legacyPlateModel->fields()->whereKey($field['id'])->update([
                    'face' => 'front',
                    'x' => $field['x'], 'y' => $field['y'], 'width' => $field['width'], 'height' => $field['height'],
                    'font_size' => $field['font_size'] ?? null, 'alignment' => $field['alignment'],
                    'max_chars' => $field['max_chars'] ?? null, 'required' => $field['required'] ?? false,
                    'visible' => $field['visible'] ?? true,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Diseño de impresión guardado.']);

        return back();
    }

    /**
     * Every layout carries one row per selectable field so the editor can
     * show/hide any of them — created lazily (never a QR row).
     */
    private function ensureFields(LegacyPlateModel $model): void
    {
        $existing = $model->fields()->pluck('field_key')->map(fn ($key) => $key instanceof LegacyPlateFieldKey ? $key->value : $key)->all();
        $width = (float) $model->width_mm;
        $height = (float) $model->height_mm;
        $sort = (int) $model->fields()->max('sort_order') + 1;

        foreach (LegacyPlateFieldKey::selectable() as $index => $key) {
            if (in_array($key->value, $existing, true)) {
                continue;
            }

            $model->fields()->create([
                'field_key' => $key,
                'face' => $key->defaultFace(),
                'x' => 8,
                'y' => min(4 + ($index % 5) * ($height / 6), $height - 6),
                'width' => max($width - 16, 4),
                'height' => max($height / 7, 4),
                'font_size' => 3.5,
                'alignment' => 'center',
                'required' => false,
                'visible' => in_array($key, [LegacyPlateFieldKey::AthleteName, LegacyPlateFieldKey::OfficialTime, LegacyPlateFieldKey::EventName], true),
                'sort_order' => $sort++,
            ]);
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'layout';
        $slug = $base;
        $i = 2;

        while (LegacyPlateModel::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function modelAttributes(Request $request, ?LegacyPlateModel $model = null): array
    {
        $color = ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $image = ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192'];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:60', Rule::unique('legacy_plate_models', 'sku')->ignore($model?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'width_mm' => ['required', 'numeric', 'min:1'],
            'height_mm' => ['required', 'numeric', 'min:1'],
            'engraving_x' => ['nullable', 'numeric', 'min:0'],
            'engraving_y' => ['nullable', 'numeric', 'min:0'],
            'engraving_width' => ['nullable', 'numeric', 'min:1'],
            'engraving_height' => ['nullable', 'numeric', 'min:1'],
            'active' => ['boolean'],
            'layout_style' => ['nullable', Rule::in(LegacyPlateLayouts::STYLES)],
            'front_background' => $color,
            'front_text_color' => $color,
            'preview_image' => $image,
            'front_artwork' => $image,
        ]);

        $width = (float) $data['width_mm'];
        $height = (float) $data['height_mm'];

        $attributes = [
            'name' => $data['name'],
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'width_mm' => $width,
            'height_mm' => $height,
            'engraving_area' => [
                'x' => $data['engraving_x'] ?? 4,
                'y' => $data['engraving_y'] ?? 4,
                'width' => $data['engraving_width'] ?? $width - 8,
                'height' => $data['engraving_height'] ?? $height - 8,
            ],
            'active' => $data['active'] ?? true,
        ];

        if (! empty($data['layout_style'])) {
            $attributes['layout_style'] = $data['layout_style'];
        }

        foreach (['front_background', 'front_text_color'] as $key) {
            if (! empty($data[$key])) {
                $attributes[$key] = strtoupper($data[$key]);
            }
        }

        $uploads = ['preview_image' => 'preview_image_path', 'front_artwork' => 'front_artwork_path'];
        foreach ($uploads as $input => $column) {
            if ($request->hasFile($input)) {
                $attributes[$column] = $request->file($input)->store('legacy-plate-models', 'public');
            }
        }

        return $attributes;
    }
}
