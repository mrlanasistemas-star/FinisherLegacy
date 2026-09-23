<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Actions\Social\CommentOnMoment;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MomentCommentResource;
use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Services\Social\SocialVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * "Mensajes de apoyo" under a Moment — flat list, oldest first (reads like
 * a conversation), standard pagination. Comments from people the viewer
 * blocked (or who blocked them) are hidden.
 */
class CommentController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function index(Request $request, LegacyMoment $moment, SocialVisibility $visibility): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $moment->loadMissing('author.athleteProfile');
        abort_unless(Gate::forUser($user)->allows('view', $moment), 404);

        $hidden = $visibility->hiddenUserIds($user);

        $comments = $moment->comments()
            ->with(['author.athleteProfile', 'moment'])
            ->whereHas('author')
            ->when($hidden !== [], fn ($q) => $q->whereNotIn('user_id', $hidden))
            ->orderBy('id')
            ->paginate((int) config('finisher.social.comments_per_page'));

        return MomentCommentResource::collection($comments)->response();
    }

    public function store(Request $request, LegacyMoment $moment, CommentOnMoment $comment): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $moment->loadMissing('author.athleteProfile');
        abort_unless(Gate::forUser($user)->allows('interact', $moment), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:'.config('finisher.social.comment_max')],
        ], [
            'body.required' => 'Escribe un mensaje antes de enviarlo.',
            'body.max' => 'Tu mensaje es demasiado largo (máximo :max caracteres).',
        ]);

        $created = $comment->handle($user, $moment, $data['body']);

        return $this->respond(new MomentCommentResource($created->load(['author.athleteProfile', 'moment'])), status: 201);
    }

    public function destroy(Request $request, LegacyMomentComment $comment): JsonResponse
    {
        abort_unless(Gate::forUser($this->sanctumUser($request))->allows('delete', $comment), 404);

        $comment->delete();

        return $this->respond(null, 'Mensaje eliminado.');
    }
}
