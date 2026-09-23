<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Actions\Social\CreateMoment;
use App\Actions\Social\DeleteMoment;
use App\Actions\Social\ReactToMoment;
use App\Enums\MomentReactionType;
use App\Enums\MomentVisibility;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMomentRequest;
use App\Http\Resources\Api\V1\MomentResource;
use App\Models\LegacyMoment;
use App\Models\User;
use App\Queries\Social\MomentQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Legacy Moments: create / read / edit caption+visibility / delete, and
 * idempotent reactions. Visibility is enforced by LegacyMomentPolicy (→
 * SocialVisibility); a Moment the viewer may not see answers 404, never
 * 403, so its existence isn't leaked.
 */
class MomentController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function __construct(private readonly MomentQuery $moments) {}

    public function store(StoreMomentRequest $request, CreateMoment $create): JsonResponse
    {
        $user = $this->sanctumUser($request);

        /** @var list<UploadedFile> $photos */
        $photos = array_values($request->file('photos', []));

        $moment = $create->handle($user, $request->safe()->except('photos'), $photos);

        return $this->respond(new MomentResource($this->moments->load($moment, $user)), 'Tu momento ya es parte de tu Legacy.', status: 201);
    }

    public function show(Request $request, LegacyMoment $moment): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $moment->loadMissing('author.athleteProfile');
        abort_unless(Gate::forUser($user)->allows('view', $moment), 404);

        return $this->respond(new MomentResource($this->moments->load($moment, $user)));
    }

    public function update(Request $request, LegacyMoment $moment): JsonResponse
    {
        $user = $this->sanctumUser($request);
        abort_unless(Gate::forUser($user)->allows('update', $moment), 404);

        $data = $request->validate([
            'caption' => ['sometimes', 'nullable', 'string', 'max:'.config('finisher.social.moment_caption_max')],
            'visibility' => ['sometimes', Rule::enum(MomentVisibility::class)],
        ]);

        $moment->update($data);

        return $this->respond(new MomentResource($this->moments->load($moment, $user)), 'Cambios guardados.');
    }

    public function destroy(Request $request, LegacyMoment $moment, DeleteMoment $delete): JsonResponse
    {
        abort_unless(Gate::forUser($this->sanctumUser($request))->allows('delete', $moment), 404);

        $delete->handle($moment);

        return $this->respond(null, 'Momento eliminado.');
    }

    public function react(Request $request, LegacyMoment $moment, string $type, ReactToMoment $react): JsonResponse
    {
        return $this->applyReaction($request, $moment, $type, fn ($user, $reaction) => $react->add($user, $moment, $reaction));
    }

    public function unreact(Request $request, LegacyMoment $moment, string $type, ReactToMoment $react): JsonResponse
    {
        return $this->applyReaction($request, $moment, $type, fn ($user, $reaction) => $react->remove($user, $moment, $reaction));
    }

    /**
     * @param  callable(User, MomentReactionType): void  $apply
     */
    private function applyReaction(Request $request, LegacyMoment $moment, string $type, callable $apply): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $reaction = MomentReactionType::tryFrom($type);
        abort_if($reaction === null, 404);

        $moment->loadMissing('author.athleteProfile');
        abort_unless(Gate::forUser($user)->allows('interact', $moment), 404);

        $apply($user, $reaction);

        $fresh = $this->moments->load($moment, $user);

        return $this->respond([
            'reactions' => [
                'like' => (int) $fresh->likes_count,
                'cheer' => (int) $fresh->cheers_count,
            ],
            'my_reactions' => $fresh->viewerReactions->map(fn ($r) => $r->type->value)->values(),
        ]);
    }
}
