<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A stored replay for one `Idempotency-Key` on one general `/api/v1/*`
 * write route — see App\Http\Middleware\EnsureApiIdempotencyKey.
 */
#[Fillable(['actor_type', 'actor_id', 'route', 'key', 'response_status', 'response_body'])]
class ApiIdempotencyKey extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
