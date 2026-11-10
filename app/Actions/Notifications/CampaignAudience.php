<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\OrderPaymentStatus;
use App\Enums\UserStatus;
use App\Models\NotificationPreference;
use App\Models\PushCampaign;
use App\Models\PushDevice;
use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Resolves who a push campaign reaches (docs/renovacion/campanas-push.md
 * §Audiencia) — the single place the live estimate, the confirmation step
 * and the actual send read from, so the number the admin confirms is the
 * audience that gets the campaign.
 *
 * Audiences: all (users with push enabled for the category), users
 * (hand-picked), event (participants of an event, optionally one edition
 * / race), collection (owners of a Finisher Legacy Tag from a collection),
 * product (buyers with a paid order), role, opted_in (explicitly turned
 * the category's push ON in Ajustes › Notificaciones).
 *
 * Preferences come from the notification_preferences table with the same
 * defaults as App\Support\Notifications\NotificationPreferences (a missing
 * row = the category default): a user is reached when push is allowed and
 * they have an active device, or — when the campaign is also saved to the
 * notification center — when in-app is allowed. Only active accounts.
 */
final class CampaignAudience
{
    /** @var array<string, string> */
    public const array TYPES = [
        'all' => 'Todos con notificaciones activas',
        'users' => 'Usuarios específicos',
        'event' => 'Participantes de un evento',
        'collection' => 'Dueños de tags de una colección',
        'product' => 'Compradores de un producto',
        'role' => 'Usuarios por rol',
        'opted_in' => 'Suscritos a novedades y promociones',
    ];

    public function __construct(private readonly NotificationPreferences $preferences) {}

    /**
     * Every user the campaign reaches (push and/or notification center).
     *
     * @param  array<string, mixed>  $params
     * @return Builder<User>
     */
    public function query(string $type, array $params, string $category, bool $saveToInbox): Builder
    {
        $cat = NotificationCategory::from($category);
        $query = $this->base($type, $params, $cat);

        // "Todos" means users with push enabled — never the whole user table.
        if ($type === 'all' || ! $saveToInbox) {
            return $this->pushReachable($query, $cat);
        }

        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $push) => $this->pushReachable($push, $cat))
            ->orWhere(fn (Builder $inbox) => $this->allows($inbox, $cat, 'in_app')));
    }

    /** @return Builder<User> */
    public function forCampaign(PushCampaign $campaign): Builder
    {
        return $this->query($campaign->audience_type, $campaign->audience_params ?? [], $campaign->category, $campaign->save_to_inbox);
    }

    /**
     * Live reach for the composer and the confirmation step.
     *
     * @param  array<string, mixed>  $params
     * @return array{users: int, devices: int, users_with_push: int, inbox: int}
     */
    public function estimate(string $type, array $params, string $category, bool $saveToInbox): array
    {
        $cat = NotificationCategory::from($category);
        $users = $this->query($type, $params, $category, $saveToInbox);

        $pushUsers = $this->pushReachable(clone $users, $cat)->select('users.id');

        $devices = PushDevice::query()->where('active', true)->whereIn('user_id', $pushUsers);

        return [
            'users' => (clone $users)->count(),
            'devices' => (clone $devices)->count(),
            'users_with_push' => (clone $devices)->distinct()->count('user_id'),
            'inbox' => $saveToInbox ? $this->allows(clone $users, $cat, 'in_app')->count() : 0,
        ];
    }

    /**
     * The audience definition plus account status, before any preference.
     *
     * @param  array<string, mixed>  $params
     * @return Builder<User>
     */
    private function base(string $type, array $params, NotificationCategory $category): Builder
    {
        $query = User::query()->whereIn('users.status', [UserStatus::Active->value, UserStatus::Pending->value]);

        match ($type) {
            'all' => null,
            'users' => $query->whereIn('users.id', self::intList($params['user_ids'] ?? [])),
            'event' => $query->whereIn('users.id', $this->eventUserIds($params)),
            'collection' => $query->whereIn('users.id', $this->collectionUserIds((int) ($params['collection_id'] ?? 0))),
            'product' => $query->whereIn('users.id', $this->productUserIds((int) ($params['product_id'] ?? 0))),
            'role' => $query->whereHas('roles', fn (Builder $q) => $q->where('name', (string) ($params['role'] ?? ''))),
            'opted_in' => $query->whereExists($this->preferenceRow($category, 'push', true)),
            default => $query->whereRaw('1 = 0'),
        };

        return $query;
    }

    /**
     * Push allowed for the category and at least one active device.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function pushReachable(Builder $query, NotificationCategory $category): Builder
    {
        return $this->allows($query, $category, 'push')
            ->whereHas('pushDevices', fn (Builder $q) => $q->where('active', true));
    }

    /**
     * SQL twin of NotificationPreferences::filterUsers(): an explicit row
     * wins, otherwise the category default.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function allows(Builder $query, NotificationCategory $category, string $channel): Builder
    {
        return $this->preferences->default($category, $channel)
            ? $query->whereNotExists($this->preferenceRow($category, $channel, false))
            : $query->whereExists($this->preferenceRow($category, $channel, true));
    }

    private function preferenceRow(NotificationCategory $category, string $channel, bool $value): QueryBuilder
    {
        return DB::table('notification_preferences')
            ->whereColumn('notification_preferences.user_id', 'users.id')
            ->where('notification_preferences.scope', NotificationPreference::SCOPE_CUSTOMER)
            ->where('notification_preferences.category', $category->value)
            ->where("notification_preferences.{$channel}", $value);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function eventUserIds(array $params): QueryBuilder
    {
        $participants = DB::table('event_participants')
            ->join('event_editions', 'event_editions.id', '=', 'event_participants.event_edition_id')
            ->where('event_editions.event_id', (int) ($params['event_id'] ?? 0))
            ->when(! empty($params['edition_id']), fn (QueryBuilder $q) => $q->where('event_participants.event_edition_id', (int) $params['edition_id']))
            ->when(! empty($params['race_id']), fn (QueryBuilder $q) => $q->where('event_participants.event_race_id', (int) $params['race_id']));

        // A participation is linked to an account directly (user_id) or
        // through the canonical athlete identity.
        $direct = (clone $participants)->whereNotNull('event_participants.user_id')->select('event_participants.user_id');

        $viaAthlete = (clone $participants)
            ->join('athletes', 'athletes.id', '=', 'event_participants.athlete_id')
            ->whereNotNull('athletes.user_id')
            ->select('athletes.user_id');

        return $direct->union($viaAthlete);
    }

    private function collectionUserIds(int $collectionId): QueryBuilder
    {
        return DB::table('legacy_tags')
            ->join('product_variants', 'product_variants.id', '=', 'legacy_tags.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('athletes', 'athletes.id', '=', 'legacy_tags.owner_athlete_id')
            ->where('products.collection_id', $collectionId)
            ->whereNotNull('athletes.user_id')
            ->select('athletes.user_id');
    }

    private function productUserIds(int $productId): QueryBuilder
    {
        return DB::table('orders')
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.product_id', $productId)
            ->whereIn('orders.payment_status', [OrderPaymentStatus::Paid->value, OrderPaymentStatus::PartiallyRefunded->value])
            ->whereNotNull('orders.user_id')
            ->select('orders.user_id');
    }

    /**
     * @return list<int>
     */
    public static function intList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', array_filter($value, fn ($id) => is_numeric($id) && (int) $id > 0))));
    }
}
