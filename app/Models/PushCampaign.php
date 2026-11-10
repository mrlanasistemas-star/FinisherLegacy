<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A push campaign composed in Administración → Notificaciones → Campañas
 * push (docs/renovacion/campanas-push.md): content, deep-link destination,
 * audience definition, timing and aggregated delivery counters.
 *
 * Lifecycle: draft → scheduled → sending → sent | failed (con errores),
 * or cancelled from draft/scheduled. Only draft and scheduled campaigns
 * can be edited, rescheduled or cancelled; everything after `sending` is
 * history. Statuses are plain strings (constants below) so the mobile and
 * web sides can read them without an enum import.
 *
 * Delivery counters: `sent_count` = messages the provider accepted into
 * its queue; `delivered_count` / `delivery_failed_count` = what the push
 * receipts later confirmed (push:check-receipts). Queued − delivered −
 * failed = still waiting for its receipt.
 *
 * @property array<string, mixed>|null $audience_params
 * @property int $sent_count
 * @property int $delivered_count
 * @property int $delivery_failed_count
 */
#[Fillable([
    'uuid', 'name', 'title', 'body', 'image_path', 'include_logo', 'color', 'channel', 'category', 'save_to_inbox',
    'link_type', 'link_value', 'link_label', 'audience_type', 'audience_params',
    'status', 'scheduled_at', 'started_at', 'completed_at', 'cancelled_at',
    'audience_users_count', 'audience_devices_count', 'batches_total', 'batches_done', 'sent_count', 'inbox_count', 'skipped_count', 'failed_count', 'delivered_count', 'delivery_failed_count', 'last_error',
    'duplicated_from_id', 'created_by', 'updated_by', 'sent_by', 'cancelled_by',
])]
class PushCampaign extends Model
{
    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_SCHEDULED = 'scheduled';

    public const string STATUS_SENDING = 'sending';

    public const string STATUS_SENT = 'sent';

    public const string STATUS_CANCELLED = 'cancelled';

    public const string STATUS_FAILED = 'failed';

    /** @var array<string, string> */
    public const array STATUS_LABELS = [
        self::STATUS_DRAFT => 'Borrador',
        self::STATUS_SCHEDULED => 'Programada',
        self::STATUS_SENDING => 'Enviando',
        self::STATUS_SENT => 'Enviada',
        self::STATUS_CANCELLED => 'Cancelada',
        self::STATUS_FAILED => 'Con errores',
    ];

    /**
     * Broadcastable App\Enums\NotificationCategory values only — the
     * transactional ones (pedidos, comunidad, entrenamientos, soporte) can
     * never be sent as a campaign. `avisos` is limited to targeted
     * audiences (CampaignRequest): "Todos" and "Suscritos" are always
     * `promociones`.
     *
     * @var list<string>
     */
    public const array CATEGORIES = ['promociones', 'avisos'];

    /** Audiences that may only carry the `promociones` category. */
    public const array PROMO_ONLY_AUDIENCES = ['all', 'opted_in'];

    public const string TIMEZONE = 'America/Mexico_City';

    protected static function booted(): void
    {
        static::creating(function (self $campaign) {
            $campaign->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'include_logo' => 'boolean',
            'save_to_inbox' => 'boolean',
            'audience_params' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'audience_users_count' => 'integer',
            'audience_devices_count' => 'integer',
            'batches_total' => 'integer',
            'batches_done' => 'integer',
            'sent_count' => 'integer',
            'inbox_count' => 'integer',
            'skipped_count' => 'integer',
            'failed_count' => 'integer',
            'delivered_count' => 'integer',
            'delivery_failed_count' => 'integer',
        ];
    }

    /** Admin URLs use the uuid, never the sequential id. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path !== null ? Storage::disk('public')->url($this->image_path) : null;
    }

    /** @return HasMany<PushCampaignRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(PushCampaignRecipient::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** @return BelongsTo<self, $this> */
    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicated_from_id');
    }
}
