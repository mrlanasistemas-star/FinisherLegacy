<?php

namespace App\Support\Notifications;

use App\Models\User;
use App\Notifications\AdminAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * Unread counts for the customer notification center — the number the web
 * bell, the app badge and the iOS push `badge` all show. Admin alerts
 * (App\Notifications\AdminAlert) live in the admin bell and are excluded
 * here, so an admin's personal badge never counts back-office alerts.
 */
class UnreadCounter
{
    /**
     * Restrict a `$user->notifications()` / `unreadNotifications()` query to
     * the customer center (everything except admin alerts).
     *
     * @template T of Builder|MorphMany
     *
     * @param  T  $query
     * @return T
     */
    public static function customer(Builder|MorphMany $query): Builder|MorphMany
    {
        return $query->where('type', '!=', AdminAlert::class);
    }

    /**
     * @template T of Builder|MorphMany
     *
     * @param  T  $query
     * @return T
     */
    public static function admin(Builder|MorphMany $query): Builder|MorphMany
    {
        return $query->where('type', AdminAlert::class);
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, int> user id => unread customer notifications
     */
    public function forUsers(array $userIds): array
    {
        $userIds = array_values(array_unique($userIds));

        if ($userIds === []) {
            return [];
        }

        $counts = DB::table('notifications')
            ->where('notifiable_type', (new User)->getMorphClass())
            ->whereIn('notifiable_id', $userIds)
            ->whereNull('read_at')
            ->where('type', '!=', AdminAlert::class)
            ->groupBy('notifiable_id')
            ->selectRaw('notifiable_id, count(*) as aggregate')
            ->pluck('aggregate', 'notifiable_id');

        $result = [];

        foreach ($userIds as $id) {
            $result[$id] = (int) ($counts[$id] ?? 0);
        }

        return $result;
    }
}
