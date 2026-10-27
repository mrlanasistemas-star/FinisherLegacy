<?php

namespace App\Http\Controllers\LegacyTags;

use App\Actions\LegacyTags\ActivateTag;
use App\Actions\LegacyTags\RecordTagScan;
use App\Actions\LegacyTags\UpdateStory;
use App\Enums\TagAccessSource;
use App\Enums\TagScanEvent;
use App\Exceptions\Athletes\AthleteIdentityConflictException;
use App\Exceptions\LegacyTags\TagAlreadyActivatedException;
use App\Exceptions\LegacyTags\TagNotActivatableException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\LegacyTags\Concerns\FindsLegacyTag;
use App\Http\Requests\LegacyTags\CompleteActivationRequest;
use App\Models\EventParticipant;
use App\Models\LegacyTag;
use App\Models\User;
use App\Support\LegacyTags\StoryPresenter;
use App\Support\LegacyTags\TagActivationState;
use App\Support\LegacyTags\TagCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * `/activar` + `/activar/{code}` — the activation experience
 * (docs/architecture/commerce-os.md §Activación y Mi Legacy):
 *
 *   1 Tu tag → 2 Tu cuenta (login/register, back here via url.intended)
 *   → 3 Activar (ActivateTag, the only writer) → 4 ¿De qué carrera? → 5
 *   story editor.
 *
 * How the person arrived (nfc / qr / typed code / link) is remembered in
 * the session per code so it survives the login detour, for analytics.
 */
class ActivationController extends Controller
{
    use FindsLegacyTag;

    public function index(Request $request): Response
    {
        return Inertia::render('activate/Index', [
            'prefill' => is_string($request->query('code')) ? mb_substr($request->query('code'), 0, 64) : null,
        ]);
    }

    /** Typed code (or a pasted /t/ URL) → /activar/{code}. */
    public function lookup(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:200']], [
            'code.required' => 'Escribe el código impreso en tu tag.',
        ]);

        $code = TagCode::normalize((string) $request->input('code'));

        if ($code === null) {
            throw ValidationException::withMessages(['code' => 'Revisa el código: son 12 letras y números, como ABCD-EFGH-JKMN.']);
        }

        $tag = LegacyTag::query()->where('public_code', $code)->first();

        if ($tag === null) {
            throw ValidationException::withMessages(['code' => 'No encontramos ese código. Revisa que esté bien escrito o escríbenos.']);
        }

        return redirect()->route('activate.show', ['code' => $tag->public_code, 'src' => TagAccessSource::Code->value]);
    }

    public function show(Request $request, string $code, RecordTagScan $scans, StoryPresenter $presenter): Response|RedirectResponse|HttpResponse
    {
        $tag = $this->findTag($code);

        if ($tag === null) {
            return Inertia::render('activate/Unavailable', [
                'reason' => 'not_found',
                'message' => 'No encontramos este código. Revisa que esté bien escrito o escríbenos y lo buscamos contigo.',
                'tag' => null,
            ])->toResponse($request)->setStatusCode(404);
        }

        if ($tag->public_code !== $code) {
            return redirect()->route('activate.show', ['code' => $tag->public_code, ...$request->only('src')]);
        }

        $source = $this->rememberSource($request, $tag);
        /** @var User|null $user */
        $user = $request->user();
        $state = TagActivationState::for($tag, $user);
        $story = $tag->story;

        if ($state->state === TagActivationState::MINE && $story !== null && ($presenter->hasRace($story) || $story->completed_at !== null)) {
            return redirect()->route('legacy.story.show', $story->public_id);
        }

        if ($state->canActivate()) {
            $scans->handle($tag, TagScanEvent::ActivationStarted, $source, $user, $request);
        }

        return Inertia::render('activate/Show', [
            'code' => $tag->public_code,
            'displayCode' => $tag->displayCode(),
            'state' => $state->state,
            'reason' => $state->reason,
            'message' => $state->message(),
            'source' => $source->value,
            'tag' => $presenter->tag($tag),
            'firstName' => $user?->first_name ?: ($user !== null ? str($user->name)->before(' ')->toString() : null),
            'story' => $state->state === TagActivationState::MINE && $story !== null
                ? ['public_id' => $story->public_id, 'title' => $story->title]
                : null,
            'participations' => $state->state === TagActivationState::MINE ? $this->participations($user, $presenter) : [],
        ]);
    }

    /** Guest → login/register, then straight back to this tag. */
    public function continueTo(Request $request, string $code, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, ['login', 'register'], true), 404);
        $tag = $this->findTagOrFail($code);

        $request->session()->put('url.intended', route('activate.show', $tag->public_code));

        return redirect()->route($provider);
    }

    public function store(Request $request, string $code, ActivateTag $activate): RedirectResponse
    {
        $tag = $this->findTagOrFail($code);
        $source = $this->sourceFor($request, $tag);

        try {
            $activate->handle($tag, $request->user(), $source);
        } catch (TagAlreadyActivatedException|TagNotActivatableException|AthleteIdentityConflictException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return redirect()->route('activate.show', $tag->public_code);
        }

        $request->session()->forget($this->sourceKey($tag));
        Inertia::flash('toast', ['type' => 'success', 'message' => '¡Listo! Tu Finisher Legacy ya es tuyo.']);

        return redirect()->route('activate.show', $tag->public_code);
    }

    /** Step 4: which race (or "Lo completo después") → story editor. */
    public function complete(CompleteActivationRequest $request, string $code, UpdateStory $update): RedirectResponse
    {
        $tag = $this->findTagOrFail($code);
        $story = $tag->story;

        abort_unless($story !== null && $story->isOwnedBy($request->user()), 403);

        $data = $request->validated();
        $update->applyRace($story, (string) $data['race_mode'], $data);
        $story->completed_at ??= now();
        $story->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tu Legacy está listo. Ahora ponle tus fotos y tus palabras.']);

        return redirect()->route('my-legacy.edit', $story->public_id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function participations(?User $user, StoryPresenter $presenter): array
    {
        $athleteId = $user?->athlete()->value('id');

        if ($athleteId === null) {
            return [];
        }

        return EventParticipant::query()
            ->where('athlete_id', $athleteId)
            ->with(['eventEdition.event', 'eventRace', 'result'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->sortByDesc(fn (EventParticipant $p) => $p->eventEdition?->event_date?->toDateString() ?? '')
            ->map(fn (EventParticipant $p) => $presenter->participationOption($p))
            ->values()
            ->all();
    }

    private function rememberSource(Request $request, LegacyTag $tag): TagAccessSource
    {
        $fromQuery = TagAccessSource::tryFrom((string) $request->query('src', ''));

        if ($fromQuery !== null) {
            $request->session()->put($this->sourceKey($tag), $fromQuery->value);
        }

        return $fromQuery ?? $this->sourceFor($request, $tag);
    }

    private function sourceFor(Request $request, LegacyTag $tag): TagAccessSource
    {
        return TagAccessSource::tryFrom((string) $request->session()->get($this->sourceKey($tag), ''))
            ?? TagAccessSource::tryFrom((string) $request->input('source', ''))
            ?? TagAccessSource::Link;
    }

    private function sourceKey(LegacyTag $tag): string
    {
        return 'legacy_activation.source.'.$tag->public_code;
    }
}
