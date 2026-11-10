<?php

namespace App\Http\Controllers\Admin\Quotas;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\PhotoQuotaRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Upserts / removes the limits of one scope — a product, a product type
 * or `global` (every story) — for every media kind: included photos /
 * videos / voice messages, max seconds per video and per audio, and an
 * optional hard cap per story. An empty value means "inherit from the
 * next scope". Lowering a limit never deletes content: stories above it
 * keep everything and only stop accepting new items.
 */
class QuotaRuleController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['product', 'type', 'global'])],
            'product_id' => ['required_if:scope,product', 'nullable', 'integer', Rule::exists('products', 'id')->where(fn ($q) => $q->where('type', '!=', ProductType::DigitalPhoto->value))],
            'product_type' => ['required_if:scope,type', 'nullable', Rule::enum(ProductType::class)->except([ProductType::DigitalPhoto])],
            'included_photos' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'included_videos' => ['nullable', 'integer', 'min:0', 'max:200'],
            'included_audio' => ['nullable', 'integer', 'min:0', 'max:50'],
            'max_video_seconds' => ['nullable', 'integer', 'min:5', 'max:3600'],
            'max_audio_seconds' => ['nullable', 'integer', 'min:5', 'max:3600'],
            'cap_photos' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'cap_videos' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'cap_audio' => ['nullable', 'integer', 'min:0', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $limits = [];
        foreach (PhotoQuotaRule::LIMIT_COLUMNS as $column) {
            $limits[$column] = isset($data[$column]) ? (int) $data[$column] : null;
        }

        if (array_filter($limits, fn (?int $value) => $value !== null) === []) {
            throw ValidationException::withMessages(['included_photos' => 'Escribe al menos un límite. Para volver al valor heredado, elimina la regla.']);
        }

        foreach (['photos', 'videos', 'audio'] as $kind) {
            if ($limits["cap_{$kind}"] !== null && $limits["included_{$kind}"] !== null && $limits["cap_{$kind}"] < $limits["included_{$kind}"]) {
                throw ValidationException::withMessages(["cap_{$kind}" => 'El tope máximo no puede ser menor que lo incluido.']);
            }
        }

        $isProduct = $data['scope'] === 'product';
        $isType = $data['scope'] === 'type';
        $key = match (true) {
            $isProduct => PhotoQuotaRule::productKey((int) $data['product_id']),
            $isType => PhotoQuotaRule::typeKey(ProductType::from((string) $data['product_type'])),
            default => PhotoQuotaRule::GLOBAL_KEY,
        };

        PhotoQuotaRule::query()->updateOrCreate(['scope_key' => $key], [
            'product_id' => $isProduct ? (int) $data['product_id'] : null,
            'product_type' => $isType ? $data['product_type'] : null,
            ...$limits,
            'notes' => $data['notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Límite guardado. El contenido que ya existe no se toca.']);

        return back();
    }

    public function destroy(PhotoQuotaRule $rule): RedirectResponse
    {
        $rule->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Límite eliminado: vuelve a aplicar el valor general.']);

        return back();
    }
}
