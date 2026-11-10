<?php

namespace App\Http\Controllers\Admin\Quotas;

use App\Enums\ProductPriceType;
use App\Http\Controllers\Controller;
use App\Models\MediaEntitlement;
use App\Models\OrderItem;
use App\Models\PhotoExpansionPack;
use App\Models\PhotoExpansionPriceStep;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Price history of a photo expansion. A schedule row's amount, currency,
 * tax and start are never edited: a new price is a new row, and the
 * previous open-ended row gets its `ends_at` set to the new start. The
 * only other changes allowed are closing the current window ("terminar")
 * and cancelling a window that has not started and was never sold
 * (`active = false`, kept as history — never deleted).
 *
 * Price steps ("banderas"): a schedule may carry steps — "from N units
 * the story already holds, the pack costs X". They are stored with the
 * schedule in the same transaction and are just as immutable: changing a
 * step means scheduling a new price with the new steps.
 */
class ExpansionPriceController extends Controller
{
    public function store(Request $request, PhotoExpansionPack $pack): RedirectResponse
    {
        abort_if($pack->isArchived(), 422, 'La ampliación está archivada.');

        $this->createSchedule($request, $pack, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Precio programado. Las compras anteriores conservan su precio.']);

        return back();
    }

    public function end(ProductPriceSchedule $schedule): RedirectResponse
    {
        abort_if($schedule->photo_expansion_pack_id === null, 404);

        if ($schedule->ends_at === null || $schedule->ends_at->isFuture()) {
            $schedule->update(['ends_at' => now()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Venta a este precio terminada.']);

        return back();
    }

    public function cancel(ProductPriceSchedule $schedule): RedirectResponse
    {
        abort_if($schedule->photo_expansion_pack_id === null, 404);

        if (($schedule->starts_at === null || ! $schedule->starts_at->isFuture()) || $this->wasUsed($schedule)) {
            throw ValidationException::withMessages(['schedule' => 'Solo se puede cancelar un precio que aún no empieza y no se ha vendido. Para cambiar el precio actual, programa uno nuevo.']);
        }

        $schedule->update(['active' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Precio programado cancelado (queda en el historial).']);

        return back();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function createSchedule(Request $request, PhotoExpansionPack $pack, array $input): ProductPriceSchedule
    {
        $data = Validator::make($input, [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'currency' => ['required', 'string', 'size:3', Rule::in(['MXN', 'USD'])],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'tax_included' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'steps' => ['nullable', 'array', 'max:12'],
            'steps.*.from_units' => ['required', 'integer', 'min:1', 'max:100000', 'distinct'],
            'steps.*.amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            // A store product belongs to ONE pack: it may repeat on the next
            // price of this same pack, never on another pack or its steps.
            'steps.*.apple_product_id' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'distinct', $this->uniqueStepProduct('apple_product_id', $pack), Rule::unique('photo_expansion_packs', 'apple_product_id')],
            'steps.*.google_product_id' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9._]*$/', 'distinct', $this->uniqueStepProduct('google_product_id', $pack), Rule::unique('photo_expansion_packs', 'google_product_id')],
        ], [
            'steps.*.from_units.distinct' => 'Cada bandera debe empezar en una cantidad distinta.',
            'steps.*.from_units.min' => 'Una bandera empieza en 1 o más (el precio base aplica desde 0).',
            'steps.*.amount.required' => 'Escribe el precio de la bandera.',
        ])->validate();

        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();
        if ($startsAt->isPast()) {
            $startsAt = now();
        }
        $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : null;

        if ($endsAt !== null && $endsAt <= $startsAt) {
            throw ValidationException::withMessages(['ends_at' => 'La fecha de fin debe ser posterior al inicio.']);
        }

        return DB::transaction(function () use ($request, $pack, $data, $startsAt, $endsAt) {
            // Serialize schedule changes of this pack.
            PhotoExpansionPack::query()->whereKey($pack->id)->lockForUpdate()->first();

            $later = $pack->prices()->where('active', true)->where('starts_at', '>=', $startsAt)->exists();
            if ($later) {
                throw ValidationException::withMessages(['starts_at' => 'Ya hay un precio programado que empieza en esa fecha o después. Cancélalo primero o elige otra fecha.']);
            }

            // Close (never rewrite) the window that would overlap the new one.
            $pack->prices()
                ->where('active', true)
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $startsAt))
                ->update(['ends_at' => $startsAt, 'updated_at' => now()]);

            /** @var ProductPriceSchedule $schedule */
            $schedule = $pack->prices()->create([
                'product_id' => Product::query()->where('slug', PhotoExpansionPack::PRODUCT_SLUG)->value('id'),
                'price_type' => ProductPriceType::Standard,
                'amount_minor' => (int) round(((float) $data['amount']) * 100),
                'currency' => strtoupper((string) $data['currency']),
                'tax_rate' => isset($data['tax_rate']) ? round(((float) $data['tax_rate']) / 100, 4) : null,
                'tax_included' => (bool) ($data['tax_included'] ?? true),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'active' => true,
                'created_by' => $request->user()?->id,
            ]);

            foreach ((array) ($data['steps'] ?? []) as $step) {
                $schedule->steps()->create([
                    'from_units' => (int) $step['from_units'],
                    'amount_minor' => (int) round(((float) $step['amount']) * 100),
                    'apple_product_id' => filled($step['apple_product_id'] ?? null) ? $step['apple_product_id'] : null,
                    'google_product_id' => filled($step['google_product_id'] ?? null) ? $step['google_product_id'] : null,
                ]);
            }

            return $schedule;
        });
    }

    /** Fails when another pack's price step already uses that store product id. */
    private function uniqueStepProduct(string $column, PhotoExpansionPack $pack): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($column, $pack): void {
            $taken = is_string($value) && $value !== '' && PhotoExpansionPriceStep::query()
                ->where($column, $value)
                ->whereHas('schedule', fn ($q) => $q->where('photo_expansion_pack_id', '!=', $pack->id))
                ->exists();

            if ($taken) {
                $fail('Ese producto de la tienda ya pertenece a otra ampliación.');
            }
        };
    }

    private function wasUsed(ProductPriceSchedule $schedule): bool
    {
        return MediaEntitlement::query()->where('product_price_schedule_id', $schedule->id)->exists()
            || OrderItem::query()->where('metadata->product_price_schedule_id', $schedule->id)->exists();
    }
}
