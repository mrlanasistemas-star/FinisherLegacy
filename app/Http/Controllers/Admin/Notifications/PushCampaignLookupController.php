<?php

namespace App\Http\Controllers\Admin\Notifications;

use App\Actions\Notifications\CampaignAudience;
use App\Actions\Notifications\CampaignManager;
use App\Actions\Notifications\CampaignRateGuard;
use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Event;
use App\Models\EventEdition;
use App\Models\EventRace;
use App\Models\Product;
use App\Models\PushCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * JSON helpers of the push-campaign composer (docs/renovacion/campanas-push.md):
 * the live estimated reach (users / devices / inbox copies, plus whether
 * the "Todos" rate guard would block sending now) and the pickers' search
 * (users by name or email, events with editions and races, products,
 * collections). Same `notifications.broadcast` gate as the panel.
 */
class PushCampaignLookupController extends Controller
{
    public function estimate(Request $request, CampaignAudience $audience, CampaignRateGuard $guard): JsonResponse
    {
        $data = $request->validate([
            'audience_type' => ['required', Rule::in(array_keys(CampaignAudience::TYPES))],
            'audience_params' => ['nullable', 'array'],
            'category' => ['required', Rule::in(PushCampaign::CATEGORIES)],
            'save_to_inbox' => ['nullable', 'boolean'],
            'campaign' => ['nullable', 'uuid'],
        ]);

        $type = (string) $data['audience_type'];
        $params = CampaignManager::audienceParams($type, (array) ($data['audience_params'] ?? []));

        $probe = PushCampaign::query()->where('uuid', $data['campaign'] ?? '')->first() ?? new PushCampaign;
        $probe->audience_type = $type;

        return response()->json([
            ...$audience->estimate($type, $params, (string) $data['category'], $request->boolean('save_to_inbox', true)),
            'rate_limited_now' => ! $guard->allows($probe, now()),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['users', 'events', 'products', 'collections'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $term = trim((string) ($data['q'] ?? ''));
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        $results = match ($data['kind']) {
            'users' => $term === '' ? [] : User::query()
                ->where(fn (Builder $q) => $q->where('email', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like))
                ->orderBy('first_name')
                ->limit(15)
                ->get(['id', 'first_name', 'last_name', 'email'])
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])
                ->all(),
            'events' => Event::query()
                ->when($term !== '', fn (Builder $q) => $q->where('name', 'like', $like))
                ->with(['editions' => fn ($q) => $q->orderByDesc('year')->with('races:id,event_edition_id,name')])
                ->orderBy('name')
                ->limit(15)
                ->get()
                ->map(fn (Event $e) => self::eventPayload($e))
                ->all(),
            'products' => Product::query()
                ->when($term !== '', fn (Builder $q) => $q->where('name', 'like', $like))
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name', 'slug'])
                ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug])
                ->all(),
            default => Collection::query()
                ->when($term !== '', fn (Builder $q) => $q->where('name', 'like', $like))
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name', 'slug'])
                ->map(fn (Collection $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug])
                ->all(),
        };

        return response()->json(['data' => $results]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function eventPayload(Event $event): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'slug' => $event->slug,
            'editions' => $event->editions->map(fn (EventEdition $edition) => [
                'id' => $edition->id,
                'name' => $edition->name ?: (string) $edition->year,
                'races' => $edition->races->map(fn (EventRace $race) => ['id' => $race->id, 'name' => $race->name])->values()->all(),
            ])->values()->all(),
        ];
    }
}
