<?php

namespace App\Support\Notifications;

use App\Enums\AdminAlertCategory;
use App\Enums\NotificationCategory;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The one place notification preferences are read and written
 * (docs/renovacion/notificaciones.md §Preferencias). A missing row means the
 * category default:
 *
 *  - customer: in-app ON, push ON (except Promociones: push OFF).
 *  - admin: in-app always ON (the bell), email per AdminAlertCategory::defaultEmail(),
 *    push ON.
 *
 * Read by SendAthleteNotification (in-app + push), PushSender (push for a
 * category) and NotifyAdmins (email + push) — always server-side, so the web
 * and the app can only change what is stored, never bypass it.
 */
class NotificationPreferences
{
    public const array CHANNELS = ['in_app', 'push', 'email'];

    public function allows(User|int $user, NotificationCategory|AdminAlertCategory $category, string $channel): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        $row = NotificationPreference::query()
            ->where('user_id', $userId)
            ->where('scope', $this->scope($category))
            ->where('category', $category->value)
            ->first();

        return $row !== null ? (bool) $row->{$channel} : $this->default($category, $channel);
    }

    /**
     * The subset of `$userIds` that allow `$channel` for `$category` — one
     * query per chunk, for broadcasts and admin fan-out.
     *
     * @param  iterable<int>  $userIds
     * @return list<int>
     */
    public function filterUsers(iterable $userIds, NotificationCategory|AdminAlertCategory $category, string $channel): array
    {
        $ids = array_values(array_unique(array_map('intval', is_array($userIds) ? $userIds : iterator_to_array($userIds, false))));

        if ($ids === []) {
            return [];
        }

        $default = $this->default($category, $channel);
        $overrides = [];

        foreach (array_chunk($ids, 1000) as $chunk) {
            NotificationPreference::query()
                ->where('scope', $this->scope($category))
                ->where('category', $category->value)
                ->whereIn('user_id', $chunk)
                ->get(['user_id', $channel])
                ->each(function (NotificationPreference $row) use (&$overrides, $channel) {
                    $overrides[$row->user_id] = (bool) $row->{$channel};
                });
        }

        return array_values(array_filter($ids, fn (int $id) => $overrides[$id] ?? $default));
    }

    /**
     * Full matrix for the settings screens (web + app).
     *
     * @return list<array{key: string, label: string, description: string, in_app: bool, push: bool}>
     */
    public function customerMatrix(User $user): array
    {
        $rows = $this->rows($user, NotificationPreference::SCOPE_CUSTOMER);

        return array_map(fn (NotificationCategory $c) => [
            'key' => $c->value,
            'label' => $c->label(),
            'description' => $c->description(),
            'in_app' => (bool) ($rows->get($c->value)->in_app ?? $this->default($c, 'in_app')),
            'push' => (bool) ($rows->get($c->value)->push ?? $this->default($c, 'push')),
        ], NotificationCategory::cases());
    }

    /**
     * Admin categories this user can actually receive (by permission).
     *
     * @return list<array{key: string, label: string, description: string, email: bool, push: bool}>
     */
    public function adminMatrix(User $user): array
    {
        $rows = $this->rows($user, NotificationPreference::SCOPE_ADMIN);

        $categories = array_filter(AdminAlertCategory::cases(), fn (AdminAlertCategory $c) => $user->can($c->permission()));

        return array_values(array_map(fn (AdminAlertCategory $c) => [
            'key' => $c->value,
            'label' => $c->label(),
            'description' => $c->description(),
            'email' => (bool) ($rows->get($c->value)->email ?? $this->default($c, 'email')),
            'push' => (bool) ($rows->get($c->value)->push ?? $this->default($c, 'push')),
        ], $categories));
    }

    /**
     * @param  array<string, array<string, mixed>>  $values  category => [channel => bool]
     */
    public function updateCustomer(User $user, array $values): void
    {
        foreach (NotificationCategory::cases() as $category) {
            if (! isset($values[$category->value])) {
                continue;
            }

            $this->store($user, $category, $values[$category->value], ['in_app', 'push']);
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $values
     */
    public function updateAdmin(User $user, array $values): void
    {
        foreach (AdminAlertCategory::cases() as $category) {
            if (! isset($values[$category->value])) {
                continue;
            }

            $this->store($user, $category, $values[$category->value], ['email', 'push']);
        }
    }

    public function default(NotificationCategory|AdminAlertCategory $category, string $channel): bool
    {
        if ($category instanceof AdminAlertCategory) {
            return match ($channel) {
                'email' => $category->defaultEmail(),
                'push' => $category->defaultPush(),
                default => true,
            };
        }

        return match ($channel) {
            'push' => $category->defaultPush(),
            'email' => false,
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $channels
     */
    private function store(User $user, NotificationCategory|AdminAlertCategory $category, array $values, array $channels): void
    {
        $attributes = [];

        foreach ($channels as $channel) {
            $attributes[$channel] = array_key_exists($channel, $values)
                ? filter_var($values[$channel], FILTER_VALIDATE_BOOLEAN)
                : $this->allows($user, $category, $channel);
        }

        // Unmanaged channels keep their defaults (e.g. admin in-app = always on).
        foreach (array_diff(self::CHANNELS, $channels) as $channel) {
            $attributes[$channel] = $this->default($category, $channel);
        }

        NotificationPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'scope' => $this->scope($category), 'category' => $category->value],
            $attributes,
        );
    }

    /**
     * @return Collection<string, NotificationPreference>
     */
    private function rows(User $user, string $scope): Collection
    {
        return NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('scope', $scope)
            ->get()
            ->keyBy('category');
    }

    private function scope(NotificationCategory|AdminAlertCategory $category): string
    {
        return $category instanceof AdminAlertCategory
            ? NotificationPreference::SCOPE_ADMIN
            : NotificationPreference::SCOPE_CUSTOMER;
    }
}
