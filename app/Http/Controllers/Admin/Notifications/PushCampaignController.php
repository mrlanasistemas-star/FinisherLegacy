<?php

namespace App\Http\Controllers\Admin\Notifications;

use App\Actions\Notifications\CampaignAudience;
use App\Actions\Notifications\CampaignDestination;
use App\Actions\Notifications\CampaignManager;
use App\Actions\Notifications\CampaignRateGuard;
use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PushCampaignRequest;
use App\Models\Collection;
use App\Models\Event;
use App\Models\EventEdition;
use App\Models\Product;
use App\Models\PushCampaign;
use App\Models\PushCampaignRecipient;
use App\Models\Role;
use App\Models\User;
use App\Support\Notifications\PushSender;
use App\Support\SystemRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Administración → Notificaciones → Campañas push
 * (docs/renovacion/campanas-push.md): history with status and counters,
 * the composer (create / edit while draft or scheduled), the detail page
 * with delivery stats and the campaign's audit trail, plus cancel,
 * unschedule and duplicate. Gated by `notifications.broadcast`.
 *
 * Thin: lifecycle in App\Actions\Notifications\CampaignManager, sending in
 * CampaignDispatcher, audience in CampaignAudience.
 */
class PushCampaignController extends Controller
{
    private const array AUDIT_LABELS = [
        'push_campaign.created' => 'Creó la campaña',
        'push_campaign.updated' => 'Editó la campaña',
        'push_campaign.scheduled' => 'Programó el envío',
        'push_campaign.rescheduled' => 'Reprogramó el envío',
        'push_campaign.unscheduled' => 'Regresó a borrador',
        'push_campaign.send_now' => 'Pidió enviar ahora',
        'push_campaign.started' => 'Empezó el envío',
        'push_campaign.completed' => 'Terminó el envío',
        'push_campaign.cancelled' => 'Canceló la campaña',
        'push_campaign.duplicated' => 'Creó esta campaña como copia',
        'push_campaign.test_sent' => 'Se envió una prueba',
        'push_campaign.blocked_by_rate_guard' => 'Bloqueada por el límite de envíos a todos',
    ];

    public function index(Request $request, CampaignRateGuard $guard, PushSender $sender): Response
    {
        $status = $request->string('status')->toString();

        $campaigns = PushCampaign::query()
            ->with('creator:id,first_name,last_name')
            ->withCount(['recipients as opened_count' => fn ($q) => $q->whereNotNull('opened_at')])
            ->when(array_key_exists($status, PushCampaign::STATUS_LABELS), fn ($q) => $q->where('status', $status))
            ->orderByRaw('case when status in (?, ?) then 0 else 1 end', [PushCampaign::STATUS_SENDING, PushCampaign::STATUS_SCHEDULED])
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (PushCampaign $c) => $this->row($c));

        $since = now()->subDays(30);

        return Inertia::render('admin/notifications/campaigns/Index', [
            'campaigns' => $campaigns,
            'filters' => ['status' => $status !== '' ? $status : null],
            'statuses' => PushCampaign::STATUS_LABELS,
            'stats' => [
                'sent_30d' => PushCampaign::query()->whereIn('status', [PushCampaign::STATUS_SENT, PushCampaign::STATUS_FAILED])->where('started_at', '>=', $since)->count(),
                'reached_30d' => (int) PushCampaign::query()->where('started_at', '>=', $since)->sum('audience_users_count'),
                'opened_30d' => PushCampaignRecipient::query()->whereNotNull('opened_at')->where('opened_at', '>=', $since)->count(),
                'scheduled' => PushCampaign::query()->where('status', PushCampaign::STATUS_SCHEDULED)->count(),
            ],
            'pushConfigured' => $sender->isConfigured(),
            'allLimitPerHour' => $guard->limit(),
        ]);
    }

    public function create(Request $request, PushSender $sender, CampaignRateGuard $guard): Response
    {
        return $this->composer(null, $sender, $guard);
    }

    public function edit(PushCampaign $campaign, PushSender $sender, CampaignRateGuard $guard): Response|RedirectResponse
    {
        if (! $campaign->isEditable()) {
            return redirect()->route('admin.push-campaigns.show', $campaign);
        }

        return $this->composer($campaign, $sender, $guard);
    }

    public function store(PushCampaignRequest $request, CampaignManager $manager): RedirectResponse
    {
        $campaign = $manager->save(null, $request->validated(), $request->file('image'), false, $request->user());

        return $this->afterSave($request, $campaign, $manager);
    }

    public function update(PushCampaignRequest $request, PushCampaign $campaign, CampaignManager $manager): RedirectResponse
    {
        $campaign = $manager->save($campaign, $request->validated(), $request->file('image'), $request->boolean('remove_image'), $request->user());

        return $this->afterSave($request, $campaign, $manager);
    }

    public function show(PushCampaign $campaign): Response
    {
        $campaign->loadMissing(['creator:id,first_name,last_name', 'sender:id,first_name,last_name', 'duplicatedFrom:id,uuid,name,title']);

        $breakdown = PushCampaignRecipient::query()
            ->where('push_campaign_id', $campaign->id)
            ->groupBy('status')
            ->select('status', DB::raw('count(*) as total'))
            ->pluck('total', 'status');

        $audit = Activity::query()
            ->where('subject_type', $campaign->getMorphClass())
            ->where('subject_id', $campaign->id)
            ->where('description', 'like', 'push_campaign.%')
            ->with('causer')
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'label' => self::AUDIT_LABELS[$a->description] ?? $a->description,
                'by' => $a->causer instanceof User ? $a->causer->name : null,
                'at' => $a->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/notifications/campaigns/Show', [
            'campaign' => [
                ...$this->row($campaign),
                ...$this->content($campaign),
                'opened_count' => PushCampaignRecipient::query()->where('push_campaign_id', $campaign->id)->whereNotNull('opened_at')->count(),
                'sender' => $campaign->sender?->name,
                'duplicated_from' => $campaign->duplicatedFrom ? ['uuid' => $campaign->duplicatedFrom->uuid, 'title' => $campaign->duplicatedFrom->name ?? $campaign->duplicatedFrom->title] : null,
                'audience_summary' => $this->audienceSummary($campaign),
            ],
            'breakdown' => [
                'pending' => (int) ($breakdown[PushCampaignRecipient::STATUS_PENDING] ?? 0),
                'sent' => (int) ($breakdown[PushCampaignRecipient::STATUS_SENT] ?? 0),
                'skipped' => (int) ($breakdown[PushCampaignRecipient::STATUS_SKIPPED] ?? 0),
                'failed' => (int) ($breakdown[PushCampaignRecipient::STATUS_FAILED] ?? 0),
            ],
            'audit' => $audit,
        ]);
    }

    public function cancel(Request $request, PushCampaign $campaign, CampaignManager $manager): RedirectResponse
    {
        $manager->cancel($campaign, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Campaña cancelada. No se enviará.']);

        return redirect()->route('admin.push-campaigns.show', $campaign);
    }

    public function unschedule(Request $request, PushCampaign $campaign, CampaignManager $manager): RedirectResponse
    {
        $manager->unschedule($campaign, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'La campaña volvió a borrador.']);

        return redirect()->route('admin.push-campaigns.edit', $campaign);
    }

    public function duplicate(Request $request, PushCampaign $campaign, CampaignManager $manager): RedirectResponse
    {
        $copy = $manager->duplicate($campaign, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Copia creada como borrador. Revisa la audiencia antes de enviarla.']);

        return redirect()->route('admin.push-campaigns.edit', $copy);
    }

    private function afterSave(PushCampaignRequest $request, PushCampaign $campaign, CampaignManager $manager): RedirectResponse
    {
        $user = $request->user();

        switch ($request->string('intent')->toString()) {
            case 'test':
                $summary = $manager->testSend($campaign, $user);

                Inertia::flash('toast', ! $summary->configured
                    ? ['type' => 'warning', 'message' => 'Guardada. No hay un proveedor de push configurado: la prueba solo llegó a tu centro de notificaciones.']
                    : ($summary->devices === 0
                        ? ['type' => 'warning', 'message' => 'Guardada. No tienes la app con notificaciones activas en ningún dispositivo.']
                        : ['type' => 'success', 'message' => "Prueba enviada a {$summary->devices} ".($summary->devices === 1 ? 'dispositivo tuyo.' : 'dispositivos tuyos.')]));

                return redirect()->route('admin.push-campaigns.edit', $campaign);

            case 'schedule':
                $at = $request->scheduledAt();

                if ($at === null) {
                    return back()->withErrors(['scheduled_at' => 'Elige la fecha y hora de envío.']);
                }

                $manager->schedule($campaign, $at, $user);

                Inertia::flash('toast', ['type' => 'success', 'message' => 'Campaña programada para el '.$at->setTimezone(PushCampaign::TIMEZONE)->translatedFormat('j \d\e F, H:i').' (hora del centro de México).']);

                return redirect()->route('admin.push-campaigns.show', $campaign);

            case 'send':
                $manager->sendNow($campaign, $user);

                Inertia::flash('toast', ['type' => 'success', 'message' => 'Enviando la campaña. Los números se actualizan en unos segundos.']);

                return redirect()->route('admin.push-campaigns.show', $campaign);

            default:
                if ($campaign->status === PushCampaign::STATUS_SCHEDULED) {
                    $manager->unschedule($campaign, $user);
                }

                Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador guardado.']);

                return redirect()->route('admin.push-campaigns.edit', $campaign);
        }
    }

    private function composer(?PushCampaign $campaign, PushSender $sender, CampaignRateGuard $guard): Response
    {
        return Inertia::render('admin/notifications/campaigns/Compose', [
            'campaign' => $campaign !== null ? [
                ...$this->row($campaign),
                ...$this->content($campaign),
                'scheduled_local' => $campaign->scheduled_at?->setTimezone(PushCampaign::TIMEZONE)->format('Y-m-d\TH:i'),
                'audience_selection' => $this->audienceSelection($campaign),
            ] : null,
            'options' => [
                'destinations' => collect(CampaignDestination::TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label, 'needs_value' => in_array($value, CampaignDestination::WITH_VALUE, true)])->values(),
                'audiences' => collect(CampaignAudience::TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                'categories' => collect(PushCampaign::CATEGORIES)->map(fn ($value) => [
                    'value' => $value,
                    'label' => NotificationCategory::from($value)->label(),
                    'description' => NotificationCategory::from($value)->description(),
                ])->values(),
                'promoOnlyAudiences' => PushCampaign::PROMO_ONLY_AUDIENCES,
                'channels' => [
                    ['value' => 'avisos', 'label' => 'Avisos', 'description' => 'Canal de avisos y novedades de la app (recomendado).'],
                    ['value' => 'default', 'label' => 'General', 'description' => 'Canal general de la app.'],
                ],
                'collections' => Collection::query()->orderBy('name')->get(['id', 'name', 'slug'])->map(fn (Collection $c) => ['id' => $c->id, 'name' => $c->name, 'slug' => $c->slug]),
                'roles' => Role::query()->orderBy('name')->get(['name', 'label'])->map(fn (Role $r) => ['value' => $r->name, 'label' => $r->label ?: (SystemRoles::LABELS[$r->name] ?? $r->name)]),
                'imageMaxKb' => (int) config('push_campaigns.image_max_kb', 1024),
                'allowedHost' => CampaignDestination::ALLOWED_HOST,
            ],
            'pushConfigured' => $sender->isConfigured(),
            'allLimitPerHour' => $guard->limit(),
            'logoUrl' => asset('images/brand/logo/fl-monograma.png'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(PushCampaign $c): array
    {
        return [
            'id' => $c->id,
            'uuid' => $c->uuid,
            'name' => $c->name,
            'title' => $c->title,
            'status' => $c->status,
            'status_label' => $c->statusLabel(),
            'editable' => $c->isEditable(),
            'audience_type' => $c->audience_type,
            'audience_label' => CampaignAudience::TYPES[$c->audience_type] ?? $c->audience_type,
            'audience_users_count' => $c->audience_users_count,
            'audience_devices_count' => $c->audience_devices_count,
            'sent_count' => $c->sent_count,
            'inbox_count' => $c->inbox_count,
            'skipped_count' => $c->skipped_count,
            'failed_count' => $c->failed_count,
            // From the push receipts (push:check-receipts): what really reached
            // the phones vs. what the provider could not deliver.
            'delivered_count' => $c->delivered_count,
            'delivery_failed_count' => $c->delivery_failed_count,
            'awaiting_receipt_count' => max(0, $c->sent_count - $c->delivered_count - $c->delivery_failed_count),
            'opened_count' => (int) ($c->getAttribute('opened_count') ?? 0),
            'batches_total' => $c->batches_total,
            'batches_done' => $c->batches_done,
            'last_error' => $c->last_error,
            'scheduled_at' => $c->scheduled_at?->toIso8601String(),
            'started_at' => $c->started_at?->toIso8601String(),
            'completed_at' => $c->completed_at?->toIso8601String(),
            'cancelled_at' => $c->cancelled_at?->toIso8601String(),
            'created_at' => $c->created_at?->toIso8601String(),
            'creator' => $c->creator?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function content(PushCampaign $c): array
    {
        return [
            'body' => $c->body,
            'image_url' => $c->imageUrl(),
            'include_logo' => $c->include_logo,
            'color' => $c->color,
            'channel' => $c->channel,
            'category' => $c->category,
            'category_label' => NotificationCategory::tryFrom($c->category)?->label() ?? $c->category,
            'save_to_inbox' => $c->save_to_inbox,
            'link_type' => $c->link_type,
            'link_value' => $c->link_value,
            'link_label' => $c->link_label ?? (CampaignDestination::TYPES[$c->link_type] ?? null),
            'audience_params' => $c->audience_params ?? [],
        ];
    }

    /**
     * Display labels for the pickers of an existing campaign.
     *
     * @return array<string, mixed>
     */
    private function audienceSelection(PushCampaign $c): array
    {
        $params = $c->audience_params ?? [];

        return match ($c->audience_type) {
            'users' => ['users' => User::query()->whereIn('id', CampaignAudience::intList($params['user_ids'] ?? []))->get(['id', 'first_name', 'last_name', 'email'])->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values()],
            'event' => ['event' => $this->eventOption((int) ($params['event_id'] ?? 0))],
            'product' => ['product' => Product::query()->whereKey((int) ($params['product_id'] ?? 0))->first(['id', 'name', 'slug'])?->only(['id', 'name', 'slug'])],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function eventOption(int $eventId): ?array
    {
        $event = Event::query()->with(['editions' => fn ($q) => $q->orderByDesc('year')->with('races:id,event_edition_id,name')])->find($eventId);

        return $event === null ? null : PushCampaignLookupController::eventPayload($event);
    }

    private function audienceSummary(PushCampaign $c): string
    {
        $params = $c->audience_params ?? [];
        $label = CampaignAudience::TYPES[$c->audience_type] ?? $c->audience_type;

        $detail = match ($c->audience_type) {
            'users' => count($params['user_ids'] ?? []).' seleccionados',
            'event' => trim((Event::query()->whereKey((int) ($params['event_id'] ?? 0))->value('name') ?? 'Evento eliminado').' '.(isset($params['edition_id']) ? (string) EventEdition::query()->whereKey((int) $params['edition_id'])->value('name') : '')),
            'collection' => Collection::query()->whereKey((int) ($params['collection_id'] ?? 0))->value('name') ?? 'Colección eliminada',
            'product' => Product::query()->whereKey((int) ($params['product_id'] ?? 0))->value('name') ?? 'Producto eliminado',
            'role' => SystemRoles::LABELS[$params['role'] ?? ''] ?? (string) ($params['role'] ?? ''),
            default => null,
        };

        return $detail !== null && $detail !== '' ? "{$label}: {$detail}" : $label;
    }
}
