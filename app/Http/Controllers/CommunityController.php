<?php

namespace App\Http\Controllers;

use App\Actions\Social\BlockUser;
use App\Actions\Social\CommentOnMoment;
use App\Actions\Social\CreateMoment;
use App\Actions\Social\CreateReport;
use App\Actions\Social\DeleteMoment;
use App\Actions\Social\FollowAthlete;
use App\Actions\Social\ReactToMoment;
use App\Enums\EditionStatus;
use App\Enums\MomentReactionType;
use App\Enums\MomentVisibility;
use App\Enums\ReportTargetType;
use App\Exceptions\MomentReferenceInvalidException;
use App\Exceptions\SocialActionNotAllowedException;
use App\Http\Requests\Api\StoreMomentRequest;
use App\Http\Resources\Api\V1\AthleteResource;
use App\Http\Resources\Api\V1\MomentCommentResource;
use App\Http\Resources\Api\V1\MomentResource;
use App\Http\Resources\EventEditionCardResource;
use App\Models\AthleteFollow;
use App\Models\AthleteOwnedProduct;
use App\Models\AthleteProfile;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Models\Medal;
use App\Models\User;
use App\Queries\Social\CommentPreviews;
use App\Queries\Social\FeedQuery;
use App\Queries\Social\MomentQuery;
use App\Queries\Social\MutualConnections;
use App\Queries\Social\PeopleQuery;
use App\Queries\Social\PeopleStrip;
use App\Queries\Social\SuggestedAthletes;
use App\Services\EventCatalogService;
use App\Services\Social\SocialVisibility;
use App\Support\Social\ReportReasons;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Comunidad" — the web face of the social layer that also powers the
 * mobile API (docs/architecture/social.md). Every rule is reused, never
 * re-derived: FeedQuery/MomentQuery + SocialVisibility decide what a viewer
 * sees, the Social Actions write, LegacyMoment(Comment)Policy authorize.
 * The web shows ONE reaction, "Celebrar" (stored as `cheer`).
 */
class CommunityController extends Controller
{
    /** URL tab → FeedQuery scope. */
    private const array TABS = [
        'para-ti' => FeedQuery::FOR_YOU,
        'siguiendo' => FeedQuery::FOLLOWING,
        'eventos' => FeedQuery::EVENTS,
    ];

    public function __construct(
        private readonly MomentQuery $moments,
        private readonly SocialVisibility $visibility,
    ) {}

    public function index(
        Request $request,
        FeedQuery $feed,
        SuggestedAthletes $suggested,
        EventCatalogService $events,
        CommentPreviews $previews,
        MutualConnections $mutuals,
        PeopleStrip $strip,
    ): Response {
        $viewer = $request->user();
        $tab = array_key_exists((string) $request->query('tab'), self::TABS) ? (string) $request->query('tab') : 'para-ti';

        // "Siguiendo" only means something with a session.
        if ($viewer === null && $tab === 'siguiendo') {
            $tab = 'para-ti';
        }

        return Inertia::render('community/Index', [
            'tab' => $tab,
            'posts' => Inertia::scroll(function () use ($feed, $previews, $viewer, $tab) {
                $page = $feed->paginate($viewer, self::TABS[$tab]);
                $previews->attach($page->items(), $viewer);

                return MomentResource::collection($page);
            }),
            // Navbar "+" links to /comunidad?publicar=1.
            'openComposer' => $request->boolean('publicar'),
            'peopleStrip' => fn () => $strip->handle($viewer),
            'me' => fn () => $viewer === null ? null : [
                'posts' => LegacyMoment::query()->where('user_id', $viewer->id)->count(),
                'followers' => AthleteFollow::query()->where('following_id', $viewer->id)->count(),
                'following' => AthleteFollow::query()->where('follower_id', $viewer->id)->count(),
            ],
            'suggestedAthletes' => fn () => AthleteResource::collection(
                $mutuals->annotate($viewer, $suggested->handle($viewer, 5))
            )->resolve($request),
            'upcomingEvents' => fn () => EventEditionCardResource::collection(
                $events->publishedEditions(['status' => 'upcoming'], perPage: 4)->getCollection()
            )->resolve($request),
            'composer' => fn () => $viewer === null ? null : $this->composerData($viewer),
            'visibilityOptions' => $this->visibilityOptions(),
            'reportReasons' => $this->reportReasons(),
            'limits' => [
                'caption_max' => (int) config('finisher.social.moment_caption_max'),
                'max_photos' => (int) config('finisher.social.moment_max_photos'),
                'photo_max_kb' => (int) config('finisher.social.moment_photo_max_kb'),
            ],
        ]);
    }

    /**
     * Followers / following of any athlete the viewer may fully see
     * (/@username/seguidores, /@username/siguiendo).
     */
    public function profileConnections(Request $request, AthleteProfile $athleteProfile, string $list, MutualConnections $mutuals, PeopleQuery $people): Response
    {
        $viewer = $request->user();
        $athleteProfile->loadMissing('user');
        abort_unless($athleteProfile->user !== null && $this->visibility->canViewProfile($athleteProfile, $viewer), 404);

        $tab = $list === 'seguidores' ? 'seguidores' : 'siguiendo';
        $ownerId = $athleteProfile->user_id;
        $ids = $tab === 'siguiendo'
            ? AthleteFollow::query()->where('follower_id', $ownerId)->select('following_id')
            : AthleteFollow::query()->where('following_id', $ownerId)->select('follower_id');
        $search = trim(mb_substr($request->string('q')->toString(), 0, 60));

        $page = $people->search($this->visibility->scopeListableUsers(User::query()->whereIn('users.id', $ids), $viewer), $search)
            ->with('athleteProfile.mainSport')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(30)
            ->withQueryString();

        $mutuals->annotate($viewer, $page->getCollection());

        return Inertia::render('community/Connections', [
            'tab' => $tab,
            'q' => $search,
            'owner' => [
                'username' => $athleteProfile->username,
                'name' => $athleteProfile->user->name,
                'photo_url' => $athleteProfile->profile_photo_path ? Storage::disk('public')->url($athleteProfile->profile_photo_path) : null,
            ],
            'athletes' => [
                'data' => AthleteResource::collection($page->getCollection())->resolve($request),
                'links' => $page->linkCollection()->toArray(),
                'total' => $page->total(),
            ],
            'counts' => [
                'siguiendo' => AthleteFollow::query()->where('follower_id', $ownerId)->count(),
                'seguidores' => AthleteFollow::query()->where('following_id', $ownerId)->count(),
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
            ->where(fn ($q) => $q->whereNull('hidden_at')->when($viewer !== null, fn ($q) => $q->orWhere('user_id', $viewer->id)))
            ->orderBy('id')
            ->limit((int) config('finisher.social.comments_per_page') * 3)
            ->get();

        return Inertia::render('community/Show', [
            'post' => (new MomentResource($this->moments->load($moment, $viewer)))->resolve($request),
            'comments' => MomentCommentResource::collection($comments)->resolve($request),
            'commentMax' => (int) config('finisher.social.comment_max'),
            'canInteract' => $viewer !== null && Gate::forUser($viewer)->allows('interact', $moment),
            'reportReasons' => $this->reportReasons(),
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

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Publicado. Ya es parte de la comunidad.']);

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

    /**
     * The web has a single reaction toggle: turning it off clears any
     * reaction of this person (also a mobile "like").
     */
    public function uncelebrate(Request $request, LegacyMoment $moment, ReactToMoment $react): RedirectResponse
    {
        $this->authorizeInteraction($request->user(), $moment);
        $react->remove($request->user(), $moment, null);

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
        abort_unless($athleteProfile->user !== null && $this->visibility->canSeeProfileShell($athleteProfile, $request->user()), 404);

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

    public function block(Request $request, AthleteProfile $athleteProfile, BlockUser $block): RedirectResponse
    {
        $athleteProfile->loadMissing('user');
        abort_if($athleteProfile->user === null, 404);

        try {
            $block->block($request->user(), $athleteProfile->user);
        } catch (SocialActionNotAllowedException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bloqueaste a este atleta. Ya no verán su actividad entre ustedes.']);

        // Their profile is no longer visible to you — leave it.
        return to_route('community.index');
    }

    public function unblock(Request $request, AthleteProfile $athleteProfile, BlockUser $block): RedirectResponse
    {
        $athleteProfile->loadMissing('user');
        abort_if($athleteProfile->user === null, 404);

        $block->unblock($request->user(), $athleteProfile->user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Desbloqueaste a este atleta.']);

        return back();
    }

    /**
     * Report a post, comment or profile (same CreateReport action as the
     * app: you can only report what you can see, never your own content).
     */
    public function report(Request $request, CreateReport $create): RedirectResponse
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::enum(ReportTargetType::class)],
            'target' => ['required', 'string', 'max:100'],
            'reason' => ['required', Rule::in(config('finisher.social.report_reasons'))],
            'details' => ['nullable', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Elige un motivo.',
        ]);

        try {
            $create->handle($request->user(), ReportTargetType::from($data['target_type']), $data['target'], $data['reason'], $data['details'] ?? null);
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (SocialActionNotAllowedException $e) {
            throw ValidationException::withMessages(['reason' => $e->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Gracias. Nuestro equipo revisará tu reporte.']);

        return back();
    }

    private function authorizeInteraction(User $user, LegacyMoment $moment): void
    {
        $moment->loadMissing('author.athleteProfile');
        abort_unless(Gate::forUser($user)->allows('interact', $moment), 404);
    }

    /**
     * What the composer can attach — only the viewer's OWN participations,
     * medals and gear (CreateMoment re-checks ownership server-side).
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
                'event_edition_id' => $p->event_edition_id,
                'label' => trim(($p->eventEdition?->event->name ?? 'Evento').' · '.($p->eventRace->name ?? ''), ' ·'),
                'date' => $p->eventEdition?->event_date?->toDateString(),
            ]);

        // Any published event from the last 90 days or the next 60 — you can
        // post about a race you cheered at, not only ones you ran.
        $editions = EventEdition::query()
            ->where('status', EditionStatus::Published)
            ->whereBetween('event_date', [now()->subDays(90)->toDateString(), now()->addDays(60)->toDateString()])
            ->with('event:id,name')
            ->orderByDesc('event_date')
            ->limit(40)
            ->get(['id', 'event_id', 'name', 'event_date'])
            ->map(fn (EventEdition $edition) => [
                'id' => $edition->id,
                'label' => $edition->event->name ?? $edition->name,
                'date' => $edition->event_date->toDateString(),
            ]);

        $gear = $athlete === null ? collect() : AthleteOwnedProduct::query()
            ->where('athlete_id', $athlete->id)
            ->with('product:id,name')
            ->latest('id')
            ->limit(30)
            ->get(['id', 'uuid', 'product_id'])
            ->map(fn (AthleteOwnedProduct $item) => ['uuid' => $item->uuid, 'label' => $item->product->name ?? 'Producto']);

        return [
            'has_profile' => $profile !== null,
            'participations' => $participations->values(),
            'editions' => $editions->values(),
            'medals' => Medal::query()
                ->where('user_id', $viewer->id)
                ->latest('id')
                ->limit(30)
                ->get(['uuid', 'title'])
                ->map(fn (Medal $medal) => ['uuid' => $medal->uuid, 'title' => $medal->title])
                ->values(),
            'gear' => $gear->values(),
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
            ['value' => MomentVisibility::Private->value, 'label' => 'Solo yo', 'description' => 'Queda en tu perfil, nadie más lo ve'],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function reportReasons(): array
    {
        return ReportReasons::options();
    }
}
