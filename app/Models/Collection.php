<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\CollectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A collection of Finisher Legacy Tag designs — by destination, discipline,
 * event, year or season ("FINISHER LEGACY — COZUMEL 2026"). Only `active`
 * collections whose launch date has passed are public.
 */
#[Fillable([
    'uuid', 'name', 'slug', 'tagline', 'description', 'discipline', 'destination', 'year', 'edition',
    'season', 'event_id', 'status', 'launch_at', 'cover_path', 'hero_path', 'accent_color', 'sort_order',
    'seo_title', 'seo_description',
])]
class Collection extends Model
{
    /** @use HasFactory<CollectionFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'launch_at' => 'datetime',
            'year' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @param  Builder<Collection>  $query
     * @return Builder<Collection>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active->value)
            ->where(fn (Builder $q) => $q->whereNull('launch_at')->orWhere('launch_at', '<=', now()));
    }

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Active && ($this->launch_at === null || $this->launch_at->isPast());
    }

    public function coverUrl(): ?string
    {
        return self::assetUrl($this->cover_path);
    }

    public function heroUrl(): ?string
    {
        return self::assetUrl($this->hero_path) ?? $this->coverUrl();
    }

    /** Uploaded files live on the public disk; seeded art may be a /media path. */
    public static function assetUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return str_starts_with($path, '/') || str_starts_with($path, 'http') ? $path : Storage::disk('public')->url($path);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'slug', 'status', 'launch_at'])->logOnlyDirty();
    }
}
