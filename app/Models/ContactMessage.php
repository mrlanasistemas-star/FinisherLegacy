<?php

namespace App\Models;

use App\Enums\ContactMessageStatus;
use App\Enums\ContactMessageType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $company
 * @property string $email
 * @property ContactMessageType $type
 * @property string $message
 * @property ContactMessageStatus $status
 * @property Carbon|null $read_at
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'name', 'company', 'email', 'type', 'message', 'status', 'read_at'])]
class ContactMessage extends Model
{
    protected function casts(): array
    {
        return [
            'type' => ContactMessageType::class,
            'status' => ContactMessageStatus::class,
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
