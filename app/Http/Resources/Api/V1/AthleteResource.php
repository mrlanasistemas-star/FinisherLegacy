<?php

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Compact public identity of an athlete (a User + its AthleteProfile) —
 * what a feed card, comment row, follower list or search result shows.
 * Never email/phone. `is_following` is only present when the caller set
 * it (App\Queries\Social\AthleteListQuery / controllers).
 *
 * @mixin User
 */
class AthleteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->athleteProfile;

        return [
            'username' => $profile?->username,
            'name' => $this->name,
            'photo_url' => $profile?->profile_photo_path ? Storage::disk('public')->url($profile->profile_photo_path) : null,
            'city' => $profile?->city,
            'is_following' => $this->when(isset($this->resource->is_following), fn () => (bool) $this->resource->is_following),
        ];
    }
}
