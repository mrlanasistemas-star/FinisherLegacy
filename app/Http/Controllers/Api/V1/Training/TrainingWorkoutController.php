<?php

namespace App\Http\Controllers\Api\V1\Training;

use App\Actions\Training\Workouts\FinishWorkout;
use App\Actions\Training\Workouts\OpenWorkoutTrackingSession;
use App\Actions\Training\Workouts\ShareWorkoutAsMoment;
use App\Actions\Training\Workouts\StartWorkout;
use App\Enums\WorkoutSource;
use App\Enums\WorkoutStatus;
use App\Exceptions\WorkoutStateException;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Training\FinishWorkoutRequest;
use App\Http\Requests\Training\ShareWorkoutRequest;
use App\Http\Requests\Training\StoreWorkoutRequest;
use App\Http\Requests\Training\UpdateWorkoutRequest;
use App\Models\Sport;
use App\Models\TrainingWorkout;
use App\Models\User;
use App\Queries\Training\GetWorkoutStats;
use App\Support\Training\WorkoutPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Modo entrenamiento — `/api/v1/training/workouts/*` (contract in
 * docs/renovacion/entrenamientos.md § Entrenamientos registrados).
 *
 * Owner-only through TrainingWorkoutPolicy; any uuid that isn't the
 * caller's answers 404 (never 403). State rules live in the Actions
 * (StartWorkout, FinishWorkout, ShareWorkoutAsMoment); conflicts render as
 * 409 CONFLICT with `error.details.reason`.
 */
class TrainingWorkoutController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /** `GET training/sports` — activities the athlete can pick, with GPS default and pace/speed metric. */
    public function sports(): JsonResponse
    {
        return $this->respond(
            Sport::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Sport $sport) => WorkoutPresenter::sport($sport->id, $sport->name, $sport->slug))
                ->values(),
        );
    }

    /** `GET training/workouts?source=live|manual&sport_id=&page=` — completed history, newest first, 20 per page. */
    public function index(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $filters = $request->validate([
            'source' => ['nullable', Rule::enum(WorkoutSource::class)],
            'sport_id' => ['nullable', 'integer'],
        ]);

        $page = TrainingWorkout::query()
            ->where('user_id', $user->id)
            ->where('status', WorkoutStatus::Completed)
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when($filters['sport_id'] ?? null, fn ($q, $sport) => $q->where('sport_id', $sport))
            ->with('sport:id,name,slug')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->paginate(20);

        return $this->respond(
            collect($page->items())->map(fn (TrainingWorkout $w) => WorkoutPresenter::present($w))->values(),
            meta: [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        );
    }

    /** `GET training/workouts/active` — the running live workout, or `data: null`. */
    public function active(Request $request): JsonResponse
    {
        $workout = TrainingWorkout::query()
            ->where('user_id', $this->sanctumUser($request)->id)
            ->where('status', WorkoutStatus::InProgress)
            ->latest('started_at')
            ->first();

        return $this->respond($workout === null ? null : WorkoutPresenter::present($workout));
    }

    /** `GET training/workouts/stats?period=week|month|year|all` */
    public function stats(Request $request, GetWorkoutStats $stats): JsonResponse
    {
        $data = $request->validate(['period' => ['nullable', Rule::in(GetWorkoutStats::PERIODS)]]);

        return $this->respond($stats->handle($this->sanctumUser($request), $data['period'] ?? 'month'));
    }

    /**
     * `POST training/workouts` — 201 created; 200 when `client_uuid` replays
     * an earlier start; 409 `workout_in_progress` (with its uuid) when a live
     * workout is already running.
     */
    public function store(StoreWorkoutRequest $request, StartWorkout $start): JsonResponse
    {
        $result = $start->handle($this->sanctumUser($request), $request->validated());

        if ($result['conflict']) {
            throw new WorkoutStateException(
                __('Ya tienes un entrenamiento en curso. Continúalo o termínalo antes de empezar otro.'),
                'workout_in_progress',
                ['workout_uuid' => $result['workout']->uuid],
            );
        }

        return $this->respond(
            WorkoutPresenter::present($result['workout']),
            $result['created'] ? __('Entrenamiento guardado.') : null,
            status: $result['created'] ? 201 : 200,
        );
    }

    /** `GET training/workouts/{uuid}` — includes the route polyline. */
    public function show(Request $request, string $uuid): JsonResponse
    {
        return $this->respond(WorkoutPresenter::present($this->own($request, $uuid, 'view'), withRoute: true));
    }

    /** `PATCH training/workouts/{uuid}` — heartbeat while running, or edit once finished. */
    public function update(UpdateWorkoutRequest $request, string $uuid): JsonResponse
    {
        $workout = $this->own($request, $uuid, 'update');
        $data = $request->validated();

        if ($workout->status === WorkoutStatus::Discarded) {
            throw new WorkoutStateException(__('Este entrenamiento ya fue descartado.'), 'workout_discarded');
        }

        if ($workout->isInProgress()) {
            $workout->fill(array_intersect_key($data, array_flip(['duration_s', 'moving_s', 'notes', 'sport_id'])));
            if ($workout->uses_gps && array_key_exists('distance_m', $data)) {
                $workout->distance_m = $data['distance_m'];
            }
            $workout->last_heartbeat_at = now();
        } else {
            $workout->fill(array_intersect_key($data, array_flip(['notes', 'visibility', 'sport_id'])));
            if ($workout->source === WorkoutSource::Manual) {
                $workout->fill(array_intersect_key($data, array_flip(['duration_s', 'distance_m', 'started_at'])));
                $workout->ended_at = $workout->started_at->copy()->addSeconds($workout->duration_s);
            }
            $workout->deriveAverages();
        }

        $workout->save();

        return $this->respond(WorkoutPresenter::present($workout), $workout->isInProgress() ? null : __('Cambios guardados.'));
    }

    /** `POST training/workouts/{uuid}/finish` — idempotent. */
    public function finish(FinishWorkoutRequest $request, string $uuid, FinishWorkout $finish): JsonResponse
    {
        $workout = $finish->handle($this->own($request, $uuid, 'update'), $request->validated());

        return $this->respond(WorkoutPresenter::present($workout), __('Entrenamiento guardado.'));
    }

    /** `POST training/workouts/{uuid}/discard` — only a running workout; idempotent once discarded. */
    public function discard(Request $request, string $uuid, OpenWorkoutTrackingSession $tracking): JsonResponse
    {
        $workout = $this->own($request, $uuid, 'update');

        if ($workout->status === WorkoutStatus::Completed && $workout->auto_closed_at === null) {
            throw new WorkoutStateException(__('Este entrenamiento ya está guardado. Si no lo quieres, elimínalo.'), 'workout_completed');
        }

        if ($workout->status !== WorkoutStatus::Discarded || $workout->auto_closed_at !== null) {
            $workout->forceFill([
                'status' => WorkoutStatus::Discarded,
                'ended_at' => $workout->ended_at ?? now(),
                'auto_closed_at' => null,
            ])->save();
        }
        $tracking->close($workout);

        return $this->respond(WorkoutPresenter::present($workout), __('Entrenamiento descartado.'));
    }

    /** `DELETE training/workouts/{uuid}` — removes it from the history (a shared Moment stays the athlete's to delete). */
    public function destroy(Request $request, string $uuid, OpenWorkoutTrackingSession $tracking): Response
    {
        $workout = $this->own($request, $uuid, 'delete');
        $tracking->close($workout);
        $workout->delete();

        return response()->noContent();
    }

    /**
     * `POST training/workouts/{uuid}/live` — "Compartir con mi familia":
     * opens (or reuses) the workout's TrackingSession. The app then streams
     * points to `tracking/sessions/{uuid}/points` and shares it with
     * `tracking/sessions/{uuid}/sharing`. Nothing is shared by this call.
     */
    public function live(Request $request, string $uuid, OpenWorkoutTrackingSession $open): JsonResponse
    {
        $session = $open->handle($this->own($request, $uuid, 'share'));

        return $this->respond([
            'tracking_session_uuid' => $session->uuid,
            'started_at' => $session->started_at->toIso8601String(),
            'batch_max' => (int) config('tracking.batch_max', 100),
        ], status: $session->wasRecentlyCreated ? 201 : 200);
    }

    /** `POST training/workouts/{uuid}/share` — explicit Legacy Moment, private by default. */
    public function share(ShareWorkoutRequest $request, string $uuid, ShareWorkoutAsMoment $share): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $workout = $this->own($request, $uuid, 'share');

        $moment = $share->handle($user, $workout, $request->visibility(), $request->validated('caption'));

        return $this->respond(
            ['workout' => WorkoutPresenter::present($workout), 'moment' => ['uuid' => $moment->uuid, 'visibility' => $moment->visibility->value]],
            __('Tu momento ya es parte de tu Legacy.'),
            status: 201,
        );
    }

    /** The caller's workout or a 404 — never a 403 that would confirm someone else's uuid exists. */
    private function own(Request $request, string $uuid, string $ability): TrainingWorkout
    {
        /** @var User $user */
        $user = $this->sanctumUser($request);
        $workout = TrainingWorkout::query()->where('uuid', $uuid)->with('sport:id,name,slug')->firstOrFail();
        abort_unless(Gate::forUser($user)->allows($ability, $workout), 404);

        return $workout;
    }
}
