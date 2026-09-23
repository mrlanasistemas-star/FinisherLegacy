<?php

namespace App\Services;

use App\Models\AthleteProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Single implementation of "update my Legacy Profile" shared by the web
 * Inertia controller and the /api/v1 controller — file storage/replacement
 * rules live here once, not twice.
 */
class AthleteProfileService
{
    public function __construct(private readonly ImageProcessingService $images) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, ?UploadedFile $profilePhoto, ?UploadedFile $coverPhoto): AthleteProfile
    {
        $profile = $user->athleteProfile ?: $user->athleteProfile()->make();

        $removeProfilePhoto = (bool) ($data['remove_profile_photo'] ?? false);
        $removeCoverPhoto = (bool) ($data['remove_cover_photo'] ?? false);
        unset($data['remove_profile_photo'], $data['remove_cover_photo']);

        $profile->fill($data);

        if ($profilePhoto) {
            $this->replaceImage($profile, 'profile_photo_path', $profilePhoto, cropSquare: 800);
        } elseif ($removeProfilePhoto) {
            $this->removeImage($profile, 'profile_photo_path');
        }

        if ($coverPhoto) {
            $this->replaceImage($profile, 'cover_photo_path', $coverPhoto, cropSquare: null);
        } elseif ($removeCoverPhoto) {
            $this->removeImage($profile, 'cover_photo_path');
        }

        $profile->save();

        return $profile->fresh();
    }

    private function removeImage(AthleteProfile $profile, string $column): void
    {
        if ($profile->{$column}) {
            Storage::disk('public')->delete($profile->{$column});
        }

        $profile->{$column} = null;
    }

    private function replaceImage(AthleteProfile $profile, string $column, UploadedFile $file, ?int $cropSquare): void
    {
        if ($profile->{$column}) {
            Storage::disk('public')->delete($profile->{$column});
        }

        $directory = 'profiles/'.$profile->user_id;
        $processed = $this->images->process($file, $directory, withThumbnail: false, cropSquare: $cropSquare);

        // Profile avatar/cover keep a single optimized version — no separate
        // thumbnail column on athlete_profiles — so the original isn't worth
        // keeping here even when finisher.image.keep_original is true.
        if ($processed['original_path']) {
            Storage::disk('public')->delete($processed['original_path']);
        }

        $profile->{$column} = $processed['display_path'];
    }
}
