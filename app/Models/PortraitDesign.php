<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved Portarretrato personalization: main photo (a MediaFile of the
 * story, never a copy), crop, template and words. Rendered by the same
 * PortraitComposer as the builder (App\Services\Portrait\PortraitDesigns).
 * Ordering it attaches `portrait_design_id` to the cart line; the OrderItem
 * keeps a frozen snapshot for production.
 *
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property int $legacy_story_id
 * @property int|null $media_file_id
 * @property string|null $title
 * @property string $template
 * @property array{x: float, y: float, w: float, h: float}|null $crop
 * @property array<string, string|null>|null $fields
 */
#[Fillable(['uuid', 'user_id', 'legacy_story_id', 'media_file_id', 'title', 'template', 'crop', 'fields', 'last_ordered_at'])]
class PortraitDesign extends Model
{
    protected function casts(): array
    {
        return [
            'crop' => 'array',
            'fields' => 'array',
            'last_ordered_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<LegacyStory, $this> */
    public function story(): BelongsTo
    {
        return $this->belongsTo(LegacyStory::class, 'legacy_story_id');
    }

    /** @return BelongsTo<MediaFile, $this> */
    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }
}
