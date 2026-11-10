<?php

namespace App\Actions\Notifications;

use App\Enums\AdminAlertCategory;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\PushMessage;
use App\Support\Notifications\PushSender;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Throwable;

/**
 * Fan-out of one operational alert to every admin allowed to see its
 * category (AdminAlertCategory::permission(), super_admin always) — the bell
 * always, email/push per each admin's preferences
 * (docs/renovacion/notificaciones.md §Alertas admin).
 *
 * `$throttleKey` + `$throttleMinutes` make repeated alerts quiet: the same key
 * fires at most once per window (low stock, failing webhooks…). Never throws:
 * an alert must not break the sale, webhook or request that triggered it.
 */
class NotifyAdmins
{
    public function __construct(
        private readonly PushSender $push,
        private readonly NotificationPreferences $preferences,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     * @return int how many admins were notified (0 when throttled)
     */
    public function handle(
        AdminAlertCategory $category,
        string $event,
        string $title,
        string $message,
        ?string $actionUrl = null,
        array $metadata = [],
        ?string $throttleKey = null,
        int $throttleMinutes = 60,
        string $level = 'info',
    ): int {
        try {
            if ($throttleKey !== null && ! Cache::add(self::throttleCacheKey($category, $throttleKey), 1, now()->addMinutes(max(1, $throttleMinutes)))) {
                return 0;
            }

            $admins = $this->recipients($category);

            if ($admins->isEmpty()) {
                return 0;
            }

            $alert = new AdminAlert($category, $event, $title, $message, $actionUrl, $metadata, $level);
            Notification::send($admins, $alert);

            $pushIds = $this->preferences->filterUsers($admins->pluck('id')->all(), $category, 'push');

            if ($pushIds !== []) {
                $this->push->sendToUsers($pushIds, new PushMessage(
                    title: $title,
                    body: $message,
                    data: ['type' => 'admin.'.$event, 'admin_category' => $category->value],
                    deepLink: $actionUrl !== null ? url($actionUrl) : null,
                    channel: 'avisos',
                ));
            }

            return $admins->count();
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    /**
     * Active users holding the category permission (directly or via a role)
     * plus every super_admin.
     *
     * @return Collection<int, User>
     */
    public function recipients(AdminAlertCategory $category): Collection
    {
        $query = User::query()->where('status', UserStatus::Active);

        try {
            $query->where(fn ($q) => $q->permission($category->permission())->orWhereHas('roles', fn ($r) => $r->where('name', 'super_admin')));
        } catch (PermissionDoesNotExist) {
            $query->whereHas('roles', fn ($r) => $r->where('name', 'super_admin'));
        }

        return $query->limit(200)->get();
    }

    public static function throttleCacheKey(AdminAlertCategory $category, string $throttleKey): string
    {
        return 'admin-alert:'.$category->value.':'.$throttleKey;
    }

    public static function money(int $minor, string $currency = 'MXN'): string
    {
        return '$'.number_format($minor / 100, 2).' '.Str::upper($currency);
    }
}
