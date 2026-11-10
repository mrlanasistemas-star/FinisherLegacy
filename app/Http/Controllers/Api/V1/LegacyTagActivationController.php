<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\LegacyTags\ConfirmTagActivation;
use App\Actions\LegacyTags\FailTagActivation;
use App\Actions\LegacyTags\StartTagActivation;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LegacyTags\ActivationNfcRules;
use App\Models\LegacyTagActivationIntent;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * NFC self-activation of a blank chip for the app (docs/api/v1.md
 * §Activación NFC). Same actions as the Web NFC page
 * (App\Http\Controllers\LegacyTags\ActivationNfcController):
 *
 *   start   → validate unit + right to it, issue the ONE URL to write
 *   confirm → URL read back matches, bind chip, ActivateTag (idempotent)
 *   fail    → record a write failure for support
 *
 * Domain errors render as 422 `{message, code, error{…}}`
 * (App\Exceptions\LegacyTags\TagActivationException).
 */
class LegacyTagActivationController extends Controller
{
    use ActivationNfcRules;
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function start(Request $request, StartTagActivation $start): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $data = $this->validateStart($request);

        $intent = $start->handle($user, $data, $request->ip());

        return $this->respond($start->present($intent, $user));
    }

    public function confirm(Request $request, string $intent, ConfirmTagActivation $confirm): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $data = $this->validateConfirm($request);
        $model = $this->intentFor($user, $intent);

        $story = $confirm->handle($user, $model, $data['written_url'], $data['nfc_uid'] ?? null);

        return $this->respond($confirm->present($model, $story), '¡Listo! Tu Finisher Legacy ya es tuyo.');
    }

    public function fail(Request $request, string $intent, FailTagActivation $fail): Response
    {
        $user = $this->sanctumUser($request);
        $data = $this->validateFail($request);

        $fail->handle($user, $this->intentFor($user, $intent), $data['reason']);

        return response()->noContent();
    }

    private function intentFor(User $user, string $uuid): LegacyTagActivationIntent
    {
        return LegacyTagActivationIntent::query()
            ->where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }
}
