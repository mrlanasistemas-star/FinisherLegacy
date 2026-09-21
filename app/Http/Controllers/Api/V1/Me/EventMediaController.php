<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Media\DeleteAthleteEventMedia;
use App\Actions\Media\ReorderAthleteEventMedia;
use App\Actions\Media\UpdateAthleteEventMediaVisibility;
use App\Actions\Media\UploadAthleteEventMedia;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UploadAthleteEventMediaRequest;
use App\Http\Resources\Api\V1\AthleteEventMediaResource;
use App\Models\AthleteEventMedia;
use App\Models\EventParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * `GET/POST me/events/{participant}/media`, `DELETE .../media/{uuid}`
 * (brief §117) — this whole surface is "my own" media management: every
 * action requires the participant/media to belong to the caller's own
 * Athlete, either directly (index/store/reorder) or via
 * App\Policies\AthleteEventMediaPolicy (update/delete).
 */
class EventMediaController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function index(Request $request, EventParticipant $participant, EnsureAthleteForUser $ensureAthlete): JsonResponse
    {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'event_media_index');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        return $this->respond(AthleteEventMediaResource::collection(
            $participant->media()->orderBy('sort_order')->get(),
        ));
    }

    public function store(
        UploadAthleteEventMediaRequest $request,
        EventParticipant $participant,
        EnsureAthleteForUser $ensureAthlete,
        UploadAthleteEventMedia $upload,
    ): JsonResponse {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'event_media_upload');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        $media = $upload->handle($athlete, $participant, $request->file('file'), $request->boolean('is_public'));

        return $this->respond(new AthleteEventMediaResource($media), 'Archivo subido.', status: 201);
    }

    public function updateVisibility(Request $request, AthleteEventMedia $media, UpdateAthleteEventMediaVisibility $update): JsonResponse
    {
        Gate::forUser($this->sanctumUser($request))->authorize('update', $media);

        return $this->respond(new AthleteEventMediaResource($update->handle($media, $request->boolean('is_public'))));
    }

    public function destroy(Request $request, AthleteEventMedia $media, DeleteAthleteEventMedia $delete): JsonResponse
    {
        Gate::forUser($this->sanctumUser($request))->authorize('delete', $media);
        $delete->handle($media);

        return $this->respond(null, 'Archivo eliminado.');
    }

    public function reorder(Request $request, EventParticipant $participant, EnsureAthleteForUser $ensureAthlete, ReorderAthleteEventMedia $reorder): JsonResponse
    {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'event_media_reorder');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        $mediaIds = array_values(array_map(fn (mixed $id) => (int) $id, $request->array('media_ids')));
        $reorder->handle($participant, $mediaIds);

        return $this->respond(AthleteEventMediaResource::collection(
            $participant->media()->orderBy('sort_order')->get(),
        ));
    }
}
