<?php

namespace App\Queries\Admin;

use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Admin ⌘K search (spec §111): exactly five record types — event, athlete,
 * participant, order, product — never a universal SQL search. Each type is
 * only queried when the viewer holds the permission that opens its detail
 * page, returns at most PER_TYPE rows, and selects only display columns.
 */
class SearchAdminRecords
{
    public const int PER_TYPE = 5;

    public const int MIN_LENGTH = 2;

    public const int MAX_LENGTH = 60;

    /**
     * @return list<array{type: string, label: string, items: list<array{id: int|string, title: string, subtitle: string|null, url: string}>}>
     */
    public function handle(User $viewer, string $term): array
    {
        $term = trim(mb_substr($term, 0, self::MAX_LENGTH));

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';

        $groups = [];

        if ($viewer->can('events.view')) {
            $groups[] = ['type' => 'event', 'label' => 'Eventos', 'items' => array_values(EventEdition::query()
                ->with('event:id,name')
                ->whereHas('event', fn ($q) => $q->where('name', 'like', $like))
                ->orderByDesc('event_date')
                ->limit(self::PER_TYPE)
                ->get(['id', 'event_id', 'name', 'event_date', 'city'])
                ->map(fn (EventEdition $edition) => [
                    'id' => $edition->id,
                    'title' => $edition->event->name,
                    'subtitle' => trim($edition->name.' · '.$edition->event_date->format('d/m/Y').' · '.$edition->city, ' ·'),
                    'url' => "/admin/events/{$edition->id}",
                ])->all())];
        }

        if ($viewer->can('athletes.view')) {
            $groups[] = ['type' => 'athlete', 'label' => 'Atletas', 'items' => array_values(Athlete::query()
                ->whereNull('merged_into_athlete_id')
                ->where(fn ($q) => $q->where('full_name', 'like', $like)->orWhere('email', 'like', $prefix))
                ->orderBy('full_name')
                ->limit(self::PER_TYPE)
                ->get(['id', 'full_name', 'email', 'country'])
                ->map(fn (Athlete $athlete) => [
                    'id' => $athlete->id,
                    'title' => (string) $athlete->full_name,
                    'subtitle' => $athlete->email ? Str::mask($athlete->email, '*', 3, max(0, strpos($athlete->email, '@') - 3)) : $athlete->country,
                    'url' => "/admin/athletes/{$athlete->id}",
                ])->all())];
        }

        if ($viewer->can('participants.view')) {
            $groups[] = ['type' => 'participant', 'label' => 'Participantes', 'items' => array_values(EventParticipant::query()
                ->with('eventEdition:id,event_id,name', 'eventEdition.event:id,name')
                ->where(fn ($q) => $q->where('full_name', 'like', $like)->orWhere('bib_number', $term))
                ->latest('id')
                ->limit(self::PER_TYPE)
                ->get(['id', 'event_edition_id', 'full_name', 'bib_number'])
                ->map(fn (EventParticipant $participant) => [
                    'id' => $participant->id,
                    'title' => (string) $participant->full_name,
                    'subtitle' => trim(($participant->bib_number ? "#{$participant->bib_number} · " : '').($participant->eventEdition?->event->name ?? '')),
                    'url' => "/admin/participants/{$participant->id}",
                ])->all())];
        }

        if ($viewer->can('orders.view')) {
            $groups[] = ['type' => 'order', 'label' => 'Pedidos', 'items' => array_values(Order::query()
                ->where(fn ($q) => $q->where('order_number', 'like', $prefix)->orWhere('uuid', $term))
                ->latest('id')
                ->limit(self::PER_TYPE)
                ->get(['id', 'uuid', 'order_number', 'customer_snapshot', 'total_minor', 'currency', 'status'])
                ->map(fn (Order $order) => [
                    'id' => $order->uuid,
                    'title' => (string) $order->order_number,
                    'subtitle' => $order->customer_snapshot['name'] ?? null,
                    'url' => "/admin/orders/{$order->uuid}",
                ])->all())];
        }

        if ($viewer->can('products.manage')) {
            $groups[] = ['type' => 'product', 'label' => 'Productos', 'items' => array_values(Product::query()
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('slug', 'like', $prefix))
                ->orderBy('name')
                ->limit(self::PER_TYPE)
                ->get(['id', 'name', 'type'])
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'title' => $product->name,
                    'subtitle' => null,
                    'url' => "/admin/products/{$product->id}",
                ])->all())];
        }

        return array_values(array_filter($groups, fn (array $group) => $group['items'] !== []));
    }
}
