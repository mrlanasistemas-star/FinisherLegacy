<?php

namespace App\Http\Controllers\Admin\Quotas;

use App\Actions\Quotas\GrantStoryCapacity;
use App\Enums\StoryQuotaKind;
use App\Http\Controllers\Controller;
use App\Models\LegacyStory;
use App\Models\MediaEntitlement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Límites y tarifas → "Liberar espacio": an admin grants extra photos,
 * videos or voice messages without a purchase — to one story (its public
 * id, the last part of its link) or to a person (their email: it then
 * counts on every story they own) — always with a reason, and can revoke
 * it later. Audit-logged by GrantStoryCapacity; nothing is ever deleted.
 */
class CapacityGrantController extends Controller
{
    public function store(Request $request, GrantStoryCapacity $grant): RedirectResponse
    {
        $data = $request->validate([
            'target' => ['required', Rule::in(['story', 'user'])],
            'story' => ['required_if:target,story', 'nullable', 'string', 'max:255'],
            'email' => ['required_if:target,user', 'nullable', 'email', 'max:255'],
            'kind' => ['required', Rule::enum(StoryQuotaKind::class)],
            'units' => ['required', 'integer', 'min:1', 'max:1000'],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ], [
            'story.required_if' => 'Escribe el enlace o el código del recuerdo.',
            'email.required_if' => 'Escribe el correo de la persona.',
            'reason.required' => 'Escribe el motivo: queda en el historial.',
            'expires_at.after' => 'La fecha de vencimiento debe ser futura.',
        ]);

        $story = null;

        if ($data['target'] === 'story') {
            // Accepts the public id or the whole /legacy/{id} link.
            $publicId = strtolower((string) preg_replace('#^.*/#', '', rtrim(trim((string) $data['story']), '/')));
            $story = LegacyStory::query()->where('public_id', $publicId)->with('athlete.user')->first();
            $recipient = $story?->athlete?->user;

            if ($story === null || ! $recipient instanceof User) {
                throw ValidationException::withMessages(['story' => 'No encontramos ese recuerdo. Revisa el enlace o el código.']);
            }
        } else {
            $recipient = User::query()->where('email', strtolower(trim((string) $data['email'])))->first();

            if ($recipient === null) {
                throw ValidationException::withMessages(['email' => 'No hay ninguna cuenta con ese correo.']);
            }
        }

        $kind = StoryQuotaKind::from((string) $data['kind']);

        $grant->handle(
            $request->user(),
            $recipient,
            $story,
            $kind,
            (int) $data['units'],
            (string) $data['reason'],
            isset($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Listo: liberaste +{$kind->amount((int) $data['units'])}."]);

        return back();
    }

    public function revoke(Request $request, MediaEntitlement $entitlement, GrantStoryCapacity $grant): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $grant->revoke($entitlement, $request->user(), $data['reason'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Espacio revocado. El contenido que ya existe no se borra.']);

        return back();
    }
}
