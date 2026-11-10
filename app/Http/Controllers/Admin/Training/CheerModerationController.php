<?php

namespace App\Http\Controllers\Admin\Training;

use App\Actions\Training\ModerateCheers;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\CheerBlock;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\CheerMessageReport;
use App\Models\CheerRateLimitHit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Contenido → Entrenamientos (docs/renovacion/
 * entrenamientos.md §Moderación). Cheers are private between family and
 * athlete, so this page shows only aggregates (per link, per athlete,
 * blocked senders as a short hash reference, rate-limit hits) plus the
 * messages an athlete explicitly REPORTED. Audio of a reported message is
 * reachable only through ModerateCheers::reportedAudioUrl (short signed
 * URL, audit-logged). Actions: disable/re-enable a link, resolve a report,
 * purge the reported sender.
 */
class CheerModerationController extends Controller
{
    /** Abuse-signal thresholds for a link (any one flags it). */
    private const int SIGNAL_BLOCKS = 3;

    private const int SIGNAL_RATE_HITS_7D = 10;

    private const int SIGNAL_MESSAGES_24H = 50;

    public function index(): Response
    {
        $dayAgo = now()->subDay();
        $weekAgo = now()->subDays(7);

        return Inertia::render('admin/training/Index', [
            'stats' => [
                'links' => CheerLink::query()->count(),
                'active_links' => CheerLink::query()->whereNull('revoked_at')->whereNull('moderation_disabled_at')->count(),
                'disabled_links' => CheerLink::query()->whereNotNull('moderation_disabled_at')->count(),
                'messages' => CheerMessage::query()->count(),
                'messages_24h' => CheerMessage::query()->where('created_at', '>=', $dayAgo)->count(),
                'blocks' => CheerBlock::query()->count(),
                'rate_hits_7d' => CheerRateLimitHit::query()->where('created_at', '>=', $weekAgo)->count(),
                'open_reports' => CheerMessageReport::query()->whereIn('status', [ReportStatus::Open, ReportStatus::Reviewing])->count(),
            ],
            'reports' => $this->reports(),
            'links' => $this->links($dayAgo, $weekAgo),
            'athletes' => $this->athletes(),
            'senders' => $this->senders(),
            'thresholds' => [
                'blocks' => self::SIGNAL_BLOCKS,
                'rate_hits_7d' => self::SIGNAL_RATE_HITS_7D,
                'messages_24h' => self::SIGNAL_MESSAGES_24H,
            ],
        ]);
    }

    public function disableLink(Request $request, CheerLink $link, ModerateCheers $moderate): RedirectResponse
    {
        $moderate->disableLink($this->moderator($request), $link, $this->note($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enlace desactivado. Ya no recibe mensajes.']);

        return back();
    }

    public function enableLink(Request $request, CheerLink $link, ModerateCheers $moderate): RedirectResponse
    {
        $moderate->enableLink($this->moderator($request), $link);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enlace reactivado.']);

        return back();
    }

    public function updateReport(Request $request, CheerMessageReport $report, ModerateCheers $moderate): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ReportStatus::class)],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
            'restore_message' => ['sometimes', 'boolean'],
        ]);
        $moderator = $this->moderator($request);

        $moderate->resolve($moderator, $report, ReportStatus::from($data['status']), $data['resolution_notes'] ?? null);

        if ($request->boolean('restore_message')) {
            $moderate->restoreMessage($moderator, $report);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reporte actualizado.']);

        return back();
    }

    public function purge(Request $request, CheerMessageReport $report, ModerateCheers $moderate): RedirectResponse
    {
        $deleted = $moderate->purgeSender($this->moderator($request), $report, $this->note($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => $deleted === 1
            ? 'Se eliminó 1 mensaje y se bloqueó al remitente.'
            : "Se eliminaron {$deleted} mensajes y se bloqueó al remitente."]);

        return back();
    }

    /** Mints the short signed audio URL of a REPORTED message (audited) and hands it back as flash. */
    public function audio(Request $request, CheerMessageReport $report, ModerateCheers $moderate): RedirectResponse
    {
        $url = $moderate->reportedAudioUrl($this->moderator($request), $report);

        if ($url === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Este mensaje no tiene audio disponible.']);

            return back();
        }

        Inertia::flash('cheer_report_audio', ['report' => $report->uuid, 'url' => $url]);

        return back();
    }

    /**
     * Open reports first, then the latest reviewed ones. The message
     * content is shown only here, because the athlete reported it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function reports(): array
    {
        return CheerMessageReport::query()
            ->with(['message.audio', 'link.user', 'reporter'])
            ->orderByRaw("case when status in ('open', 'reviewing') then 0 else 1 end")
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (CheerMessageReport $report) {
                $message = $report->message;

                return [
                    'uuid' => $report->uuid,
                    'reason' => $report->reason->value,
                    'reason_label' => $report->reason->label(),
                    'details' => $report->details,
                    'status' => $report->status->value,
                    'open' => $report->isOpen(),
                    'resolution_notes' => $report->resolution_notes,
                    'reviewed_at' => $report->reviewed_at?->toIso8601String(),
                    'created_at' => $report->created_at->toIso8601String(),
                    'reporter' => $report->reporter?->name,
                    'link' => $report->link ? [
                        'uuid' => $report->link->uuid,
                        'title' => $report->link->title,
                        'disabled' => $report->link->moderation_disabled_at !== null,
                    ] : null,
                    'sender_ref' => $this->senderRef($report->sender_ip_hash),
                    'message' => $message ? [
                        'sender_name' => $message->sender_name,
                        'relationship' => $message->relationship,
                        'body' => $message->body,
                        'icon' => $message->icon->value,
                        'has_audio' => $message->audio !== null && $message->audio->isServable(),
                        'duration_seconds' => $message->duration_seconds,
                        'visible' => $message->status->value === 'visible',
                        'created_at' => $message->created_at->toIso8601String(),
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Links ordered by abuse signals (blocks, reports, throttled submits,
     * volume in the last 24 h). Counts only — never message content.
     *
     * @return array<int, array<string, mixed>>
     */
    private function links(\DateTimeInterface $dayAgo, \DateTimeInterface $weekAgo): array
    {
        return CheerLink::query()
            ->with('user:id,first_name,last_name')
            ->withCount([
                'blocks',
                'reports',
                'messages as messages_24h' => fn (Builder $q) => $q->where('created_at', '>=', $dayAgo),
                'rateLimitHits as rate_hits_7d' => fn (Builder $q) => $q->where('created_at', '>=', $weekAgo),
            ])
            ->orderByDesc('reports_count')
            ->orderByDesc('blocks_count')
            ->orderByDesc('rate_hits_7d')
            ->orderByDesc('messages_24h')
            ->orderByDesc('messages_count')
            ->limit(50)
            ->get()
            ->map(function (CheerLink $link) {
                $blocks = (int) $link->getAttribute('blocks_count');
                $reports = (int) $link->getAttribute('reports_count');
                $hits = (int) $link->getAttribute('rate_hits_7d');
                $recent = (int) $link->getAttribute('messages_24h');

                $signals = array_values(array_filter([
                    $reports > 0 ? 'Reportes' : null,
                    $blocks >= self::SIGNAL_BLOCKS ? 'Muchos bloqueos' : null,
                    $hits >= self::SIGNAL_RATE_HITS_7D ? 'Límite de envíos' : null,
                    $recent >= self::SIGNAL_MESSAGES_24H ? 'Volumen alto' : null,
                ]));

                return [
                    'uuid' => $link->uuid,
                    'title' => $link->title,
                    'owner' => $link->user->name,
                    'state' => $link->moderation_disabled_at !== null ? 'disabled' : $link->state(),
                    'messages_count' => $link->messages_count,
                    'messages_24h' => $recent,
                    'blocks_count' => $blocks,
                    'reports_count' => $reports,
                    'rate_hits_7d' => $hits,
                    'signals' => $signals,
                    'created_at' => $link->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Per-athlete totals (top 50 by received messages).
     *
     * @return array<int, array<string, mixed>>
     */
    private function athletes(): array
    {
        $rows = CheerMessage::query()
            ->select('recipient_user_id', DB::raw('count(*) as messages'))
            ->groupBy('recipient_user_id')
            ->orderByDesc('messages')
            ->limit(50)
            ->get();

        $ids = $rows->pluck('recipient_user_id')->all();
        $names = User::query()->whereIn('id', $ids)->get(['id', 'first_name', 'last_name'])->mapWithKeys(fn (User $u) => [$u->id => $u->name]);
        $links = CheerLink::query()->whereIn('user_id', $ids)->select('user_id', DB::raw('count(*) as total'))->groupBy('user_id')->pluck('total', 'user_id');
        $blocks = CheerBlock::query()->whereIn('user_id', $ids)->select('user_id', DB::raw('count(*) as total'))->groupBy('user_id')->pluck('total', 'user_id');
        $reports = CheerMessageReport::query()->whereIn('reporter_user_id', $ids)->select('reporter_user_id', DB::raw('count(*) as total'))->groupBy('reporter_user_id')->pluck('total', 'reporter_user_id');

        return $rows->map(fn (CheerMessage $row) => [
            'name' => $names[$row->recipient_user_id] ?? null,
            'links' => (int) ($links[$row->recipient_user_id] ?? 0),
            'messages' => (int) $row->getAttribute('messages'),
            'blocks' => (int) ($blocks[$row->recipient_user_id] ?? 0),
            'reports' => (int) ($reports[$row->recipient_user_id] ?? 0),
        ])->values()->all();
    }

    /**
     * Senders blocked or reported by athletes, identified only by a short
     * prefix of their IP HMAC (never the IP, never message content).
     *
     * @return array<int, array<string, mixed>>
     */
    private function senders(): array
    {
        $blocked = CheerBlock::query()
            ->select('sender_ip_hash', DB::raw('count(*) as blocks'), DB::raw('count(distinct user_id) as athletes'))
            ->groupBy('sender_ip_hash')
            ->get()
            ->keyBy('sender_ip_hash');

        $reported = CheerMessageReport::query()
            ->whereNotNull('sender_ip_hash')
            ->select('sender_ip_hash', DB::raw('count(*) as reports'))
            ->groupBy('sender_ip_hash')
            ->pluck('reports', 'sender_ip_hash');

        $hits = CheerRateLimitHit::query()
            ->whereNotNull('sender_ip_hash')
            ->where('created_at', '>=', now()->subDays(7))
            ->select('sender_ip_hash', DB::raw('count(*) as hits'))
            ->groupBy('sender_ip_hash')
            ->pluck('hits', 'sender_ip_hash');

        $hashes = $blocked->keys()->merge($reported->keys())->unique()->values();

        $messages = CheerMessage::query()
            ->whereIn('sender_ip_hash', $hashes->all())
            ->select('sender_ip_hash', DB::raw('count(*) as total'))
            ->groupBy('sender_ip_hash')
            ->pluck('total', 'sender_ip_hash');

        return $hashes
            ->map(fn (string $hash) => [
                'ref' => $this->senderRef($hash),
                'blocks' => (int) ($blocked->get($hash)?->getAttribute('blocks') ?? 0),
                'athletes' => (int) ($blocked->get($hash)?->getAttribute('athletes') ?? 0),
                'reports' => (int) ($reported[$hash] ?? 0),
                'messages' => (int) ($messages[$hash] ?? 0),
                'rate_hits_7d' => (int) ($hits[$hash] ?? 0),
            ])
            ->sortByDesc(fn (array $row) => [$row['reports'], $row['blocks'], $row['messages']])
            ->take(50)
            ->values()
            ->all();
    }

    private function senderRef(?string $hash): ?string
    {
        return $hash !== null ? strtoupper(substr($hash, 0, 8)) : null;
    }

    private function moderator(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    private function note(Request $request): ?string
    {
        return $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
    }
}
