<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\LegacyPlateEntitlement;
use App\Queries\Athletes\GetAthleteHistory;
use App\Queries\Commerce\GetAthleteOwnedProducts;
use App\Queries\Operations\GetEventParticipantMetrics;
use App\Queries\Operations\GetEventParticipantsList;
use App\Support\Notifications\NotificationTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Participants V2 (product UX consolidation brief §19-§31): an operative,
 * event-scoped module — not a bare table of every participant everywhere.
 * EVENTO is required context; filters run in SQL before paginate() (see
 * App\Queries\Operations\GetEventParticipantsList's docblock re: brief
 * §67-§68).
 */
class ParticipantController extends Controller
{
    public function index(Request $request, GetEventParticipantsList $list, GetEventParticipantMetrics $metricsQuery): Response
    {
        $eventEditionId = $request->integer('event_edition_id') ?: null;
        $edition = $eventEditionId ? EventEdition::with(['event', 'races'])->find($eventEditionId) : null;

        $filters = [
            'q' => $request->string('q')->toString() ?: null,
            'event_race_id' => $request->integer('event_race_id') ?: null,
            'result' => $request->string('result')->toString() ?: null,
            'legacy_plate' => $request->string('legacy_plate')->toString() ?: null,
            'product' => $request->string('product')->toString() ?: null,
        ];

        $participants = null;
        $metrics = null;

        if ($edition !== null) {
            $participants = $list->handle($edition, $filters);
            $participants->through(fn (EventParticipant $participant) => $this->rowShape($participant));
            $metrics = $metricsQuery->handle($edition);
        }

        return Inertia::render('admin/participants/Index', [
            'events' => EventEdition::with('event')->orderByDesc('event_date')->limit(100)->get()
                ->map(fn (EventEdition $e) => ['id' => $e->id, 'name' => $e->event->name.' — '.$e->name]),
            'selectedEventEditionId' => $edition?->id,
            'races' => $edition?->races->map(fn ($race) => ['id' => $race->id, 'name' => $race->name]) ?? [],
            'participants' => $participants,
            'metrics' => $metrics,
            'filters' => array_merge(['event_edition_id' => $edition?->id], $filters),
        ]);
    }

    public function show(EventParticipant $eventParticipant): Response
    {
        // The route segment is {eventParticipant} (matching the operator
        // routes' convention) — implicit route-model binding matches by
        // parameter *name*, not type, so this can't be $participant or
        // Laravel silently falls back to an empty, unsaved model instead
        // of the bound one (no error — it's a valid EventParticipant, just
        // the wrong one).
        $participant = $eventParticipant;

        $participant->loadMissing([
            'eventEdition.event', 'eventRace', 'result', 'athlete.user',
            'legacyPlateEntitlements.legacyPlateModel', 'legacyPlateEntitlements.plate',
            'plates', 'media',
        ]);

        $athlete = $participant->athlete;
        $entitlement = $participant->legacyPlateEntitlements->first();
        $plate = $participant->plates->first();

        return Inertia::render('admin/participants/Show', [
            'participant' => [
                'id' => $participant->id,
                'full_name' => $participant->full_name,
                'email' => $participant->email,
                'bib_number' => $participant->bib_number,
                'event' => $participant->eventEdition?->event?->name,
                'edition' => $participant->eventEdition?->name,
                'edition_id' => $participant->event_edition_id,
                'race' => $participant->eventRace?->name,
                'registration_status' => $participant->registration_status->value,
                'athlete_id' => $athlete?->id,
                'athlete_uuid' => $athlete?->uuid,
            ],
            'resumen' => [
                'result' => $participant->result ? [
                    'status' => $participant->result->status->value,
                    'official_time' => $participant->result->official_time,
                    'pace' => $participant->result->pace,
                    'overall_position' => $participant->result->overall_position,
                ] : null,
                'legacy_plate' => $entitlement === null ? null : [
                    'status' => $entitlement->status->value,
                    'model' => $entitlement->legacyPlateModel?->name,
                    'paid_at' => $entitlement->paid_at?->toDateTimeString(),
                ],
                'plate' => $plate === null ? null : [
                    'status' => $plate->status->value,
                    'serial_number' => $plate->serial_number,
                ],
                'media_count' => $participant->media->count(),
            ],
            'historial' => $athlete === null ? [] : app(GetAthleteHistory::class)->handle($athlete)['participations']
                ->map(fn (EventParticipant $p) => [
                    'id' => $p->id,
                    'is_current' => $p->id === $participant->id,
                    'event' => $p->eventEdition?->event?->name,
                    'edition' => $p->eventEdition?->name,
                    'race' => $p->eventRace?->name,
                    'bib_number' => $p->bib_number,
                    'event_date' => $p->eventEdition?->event_date?->toDateString(),
                ])->values(),
            'compras' => $athlete === null ? [] : app(GetAthleteOwnedProducts::class)->handle($athlete)
                ->map(fn ($owned) => [
                    'uuid' => $owned->uuid,
                    'product' => $owned->product->name,
                    'variant' => $owned->productVariant?->name,
                    'status' => $owned->status->value,
                    'acquired_at' => $owned->acquired_at->toDateString(),
                    'order_uuid' => $owned->orderItem?->order?->uuid,
                ])->values(),
            'comunicacion' => $athlete?->user === null ? [] : $athlete->user->notifications()
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (DatabaseNotification $n) => [
                    'id' => $n->id,
                    'title' => $n->data['title'] ?? null,
                    'message' => $n->data['message'] ?? null,
                    'type' => $n->data['type'] ?? null,
                    'sent_by_name' => $n->data['sent_by_name'] ?? null,
                    'read_at' => $n->read_at?->diffForHumans(),
                    'created_at' => $n->created_at->diffForHumans(),
                ])
                ->values(),
            'canNotify' => $athlete?->user !== null,
            'notificationTemplates' => NotificationTemplates::all(),
        ]);
    }

    /**
     * "ENVIAR NOTIFICACIÓN" from the Comunicación tab (product UX
     * consolidation brief §28, §38, §53) — the same App\Actions\
     * Notifications\SendAthleteNotification a future bulk-reminder flow
     * from Preregistros would call too, never a second send path.
     */
    public function notify(Request $request, EventParticipant $eventParticipant, SendAthleteNotification $send): RedirectResponse
    {
        $eventParticipant->loadMissing('athlete.user');
        $user = $eventParticipant->athlete?->user;

        if ($user === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Este participante no tiene una cuenta para notificar.']);

            return back();
        }

        $data = $request->validate([
            'type' => ['required', Rule::enum(NotificationType::class)],
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:1000'],
        ]);

        $send->handle(
            recipient: $user,
            title: $data['title'],
            message: $data['message'],
            type: NotificationType::from($data['type']),
            actionUrl: '/dashboard/legado/'.$eventParticipant->id,
            sentBy: $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Notificación enviada.']);

        return back();
    }

    public function export(Request $request, GetEventParticipantsList $list): StreamedResponse
    {
        $edition = EventEdition::with('event')->findOrFail($request->integer('event_edition_id'));

        $filters = [
            'q' => $request->string('q')->toString() ?: null,
            'event_race_id' => $request->integer('event_race_id') ?: null,
            'result' => $request->string('result')->toString() ?: null,
            'legacy_plate' => $request->string('legacy_plate')->toString() ?: null,
            'product' => $request->string('product')->toString() ?: null,
        ];

        // The export respects the exact same filters as the screen — built
        // from the same Query, not a second hand-rolled one that could
        // silently drift out of sync with what the operator is looking at.
        $rows = $list->handle($edition, $filters, perPage: 5000);

        $filename = 'participantes-'.$edition->id.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['Bib', 'Atleta', 'Carrera', 'Resultado', 'Tiempo', 'Ritmo', 'Legacy Plate', 'Pago', 'Productos', 'Pedido']);

            foreach ($rows as $participant) {
                $shape = $this->rowShape($participant);
                fputcsv($out, [
                    $shape['bib_number'],
                    $shape['name'],
                    $shape['race'],
                    $shape['result_status'],
                    $shape['official_time'],
                    $shape['pace'],
                    $shape['legacy_plate_status'],
                    $shape['payment_status'],
                    implode('; ', $shape['products']),
                    $shape['order_uuid'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array<string, mixed>
     */
    private function rowShape(EventParticipant $participant): array
    {
        $entitlement = $participant->legacyPlateEntitlements->first();
        $athlete = $participant->athlete;

        // Reads GetEventParticipantsList's eager-loaded, event-scoped
        // athlete.orders.items.product — never a fresh per-row query.
        $order = $athlete instanceof Athlete ? $athlete->orders->first() : null;
        $products = $order === null ? [] : $order->items
            ->pluck('product.name')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $participant->id,
            'name' => $participant->full_name,
            'bib_number' => $participant->bib_number,
            'race' => $participant->eventRace?->name,
            'result_status' => $participant->result?->status->value,
            'official_time' => $participant->result?->official_time,
            'pace' => $participant->result?->pace,
            'legacy_plate_status' => $entitlement instanceof LegacyPlateEntitlement ? $entitlement->status->value : null,
            'payment_status' => $order?->payment_status->value,
            'order_uuid' => $order?->uuid,
            'products' => $products,
            'media_count' => $participant->media_count ?? 0,
        ];
    }
}
