<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Contracts\Privacy\PinProtectable;
use App\Exceptions\Privacy\ContentPinException;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\AthleteEventMedia;
use App\Models\LegacyMoment;
use App\Models\LegacyStory;
use App\Models\User;
use App\Services\Privacy\ContentPin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * `/api/v1/me/pin/*` — the optional content PIN (docs/api/v1.md §NIP,
 * security model in App\Services\Privacy\ContentPin). Every PIN field is
 * write-only: no response, log line or flash ever contains it.
 */
class ContentPinController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function __construct(private readonly ContentPin $pins) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($this->pins->status($this->sanctumUser($request), $request));
    }

    /** Activate: create + confirm. */
    public function store(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $data = $request->validate($this->newPinRules(), $this->messages());

        $this->pins->enable($user, $data['pin']);

        return $this->respond($this->pins->status($user->refresh(), $request), __('Protección con NIP activada.'), status: 201);
    }

    /** Change: requires the current PIN (counted against the lockout). */
    public function update(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $data = $request->validate(['current_pin' => ContentPin::rules(), ...$this->newPinRules()], $this->messages());

        $this->pins->change($user, $data['current_pin'], $data['pin']);

        return $this->respond($this->pins->status($user->refresh(), $request), __('Tu NIP se actualizó.'));
    }

    /** Deactivate: the PIN, or the account password. */
    public function destroy(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $data = $request->validate([
            'pin' => ['nullable', 'required_without:password', 'string', 'regex:/^\d{4,6}$/'],
            'password' => ['nullable', 'required_without:pin', 'string', 'max:255'],
        ], $this->messages());

        if (! $this->pins->isEnabled($user)) {
            throw ContentPinException::notSet();
        }

        if (isset($data['pin'])) {
            $this->pins->verify($user, $data['pin']);
        } else {
            $this->checkPassword($user, (string) $data['password']);
        }

        $this->pins->disable($user);

        return $this->respond($this->pins->status($user->refresh(), $request), __('Protección con NIP desactivada. Tu contenido sigue privado.'));
    }

    /** Correct PIN → short-lived unlock grant (send it back as X-Content-Unlock). */
    public function unlock(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $data = $request->validate(['pin' => ContentPin::rules()], $this->messages());

        $this->pins->verify($user, $data['pin']);

        return $this->respond($this->pins->issueGrant($user, $request));
    }

    public function lock(Request $request): JsonResponse
    {
        $this->pins->revokePresentedGrant($request);

        return $this->respond(['locked' => true]);
    }

    /** Forgot the PIN: email a 6-digit code to the account address. */
    public function sendRecoveryCode(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);

        if (! $this->pins->isEnabled($user)) {
            throw ContentPinException::notSet();
        }

        $this->pins->sendRecoveryCode($user);

        return $this->respond(['sent' => filled($user->email)], __('Te enviamos un código a tu correo.'));
    }

    /** Reset with the account password OR the emailed code. */
    public function reset(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $data = $request->validate([
            'password' => ['nullable', 'required_without:code', 'string', 'max:255'],
            'code' => ['nullable', 'required_without:password', 'string', 'regex:/^\d{6}$/'],
            ...$this->newPinRules(),
        ], $this->messages());

        if (! $this->pins->isEnabled($user)) {
            throw ContentPinException::notSet();
        }

        if (isset($data['code'])) {
            if (! $this->pins->checkRecoveryCode($user, $data['code'])) {
                throw ValidationException::withMessages(['code' => [__('El código no es válido o ya venció.')]]);
            }
        } else {
            $this->checkPassword($user, (string) $data['password']);
        }

        $this->pins->reset($user, $data['pin']);

        return $this->respond($this->pins->status($user->refresh(), $request), __('Listo. Tu NIP nuevo ya está activo.'));
    }

    /**
     * Mark / unmark one item. Marking only needs an active PIN (it can only
     * reduce exposure); unmarking needs a valid unlock grant.
     */
    public function protect(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $data = $request->validate([
            'type' => ['required', Rule::in(['story', 'moment', 'media'])],
            'id' => ['required', 'string', 'max:64'],
            'protected' => ['required', 'boolean'],
        ]);

        if (! $this->pins->isEnabled($user)) {
            throw ContentPinException::notSet();
        }

        $item = $this->findOwned($user, $data['type'], $data['id']);

        if (! $data['protected']) {
            $this->pins->ensureRevealed($item, $request);
        }

        $item->forceFill(['pin_protected' => (bool) $data['protected']])->save();

        return $this->respond([
            'type' => $data['type'],
            'id' => $data['id'],
            'pin_protected' => $item->isPinProtected(),
        ], $data['protected'] ? __('Protegido con NIP.') : __('Ya no está protegido con NIP.'));
    }

    /** Owner-only lookup: anyone else's item is a plain 404. */
    private function findOwned(User $user, string $type, string $id): Model&PinProtectable
    {
        $athleteId = $user->athlete()->value('id');

        $item = match ($type) {
            'story' => $athleteId === null ? null : LegacyStory::query()->where('public_id', $id)->where('athlete_id', $athleteId)->first(),
            'moment' => LegacyMoment::query()->where('uuid', $id)->where('user_id', $user->id)->first(),
            'media' => $athleteId === null ? null : AthleteEventMedia::query()->where('uuid', $id)->where('athlete_id', $athleteId)->first(),
        };

        abort_if($item === null, 404);

        return $item;
    }

    private function checkPassword(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => [__('La contraseña no es correcta.')]]);
        }
    }

    /** @return array<string, mixed> */
    private function newPinRules(): array
    {
        return ['pin' => [...ContentPin::rules(), 'confirmed']];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'pin.regex' => __('El NIP debe tener de 4 a 6 números.'),
            'current_pin.regex' => __('El NIP debe tener de 4 a 6 números.'),
            'pin.confirmed' => __('Los NIP no coinciden.'),
            'pin.required' => __('Escribe tu NIP.'),
            'current_pin.required' => __('Escribe tu NIP actual.'),
        ];
    }
}
