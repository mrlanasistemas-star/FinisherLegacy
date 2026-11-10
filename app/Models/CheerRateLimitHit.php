<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One throttled public cheer submit (`cheer-submit` limiter). Lets staff
 * spot abused links without reading any message. Only the sender's IP
 * HMAC is kept, never the IP.
 *
 * @property int $id
 * @property int|null $cheer_link_id
 * @property string $scope ip_minute | ip_hour | link_hour
 * @property string|null $sender_ip_hash
 * @property Carbon|null $created_at
 */
#[Fillable(['cheer_link_id', 'scope', 'sender_ip_hash', 'created_at'])]
class CheerRateLimitHit extends Model
{
    public const UPDATED_AT = null;
}
