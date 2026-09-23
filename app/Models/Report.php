<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Moderation foundation (docs/SOCIAL_ARCHITECTURE.md §Moderación) — a
 * review panel can be built on top later; staff can already query these.
 *
 * @property string $uuid
 * @property ReportTargetType $target_type
 * @property int $target_id
 * @property string $reason
 * @property ReportStatus $status
 */
#[Fillable([
    'uuid', 'reporter_id', 'target_type', 'target_id', 'reason', 'details',
    'status', 'reviewed_by', 'reviewed_at', 'resolution_notes',
])]
class Report extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Report $report) {
            $report->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'target_type' => ReportTargetType::class,
            'status' => ReportStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
