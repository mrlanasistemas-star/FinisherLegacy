<?php

namespace App\Models;

use App\Enums\LegacyPlateFieldAlignment;
use App\Enums\LegacyPlateFieldKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where one dynamic field (athlete_name, race_label, official_time, pace,
 * qr) sits inside its LegacyPlateModel's engraving_area — brief §6.
 */
#[Fillable([
    'legacy_plate_model_id', 'field_key', 'x', 'y', 'width', 'height',
    'font_size', 'alignment', 'max_chars', 'required', 'visible', 'sort_order',
])]
class LegacyPlateModelField extends Model
{
    protected function casts(): array
    {
        return [
            'field_key' => LegacyPlateFieldKey::class,
            'x' => 'decimal:2',
            'y' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'font_size' => 'decimal:2',
            'alignment' => LegacyPlateFieldAlignment::class,
            'required' => 'boolean',
            'visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<LegacyPlateModel, $this> */
    public function legacyPlateModel(): BelongsTo
    {
        return $this->belongsTo(LegacyPlateModel::class);
    }
}
