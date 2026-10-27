<?php

namespace App\Http\Controllers\Admin\Commercial;

use App\Enums\LegacyTagStatus;
use App\Http\Controllers\Controller;
use App\Models\LegacyTag;
use App\Notifications\TagActivationReminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Legacy → Activaciones: who activated what and how fast, and which sold
 * pieces are still waiting (oldest first) — with "Reenviar instrucciones"
 * when the buyer left an email.
 */
class ActivationController extends Controller
{
    public function index(Request $request): Response
    {
        $relations = ['productVariant:id,sku,product_id', 'productVariant.product:id,name', 'ally:id,name', 'order:id,uuid,order_number,user_id,customer_snapshot', 'order.user:id,email'];

        $recent = LegacyTag::query()
            ->with([...$relations, 'owner:id,full_name'])
            ->whereNotNull('activated_at')
            ->latest('activated_at')
            ->limit(30)
            ->get()
            ->map(fn (LegacyTag $t) => [
                ...$this->row($t),
                'owner' => $t->owner?->full_name,
                'activated_at' => $t->activated_at?->toIso8601String(),
                'seconds_to_activation' => $t->sold_at !== null && $t->activated_at !== null
                    ? max(0, $t->activated_at->getTimestamp() - $t->sold_at->getTimestamp())
                    : null,
            ])->values();

        $pending = LegacyTag::query()
            ->with([...$relations, 'location:id,name'])
            ->whereIn('status', [LegacyTagStatus::Sold->value, LegacyTagStatus::Delivered->value])
            ->orderBy('sold_at')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/activations/Index', [
            'recent' => $recent,
            'pending' => $pending->through(fn (LegacyTag $t) => [
                ...$this->row($t),
                'status_label' => $t->status->label(),
                'location' => $t->location?->name,
                'buyer_email' => $this->buyerEmail($t),
            ]),
            'windowHours' => (int) config('legacy.tags.kpi.activation_window_hours', 72),
        ]);
    }

    public function resend(Request $request, LegacyTag $legacyTag): RedirectResponse
    {
        if (! in_array($legacyTag->status, [LegacyTagStatus::Sold, LegacyTagStatus::Delivered], true)) {
            throw ValidationException::withMessages(['resend' => 'Esta pieza ya no está pendiente de activar.']);
        }

        $legacyTag->loadMissing('order.user');
        $email = $this->buyerEmail($legacyTag);

        if ($email === null) {
            throw ValidationException::withMessages(['resend' => 'No tenemos el correo de quien compró esta pieza.']);
        }

        Notification::route('mail', $email)->notify(new TagActivationReminder($legacyTag));

        activity()->causedBy($request->user())->performedOn($legacyTag)
            ->withProperties(['serial' => $legacyTag->serial])
            ->log('tags.activation_reminder_sent');

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Instrucciones de activación enviadas.']);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(LegacyTag $t): array
    {
        return [
            'uuid' => $t->uuid,
            'serial' => $t->serial,
            'display_code' => $t->displayCode(),
            'product' => $t->productVariant?->product?->name,
            'sku' => $t->productVariant?->sku,
            'channel' => $t->sales_channel?->value,
            'ally' => $t->ally?->name,
            'order' => $t->order ? ['uuid' => $t->order->uuid, 'number' => $t->order->order_number] : null,
            'sold_at' => $t->sold_at?->toIso8601String(),
            'unreported_sale' => ($t->metadata['unreported_sale'] ?? false) === true,
        ];
    }

    private function buyerEmail(LegacyTag $tag): ?string
    {
        $email = $tag->order?->customer_snapshot['email'] ?? $tag->order?->user?->email;

        return filled($email) ? (string) $email : null;
    }
}
