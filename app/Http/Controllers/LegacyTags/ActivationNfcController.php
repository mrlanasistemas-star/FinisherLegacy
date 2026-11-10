<?php

namespace App\Http\Controllers\LegacyTags;

use App\Actions\LegacyTags\ConfirmTagActivation;
use App\Actions\LegacyTags\FailTagActivation;
use App\Actions\LegacyTags\StartTagActivation;
use App\Http\Controllers\Controller;
use App\Models\LegacyTagActivationIntent;
use App\Models\User;
use App\Support\LegacyTags\TagCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `/activar/nfc` — write a blank Finisher Legacy chip from Android Chrome
 * (Web NFC) with the same actions as the app API
 * (docs/renovacion/manual-activacion-nfc.md). The page feature-detects
 * NDEFReader; on iPhone / desktop it sends people to the app or to the
 * manual activation by code instead of showing buttons that cannot work.
 *
 * The JSON endpoints answer `{data: …}` exactly like /api/v1/tags/activation/*.
 */
class ActivationNfcController extends Controller
{
    use ActivationNfcRules;

    public function show(Request $request): Response
    {
        $code = is_string($request->query('code')) ? TagCode::normalize($request->query('code')) : null;

        return Inertia::render('activate/NfcWrite', [
            'prefillTagCode' => $code,
            'officialHost' => parse_url((string) config('legacy_tags.public_base_url'), PHP_URL_HOST),
            'appLinks' => [
                'ios' => config('legacy_tags.app.ios_url') ?: null,
                'android' => config('legacy_tags.app.android_url') ?: null,
            ],
        ]);
    }

    public function start(Request $request, StartTagActivation $start): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validateStart($request);

        $intent = $start->handle($user, $data, $request->ip());

        return response()->json(['data' => $start->present($intent, $user)]);
    }

    public function confirm(Request $request, string $intent, ConfirmTagActivation $confirm): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $this->validateConfirm($request);
        $model = $this->intentFor($user, $intent);

        $story = $confirm->handle($user, $model, $data['written_url'], $data['nfc_uid'] ?? null);

        return response()->json(['data' => $confirm->present($model, $story)]);
    }

    public function fail(Request $request, string $intent, FailTagActivation $fail): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();
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
