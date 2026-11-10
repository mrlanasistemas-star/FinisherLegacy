<?php

namespace App\Models;

use App\Enums\PlateTemplateVersionStatus;
use Database\Factories\PlateTemplateVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'plate_template_id', 'version', 'front_configuration', 'back_configuration',
    'preview_front_path', 'preview_back_path', 'status', 'created_by',
])]
class PlateTemplateVersion extends Model
{
    /** @use HasFactory<PlateTemplateVersionFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'front_configuration' => 'array',
            'back_configuration' => 'array',
            'status' => PlateTemplateVersionStatus::class,
        ];
    }

    /** @return BelongsTo<PlateTemplate, $this> */
    public function plateTemplate(): BelongsTo
    {
        return $this->belongsTo(PlateTemplate::class);
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
