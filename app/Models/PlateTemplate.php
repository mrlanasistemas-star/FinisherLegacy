<?php

namespace App\Models;

use App\Enums\PlateBackTransform;
use Database\Factories\PlateTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'slug', 'description', 'width_mm', 'height_mm', 'material',
    'orientation', 'safe_margin_mm', 'created_by', 'preview_path', 'active',
    'sport_type', 'back_transform', 'minimum_validated_qr_size_mm',
])]
class PlateTemplate extends Model
{
    /** @use HasFactory<PlateTemplateFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'safe_margin_mm' => 'decimal:2',
            'active' => 'boolean',
            'back_transform' => PlateBackTransform::class,
            'minimum_validated_qr_size_mm' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Plate, $this> */
    public function plates(): HasMany
    {
        return $this->hasMany(Plate::class);
    }

    /** @return HasMany<PlateTemplateVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(PlateTemplateVersion::class);
    }
}
