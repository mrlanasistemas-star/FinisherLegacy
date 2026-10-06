<?php

namespace App\Actions\Photos;

use App\Enums\PhotographerStatus;
use App\Models\PhotographerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns an account into a photographer applicant: profile in `pending`
 * (an admin approves before photos can be uploaded) and the
 * `photographer` role (portal access). Idempotent for the same user.
 */
class RegisterPhotographer
{
    /**
     * @param  array<string, mixed>  $data  display_name, city?, phone?, instagram_url?, portfolio_url?, bio?
     */
    public function handle(User $user, array $data): PhotographerProfile
    {
        return DB::transaction(function () use ($user, $data) {
            $profile = $user->photographerProfile()->first() ?? new PhotographerProfile([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'status' => PhotographerStatus::Pending,
                'slug' => $this->uniqueSlug((string) $data['display_name']),
            ]);

            $profile->fill([
                'display_name' => $data['display_name'],
                'city' => $data['city'] ?? null,
                'phone' => $data['phone'] ?? null,
                'instagram_url' => $data['instagram_url'] ?? null,
                'portfolio_url' => $data['portfolio_url'] ?? null,
                'bio' => $data['bio'] ?? null,
            ])->save();

            if (! $user->hasRole('photographer')) {
                $user->assignRole('photographer');
            }

            return $profile;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'fotografo';
        $slug = $base;
        $i = 2;

        while (PhotographerProfile::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
