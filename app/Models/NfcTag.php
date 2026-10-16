<?php

namespace App\Models;

use App\Enums\NfcTagStatus;
use Database\Factories\NfcTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One physical NFC chip (docs/NFC.md). `token` is the random public id the
 * chip carries as /n/{token}; `uid_hash` is an optional keyed hash of the
 * hardware UID — never shown anywhere.
 */
#[Fillable([
    'token', 'uid_hash', 'status', 'batch_code', 'activated_at', 'last_tapped_at',
    'tap_count', 'metadata', 'created_by_user_id',
])]
#[Hidden(['uid_hash'])]
class NfcTag extends Model
{
    /** @use HasFactory<NfcTagFactory> */
    use HasFactory, LogsActivity;

    protected function casts(): array
    {
        return [
            'status' => NfcTagStatus::class,
            'activated_at' => 'datetime',
            'last_tapped_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /** @return HasMany<NfcAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(NfcAssignment::class);
    }

    /**
     * The open assignment (at most one at a time).
     *
     * @return HasOne<NfcAssignment, $this>
     */
    public function activeAssignment(): HasOne
    {
        return $this->hasOne(NfcAssignment::class)->whereNull('ended_at')->latestOfMany();
    }

    /** @return HasMany<NfcTap, $this> */
    public function taps(): HasMany
    {
        return $this->hasMany(NfcTap::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function shortToken(): string
    {
        return '…'.substr($this->token, -6);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'batch_code', 'activated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
