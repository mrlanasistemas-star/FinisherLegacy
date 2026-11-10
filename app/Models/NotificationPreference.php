<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user's switches for one notification category
 * (`scope` customer|admin). Read and written only through
 * App\Support\Notifications\NotificationPreferences, which supplies the
 * defaults when no row exists.
 */
#[Fillable(['user_id', 'scope', 'category', 'in_app', 'push', 'email'])]
class NotificationPreference extends Model
{
    public const string SCOPE_CUSTOMER = 'customer';

    public const string SCOPE_ADMIN = 'admin';

    protected function casts(): array
    {
        return [
            'in_app' => 'boolean',
            'push' => 'boolean',
            'email' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
