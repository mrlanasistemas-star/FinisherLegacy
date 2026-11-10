<?php

namespace App\Models;

use App\Contracts\Media\OwnsMediaFiles;
use App\Enums\CheerIcon;
use App\Enums\CheerMessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A cheer left through a CheerLink (Entrenamientos): a voice note and/or a
 * short text for the athlete to play during training. The audio is a
 * PRIVATE MediaFile whose `mediable` is this row — only the recipient may
 * open it (OwnsMediaFiles), and clients only ever receive short-lived
 * signed URLs.
 *
 * @property int $id
 * @property string $uuid
 * @property int $cheer_link_id
 * @property int $recipient_user_id
 * @property string $sender_name
 * @property string|null $relationship
 * @property string|null $body
 * @property CheerIcon $icon
 * @property int|null $audio_media_file_id
 * @property int $duration_seconds
 * @property CheerMessageStatus $status
 * @property Carbon|null $listened_at
 * @property bool $favorite
 * @property string|null $sender_ip_hash
 * @property Carbon $created_at
 * @property-read CheerLink $link
 * @property-read MediaFile|null $audio
 */
#[Fillable([
    'uuid', 'cheer_link_id', 'recipient_user_id', 'sender_name', 'relationship', 'body', 'icon',
    'audio_media_file_id', 'duration_seconds', 'status', 'listened_at', 'favorite', 'sender_ip_hash',
])]
class CheerMessage extends Model implements OwnsMediaFiles
{
    protected static function booted(): void
    {
        static::creating(function (CheerMessage $message) {
            $message->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'icon' => CheerIcon::class,
            'status' => CheerMessageStatus::class,
            'listened_at' => 'datetime',
            'favorite' => 'boolean',
            'duration_seconds' => 'integer',
        ];
    }

    /** @return BelongsTo<CheerLink, $this> */
    public function link(): BelongsTo
    {
        return $this->belongsTo(CheerLink::class, 'cheer_link_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    /** @return BelongsTo<MediaFile, $this> */
    public function audio(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'audio_media_file_id');
    }

    /**
     * @param  Builder<CheerMessage>  $query
     * @return Builder<CheerMessage>
     */
    public function scopeVisibleFor(Builder $query, User $user): Builder
    {
        return $query->where('recipient_user_id', $user->id)->where('status', CheerMessageStatus::Visible->value);
    }

    /** Signed, expiring URL (finisher.media.urls.private_ttl_minutes); null while processing. */
    public function audioUrl(): ?string
    {
        return $this->audio?->url();
    }

    public function canViewMedia(?User $viewer, MediaFile $file): bool
    {
        return $viewer !== null && $viewer->id === $this->recipient_user_id;
    }

    public function mediaIsPublic(MediaFile $file): bool
    {
        return false;
    }

    public function syncFromMediaFile(MediaFile $file): void
    {
        if ($file->duration_ms !== null && $file->duration_ms > 0) {
            $this->forceFill(['duration_seconds' => (int) round($file->duration_ms / 1000)])->save();
        }
    }
}
