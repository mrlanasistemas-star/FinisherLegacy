<?php

namespace App\Http\Controllers\Training;

use App\Actions\Training\SubmitCheerMessage;
use App\Enums\CheerIcon;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\StoreCheerMessageRequest;
use App\Models\CheerLink;
use App\Support\Training\CheerPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/animo/{token}` — the public "Envíame ánimo" page (Entrenamientos). No
 * account, no app: family and friends record a voice note in the browser
 * (or upload one), add a short text, name, relationship and an icon.
 * Never indexed (meta robots + X-Robots-Tag). Revoked / expired / full
 * links render the same page in a friendly "unavailable" state; unknown
 * tokens are a 404. Both routes sit behind per-IP / per-link limiters
 * (App\Providers\TrainingServiceProvider).
 */
class CheerPublicController extends Controller
{
    public function show(Request $request, string $token, CheerPresenter $presenter): Response
    {
        $link = $this->find($token);

        $response = Inertia::render('training/Cheer', [
            'token' => $link->token,
            'title' => $link->title,
            'state' => $link->state(),
            'athlete' => $presenter->athlete($link->user),
            'icons' => CheerIcon::values(),
            'limits' => [
                'max_audio_seconds' => (int) config('training.cheers.max_audio_seconds', 180),
                'max_audio_bytes' => (int) config('training.cheers.max_audio_bytes'),
                'max_body' => (int) config('training.cheers.max_body', 500),
            ],
        ])->toResponse($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    public function store(StoreCheerMessageRequest $request, string $token, SubmitCheerMessage $submit): RedirectResponse
    {
        $link = $this->find($token);

        if (! $link->isAccepting()) {
            return back()->withErrors(['form' => 'Este enlace ya no recibe mensajes.']);
        }

        /** @var array{sender_name: string, relationship?: string|null, body?: string|null, icon: string, duration_seconds?: int|string|null} $data */
        $data = $request->safe()->only(['sender_name', 'relationship', 'body', 'icon', 'duration_seconds']);

        $submit->handle($link, $data, $request->file('audio'), $request->ip());

        return back();
    }

    private function find(string $token): CheerLink
    {
        return CheerLink::query()->where('token', $token)->with('user.athleteProfile')->firstOrFail();
    }
}
