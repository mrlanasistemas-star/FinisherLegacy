<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LegacyMomentComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LegacyMomentComment
 */
class MomentCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user('sanctum');

        return [
            'uuid' => $this->uuid,
            'body' => $this->body,
            'created_at' => $this->created_at->toIso8601String(),
            'author' => new AthleteResource($this->author),
            // Author of the comment, or author of the Moment it's on — both
            // may remove it.
            'can_delete' => $viewer !== null && ($viewer->id === $this->user_id || $viewer->id === $this->moment?->user_id),
        ];
    }
}
