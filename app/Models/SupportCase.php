<?php

namespace App\Models;

use App\Enums\SupportCaseStatus;
use App\Enums\SupportCaseType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/** An incident about a tag and/or order: activation help, NFC/QR failure, defect, return, replacement. */
#[Fillable([
    'uuid', 'number', 'type', 'status', 'priority', 'legacy_tag_id', 'replacement_tag_id', 'order_id', 'ally_id',
    'reporter_user_id', 'assigned_user_id', 'opened_by_user_id', 'contact_email', 'subject', 'description',
    'resolution', 'refund_minor', 'resolved_at',
])]
class SupportCase extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'type' => SupportCaseType::class,
            'status' => SupportCaseStatus::class,
            'refund_minor' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<LegacyTag, $this> */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(LegacyTag::class, 'legacy_tag_id');
    }

    /** @return BelongsTo<LegacyTag, $this> */
    public function replacementTag(): BelongsTo
    {
        return $this->belongsTo(LegacyTag::class, 'replacement_tag_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Ally, $this> */
    public function ally(): BelongsTo
    {
        return $this->belongsTo(Ally::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'type', 'assigned_user_id', 'replacement_tag_id', 'refund_minor'])->logOnlyDirty();
    }
}
