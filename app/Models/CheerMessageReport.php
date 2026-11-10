<?php

namespace App\Models;

use App\Enums\CheerReportReason;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An athlete's report of one specific cheer message (Entrenamientos
 * moderation). Cheers are private between sender and athlete: a report is
 * the ONLY thing that lets staff read that message and play its audio
 * (audited). `sender_ip_hash` is copied so the report survives a purge.
 *
 * @property int $id
 * @property string $uuid
 * @property int|null $cheer_message_id
 * @property int $cheer_link_id
 * @property int $reporter_user_id
 * @property CheerReportReason $reason
 * @property string|null $details
 * @property string|null $sender_ip_hash
 * @property ReportStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $resolution_notes
 * @property Carbon $created_at
 * @property-read CheerMessage|null $message
 * @property-read CheerLink|null $link
 * @property-read User|null $reporter
 */
#[Fillable([
    'uuid', 'cheer_message_id', 'cheer_link_id', 'reporter_user_id', 'reason', 'details',
    'sender_ip_hash', 'status', 'reviewed_by', 'reviewed_at', 'resolution_notes',
])]
class CheerMessageReport extends Model
{
    protected static function booted(): void
    {
        static::creating(function (CheerMessageReport $report) {
            $report->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'reason' => CheerReportReason::class,
            'status' => ReportStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CheerMessage, $this> */
    public function message(): BelongsTo
    {
        return $this->belongsTo(CheerMessage::class, 'cheer_message_id');
    }

    /** @return BelongsTo<CheerLink, $this> */
    public function link(): BelongsTo
    {
        return $this->belongsTo(CheerLink::class, 'cheer_link_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [ReportStatus::Open, ReportStatus::Reviewing], true);
    }
}
