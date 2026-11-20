<?php

namespace App\Http\Controllers\Admin\Quotas;

use App\Enums\CheerAfterFree;
use App\Enums\CheerChannel;
use App\Enums\CheerMessageStatus;
use App\Enums\LegacyTagPurpose;
use App\Enums\LegacyTagStatus;
use App\Enums\OrderPaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\CheerMessage;
use App\Models\CheerPricingSetting;
use App\Models\LegacyTag;
use App\Models\Order;
use App\Support\Commerce\WebCheckoutPayment;
use App\Support\Training\CheerCards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Límites y tarifas → Mensajes de ánimo
 * (docs/renovacion/tarjeta-animo.md §Precios): separate settings for the
 * shared link and for the Tarjeta de ánimo NFC — free messages, what happens
 * after them (blocked / free / paid by the sender), optional hard limit,
 * price per extra message with price steps ("banderas"), packs and
 * anonymity. Gated by `quotas.manage` like the rest of Límites y tarifas.
 *
 * Amounts are typed in pesos and stored in cents (MXN, the store currency).
 * Nothing here changes a message already sent or a payment already
 * started: each paid message freezes its amount on its OrderItem.
 */
class CheerPricingController extends Controller
{
    public function index(WebCheckoutPayment $payments): Response
    {
        $channels = collect(CheerChannel::cases())->map(function (CheerChannel $channel) {
            $setting = CheerPricingSetting::for($channel);

            return [
                'channel' => $channel->value,
                'label' => $channel->label(),
                'saved' => $setting->exists,
                'free_messages' => $setting->free_messages,
                'after_free' => $setting->after_free->value,
                'hard_limit' => $setting->hard_limit,
                'price_minor' => $setting->price_minor,
                'currency' => $setting->currency,
                'price_steps' => $setting->validSteps(),
                'packs' => $setting->validPacks(),
                'allow_anonymous' => $setting->allow_anonymous,
                'updated_at' => $setting->updated_at?->toIso8601String(),
                'stats' => $this->stats($channel),
            ];
        })->values();

        $cardProduct = CheerCards::product();

        return Inertia::render('admin/quotas/Cheers', [
            'channels' => $channels,
            'defaults' => [
                'free_messages' => (int) config('training.cheers.max_messages_per_link', 200),
                'link_cap' => (int) config('training.cheers.max_messages_per_link', 200),
            ],
            'payment_ready' => $payments->openpayOnly()['provider'] !== null,
            'card_product' => [
                'published' => $cardProduct !== null,
                'active_cards' => LegacyTag::query()->where('purpose', LegacyTagPurpose::Cheer->value)->where('status', LegacyTagStatus::Activated->value)->whereNotNull('cheer_link_id')->count(),
            ],
        ]);
    }

    public function update(Request $request, string $channel): RedirectResponse
    {
        $channel = CheerChannel::tryFrom($channel) ?? abort(404);

        $data = $request->validate([
            'free_messages' => ['required', 'integer', 'min:0', 'max:100000'],
            'after_free' => ['required', Rule::enum(CheerAfterFree::class)],
            'hard_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'price' => ['nullable', 'numeric', 'min:1', 'max:100000', 'required_if:after_free,paid'],
            'price_steps' => ['nullable', 'array', 'max:10'],
            'price_steps.*.from_paid' => ['required', 'integer', 'min:2', 'max:100000', 'distinct'],
            'price_steps.*.price' => ['required', 'numeric', 'min:1', 'max:100000'],
            'packs' => ['nullable', 'array', 'max:6'],
            'packs.*.messages' => ['required', 'integer', 'min:2', 'max:100', 'distinct'],
            'packs.*.price' => ['required', 'numeric', 'min:1', 'max:100000'],
            'allow_anonymous' => ['required', 'boolean'],
        ], [
            'price.required_if' => 'Escribe el precio por mensaje adicional para cobrarlos.',
            'price_steps.*.from_paid.distinct' => 'Cada bandera necesita un número distinto.',
            'packs.*.messages.distinct' => 'Cada paquete necesita una cantidad distinta.',
        ]);

        if ($data['hard_limit'] !== null && (int) $data['hard_limit'] < (int) $data['free_messages']) {
            throw ValidationException::withMessages(['hard_limit' => 'El límite total no puede ser menor que los mensajes gratis.']);
        }

        $cents = fn ($pesos): int => (int) round(((float) $pesos) * 100);

        $setting = DB::transaction(function () use ($channel, $data, $cents, $request) {
            $setting = CheerPricingSetting::query()->firstOrNew(['channel' => $channel->value]);
            $setting->fill([
                'free_messages' => (int) $data['free_messages'],
                'after_free' => CheerAfterFree::from($data['after_free']),
                'hard_limit' => $data['hard_limit'] !== null ? (int) $data['hard_limit'] : null,
                'price_minor' => isset($data['price']) ? $cents($data['price']) : null,
                'currency' => 'MXN',
                'price_steps' => self::rows((array) ($data['price_steps'] ?? []), 'from_paid', $cents),
                'packs' => self::rows((array) ($data['packs'] ?? []), 'messages', $cents),
                'allow_anonymous' => (bool) $data['allow_anonymous'],
                'updated_by' => $request->user()?->id,
            ]);
            $setting->save();

            return $setting;
        });

        activity('training')->causedBy($request->user())->performedOn($setting)
            ->withProperties($setting->only(['channel', 'free_messages', 'after_free', 'hard_limit', 'price_minor', 'price_steps', 'packs', 'allow_anonymous']))
            ->log('cheer.pricing_updated');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Guardamos los precios de '.mb_strtolower($channel->label()).'.']);

        return back();
    }

    /**
     * Validated `[{<key>, price}]` rows → `[{<key>, amount_minor}]`, sorted by `<key>`.
     *
     * @param  array<int|string, mixed>  $rows
     * @param  callable(mixed): int  $cents
     * @return list<array<string, int>>
     */
    private static function rows(array $rows, string $key, callable $cents): array
    {
        $out = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = [$key => (int) ($row[$key] ?? 0), 'amount_minor' => $cents($row['price'] ?? 0)];
            }
        }

        usort($out, fn (array $a, array $b) => $a[$key] <=> $b[$key]);

        return $out;
    }

    /**
     * Counts only — staff never read cheer messages here.
     *
     * @return array{free: int, paid: int, credit: int, pending: int, revenue_minor: int}
     */
    private function stats(CheerChannel $channel): array
    {
        $rows = CheerMessage::query()
            ->where('source', $channel->value)
            ->whereIn('status', [CheerMessageStatus::Visible->value, CheerMessageStatus::Hidden->value])
            ->selectRaw('billing, count(*) as total')
            ->groupBy('billing')
            ->pluck('total', 'billing');

        $paidOrderIds = CheerMessage::query()->where('source', $channel->value)->whereNotNull('order_id')->select('order_id');

        return [
            'free' => (int) ($rows['free'] ?? 0),
            'paid' => (int) ($rows['paid'] ?? 0),
            'credit' => (int) ($rows['credit'] ?? 0),
            'pending' => CheerMessage::query()->where('source', $channel->value)->where('status', CheerMessageStatus::PendingPayment->value)->count(),
            'revenue_minor' => (int) Order::query()->whereIn('id', $paidOrderIds)->where('payment_status', OrderPaymentStatus::Paid->value)->sum('total_minor'),
        ];
    }
}
