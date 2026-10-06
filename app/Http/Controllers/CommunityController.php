<?php

namespace App\Http\Controllers;

use App\Actions\Social\CommentOnMoment;
use App\Actions\Social\CreateMoment;
use App\Actions\Social\DeleteMoment;
use App\Actions\Social\FollowAthlete;
use App\Actions\Social\ReactToMoment;
use App\Enums\MomentReactionType;
use App\Enums\MomentType;
use App\Enums\MomentVisibility;
use App\Exceptions\MomentReferenceInvalidException;
use App\Exceptions\SocialActionNotAllowedException;
use App\Http\Requests\Api\StoreMomentRequest;
use App\Http\Resources\Api\V1\AthleteResource;
use App\Http\Resources\Api\V1\MomentCommentResource;
use App\Http\Resources\Api\V1\MomentResource;
use App\Http\Resources\EventEditionCardResource;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\EventParticipant;
use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Models\Medal;
use App\Models\User;
use App\Queries\Social\MomentQuery;
use App\Queries\Social\SuggestedAthletes;
use App\Services\EventCatalogService;
use App\Services\Social\SocialVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Comunidad" — the web face of the social layer that already powers the
 * mobile API (docs/architecture/social.md). Every rule is reused, never
 * re-derived: MomentQuery + SocialVisibility decide what a viewer sees,
 * the Social Actions write, LegacyMoment(Comment)Policy authorize.
 * "Celebrar" is the existing `cheer` reaction.
 */
class CommunityController extends Controller
{
    private const array TABS = ['para-ti', 'siguiendo', 'mis-deportes'];

    public function __construct(
        private readonly MomentQuery $moments,
        private readonly SocialVisibility $visibility,
    ) {}

    public function index(Request $request, SuggestedAthletes $suggested, EventCatalogService $events): Response
    {
        $viewer = $request->user();
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'para-ti';

        // "Siguiendo" / "Mis deportes" only mean something with a session.
        if ($viewer === null) {
            $tab = 'para-ti';
        }

        $query = $this->moments->visibleTo($viewer);
        $sportName = null;

        if ($tab === 'siguiendo') {
            $query->whereIn('legacy_moments.user_id', [...$this->visibility->followingIds($viewer), $viewer->id]);
        } elseif ($tab === 'mis-deportes') {
            $profile = $viewer->athleteProfile()->with('mainSport')->first();
            $sportName = $profile?->mainSport?->name;
            $sportId = $profile?->main_sport_id;

            $sportId === null
                ? $query->whereRaw('1 = 0')
                : $query->whereHas('author.athleteProfile', fn ($q) => $q->where('main_sport_id', $sportId));
        }

        $page = $this->moments->paginate($query, (int) config('finisher.social.feed_per_page'));

        return Inertia::render('community/Index', [
            'tab' => $tab,
            'tabSport' => $sportName,
            'posts' => [
                'data' => MomentResource::collection($page->getCollection())->resolve($request),
                'next_cursor' => $page->nextCursor()?->encode(),
                'is_continuation' => $request->filled('cursor'),
            ],
            'suggestedAthletes' => AthleteResource::collection($suggested->handle($viewer, 5))->resolve($request),
            'upcomingEvents' => EventEditionCardResource::collection(
                $events->publishedEditions(['status' => 'upcoming'], perPage: 4)->getCollection()
            )->resolve($request),
            'composer' => $viewer === null ? null : $this->composerData($viewer),
            'visibilityOptions' => $this->visibilityOptions(),
            'limits' => [
                'caption_max' => (int) config('finisher.social.moment_caption_max'),
                'max_photos' => (int) config('finisher.social.moment_max_photos'),
            ],
        ]);
    }

    public function show(Request $request, LegacyMoment $moment): Response
    {
        $viewer = $request->user();
        $moment->loadMissing('author.athleteProfile');
        abort_unless(Gate::forUser($viewer)->allows('view', $moment), 404);

        $hidden = $this->visibility->hiddenUserIds($viewer);

        $comments = $moment->comments()
            ->with(['author.athleteProfile', 'moment'])
            ->whereHas('author')
            ->when($hidden !== [], fn ($q) => $q->whereNotIn('user_id', $hidden))
            ->orderBy('id')
            ->limit((int) config('finisher.social.comments_per_page') * 3)
            ->get();

        return Inertia::render('community/Show', [
            'post' => (new MomentResource($this->moments->load($moment, $viewer)))->resolve($request),
            'comments' => MomentCommentResource::collection($comments)->resolve($request),
            'commentMax' => (int) config('finisher.social.comment_max'),
            'canInteract' => $viewer !== null && Gate::forUser($viewer)->allows('interact', $moment),
        ]);
    }

    public function store(StoreMomentRequest $request, CreateMoment $create): RedirectResponse
    {
        $user = $request->user();

        if (! $user->athleteProfile()->exists()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Crea tu perfil de atleta para publicar en la comunidad.']);

            return to_route('dashboard.profile.edit');
        }

        /** @var list<UploadedFile> $photos */
        $photos = array_values($request->file('photos', []));

        try {
            $create->handle($user, $request->safe()->except('photos'), $photos);
        } catch (MomentReferenceInvalidException $e) {
            throw ValidationException::withMessages(['caption' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tu logro ya es parte de la comunidad.']);

        return back();
    }

    public function update(Request $request, LegacyMoment $moment): RedirectResponse
    {
        abort_unless(Gate::forUser($request->user())->allows('update', $moment), 404);

        $moment->update($request->validate([
            'caption' => ['sometimes', 'nullable', 'string', 'max:'.config('finisher.social.moment_caption_max')],
            'visibility' => ['sometimes', Rule::enum(MomentVisibility::class)],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Publicación actualizada.']);

        return back();
    }

    public function destroy(Request $request, LegacyMoment $moment, DeleteMoment $delete): RedirectResponse
    {
        abort_unless(Gate::forUser($request->user())->allows('delete', $moment), 404);

        $delete->handle($moment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Publicación eliminada.']);

        return $request->boolean('from_detail') ? to_route('community.index') : back();
    }

    public function celebrate(Request $request, LegacyMoment $moment, ReactToMoment $react): RedirectResponse
    {
        $this->authorizeInteraction($request->user(), $moment);
        $react->add($request->user(), $moment, MomentReactionType::Cheer);

        return back();
    }

    public function uncelebrate(Request $request, LegacyMoment $moment, ReactToMoment $react): RedirectResponse
    {
        $this->authorizeInteraction($request->user(), $moment);
        $react->remove($request->user(), $moment, MomentReactionType::Cheer);

        return back();
    }

    public function storeComment(Request $request, LegacyMoment $moment, CommentOnMoment $comment): RedirectResponse
    {
        $this->authorizeInteraction($request->user(), $moment);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:'.config('finisher.social.comment_max')],
        ], [
            'body.required' => 'Escribe un mensaje antes de enviarlo.',
            'body.max' => 'Tu mensaje es demasiado largo (máximo :max caracteres).',
        ]);

        $comment->handle($request->user(), $moment, $data['body']);

        return back();
    }

    public function destroyComment(Request $request, LegacyMomentComment $comment): RedirectResponse
    {
        abort_unless(Gate::forUser($request->user())->allows('delete', $comment), 404);

        $comment->delete();

        return back();
    }

    public function follow(Request $request, AthleteProfile $athleteProfile, FollowAthlete $follow): RedirectResponse
    {
        $athleteProfile->loadMissing('user');
        abort_unless($athleteProfile->user !== null && $this->visibility->canViewProfile($athleteProfile, $request->user()), 404);

        try {
            $follow->handle($request->user(), $athleteProfile);
        } catch (SocialActionNotAllowedException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    public function unfollow(Request $request, AthleteProfile $athleteProfile): RedirectResponse
    {
        AthleteFollow::query()
            ->where('follower_id', $request->user()->id)
            ->where('following_id', $athleteProfile->user_id)
            ->delete();

        $this->visibility->forget($request->user());

        return back();
    }

    private function authorizeInteraction(User $user, LegacyMoment $moment): void
    {
        $moment->loadMissing('author.athleteProfile');
        abort_unless(Gate::forUser($user)->allows('interact', $moment), 404);
    }

    /**
     * What the composer can attach — only the viewer's OWN participations
     * and medals (CreateMoment re-checks ownership server-side anyway).
     *
     * @return array<string, mixed>
     */
    private function composerData(User $viewer): array
    {
        $profile = $viewer->athleteProfile()->first(['id', 'username', 'profile_photo_path']);
        $athlete = $viewer->athlete()->first();

        $participations = $athlete === null ? collect() : $athlete->eventParticipations()
            ->with(['eventEdition.event', 'eventRace'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(fn (EventParticipant $p) => [
                'id' => $p->id,
                'label' => trim(($p->eventEdition?->event?->name ?? 'Evento').' · '.($p->eventRace?->name ?? '')),
                'date' => $p->eventEdition?->event_date?->toDateString(),
            ]);

        return [
            'has_profile' => $profile !== null,
            'participations' => $participations->values(),
            'medals' => Medal::query()
                ->where('user_id', $viewer->id)
                ->latest('id')
                ->limit(30)
                ->get(['uuid', 'title'])
                ->map(fn (Medal $medal) => ['uuid' => $medal->uuid, 'title' => $medal->title])
                ->values(),
            'types' => [
                ['value' => MomentType::Manual->value, 'label' => 'Publicación'],
                ['value' => MomentType::RaceCompleted->value, 'label' => 'Carrera terminada'],
                ['value' => MomentType::PersonalRecord->value, 'label' => 'Marca personal'],
                ['value' => MomentType::Training->value, 'label' => 'Entrenamiento'],
                ['value' => MomentType::MedalClaimed->value, 'label' => 'Medalla / logro'],
                ['value' => MomentType::Memory->value, 'label' => 'Recuerdo'],
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    private function visibilityOptions(): array
    {
        return [
            ['value' => MomentVisibility::Public->value, 'label' => 'Público', 'description' => 'Cualquiera que pueda ver tu perfil'],
            ['value' => MomentVisibility::Followers->value, 'label' => 'Seguidores', 'description' => 'Solo quienes te siguen'],
            ['value' => MomentVisibility::Private->value, 'label' => 'Privado', 'description' => 'Solo tú'],
        ];
    }
}
