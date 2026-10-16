<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Social\DeleteMoment;
use App\Actions\Social\ModerateContent;
use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use App\Http\Controllers\Controller;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Models\Report;
use App\Models\UserBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Comunidad (spec §59): three tabs — Reportes,
 * Contenido moderado, Usuarios bloqueados. Moderation hides (restorable,
 * audited through ModerateContent); permanent removal reuses the same
 * DeleteMoment action an author uses.
 */
class CommunityModerationController extends Controller
{
    private const array TABS = ['reportes', 'contenido', 'bloqueos'];

    public function index(Request $request): Response
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'reportes';

        $reports = Report::query()
            ->whereIn('status', [ReportStatus::Open, ReportStatus::Reviewing])
            ->with('reporter')
            ->latest('id')
            ->limit(50)
            ->get();

        $targets = $this->reportTargets($reports);

        return Inertia::render('admin/community/Index', [
            'tab' => $tab,
            'stats' => [
                'moments' => LegacyMoment::query()->count(),
                'moments_week' => LegacyMoment::query()->where('created_at', '>=', now()->subDays(7))->count(),
                'comments' => LegacyMomentComment::query()->count(),
                'follows' => AthleteFollow::query()->count(),
                'open_reports' => Report::query()->where('status', ReportStatus::Open)->count(),
                'hidden' => LegacyMoment::query()->whereNotNull('hidden_at')->count()
                    + LegacyMomentComment::query()->whereNotNull('hidden_at')->count(),
                'blocks' => UserBlock::query()->count(),
            ],
            'reports' => $reports->map(fn (Report $report) => [
                'id' => $report->id,
                'target_type' => $report->target_type->value,
                'target_id' => $report->target_id,
                'reason' => $report->reason,
                'details' => $report->details,
                'status' => $report->status->value,
                'reporter' => $report->reporter?->name,
                'created_at' => $report->created_at?->toIso8601String(),
                'target' => $targets[$report->target_type->value][$report->target_id] ?? null,
                // Kept for older clients of this page.
                'moment_uuid' => $report->target_type === ReportTargetType::Moment
                    ? ($targets['moment'][$report->target_id]['uuid'] ?? null)
                    : null,
            ])->values(),
            'hiddenMoments' => LegacyMoment::query()
                ->whereNotNull('hidden_at')
                ->with(['author.athleteProfile', 'hiddenBy'])
                ->latest('hidden_at')
                ->limit(50)
                ->get()
                ->map(fn (LegacyMoment $moment) => [
                    ...$this->momentRow($moment),
                    'hidden_at' => $moment->hidden_at?->toIso8601String(),
                    'hidden_by' => $moment->hiddenBy?->name,
                ]),
            'hiddenComments' => LegacyMomentComment::query()
                ->whereNotNull('hidden_at')
                ->with(['author.athleteProfile', 'moment:id,uuid'])
                ->latest('hidden_at')
                ->limit(50)
                ->get()
                ->map(fn (LegacyMomentComment $comment) => [
                    ...$this->commentRow($comment),
                    'hidden_at' => $comment->hidden_at?->toIso8601String(),
                ]),
            'recent' => LegacyMoment::query()
                ->whereNull('hidden_at')
                ->with('author.athleteProfile')
                ->withCount(['comments', 'reactions'])
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (LegacyMoment $moment) => [
                    ...$this->momentRow($moment),
                    'comments_count' => $moment->comments_count,
                    'reactions_count' => $moment->reactions_count,
                ]),
            'blocks' => UserBlock::query()
                ->with(['blocker.athleteProfile', 'blocked.athleteProfile'])
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (UserBlock $block) => [
                    'id' => $block->id,
                    'blocker' => $block->blocker?->name,
                    'blocker_username' => $block->blocker?->athleteProfile?->username,
                    'blocked' => $block->blocked?->name,
                    'blocked_username' => $block->blocked?->athleteProfile?->username,
                    'created_at' => $block->created_at?->toIso8601String(),
                ]),
            // People blocked by several athletes are a moderation signal.
            'mostBlocked' => UserBlock::query()
                ->select('blocked_id', DB::raw('count(*) as total'))
                ->groupBy('blocked_id')
                ->having('total', '>', 1)
                ->orderByDesc('total')
                ->limit(10)
                ->with('blocked.athleteProfile')
                ->get()
                ->map(fn (UserBlock $row) => [
                    'name' => $row->blocked?->name,
                    'username' => $row->blocked?->athleteProfile?->username,
                    'total' => (int) $row->getAttribute('total'),
                ]),
        ]);
    }

    public function updateReport(Request $request, Report $report, ModerateContent $moderate): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ReportStatus::class)],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $moderate->resolveReport($request->user(), $report, ReportStatus::from($data['status']), $data['resolution_notes'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reporte actualizado.']);

        return back();
    }

    public function hideMoment(Request $request, LegacyMoment $moment, ModerateContent $moderate): RedirectResponse
    {
        $moderate->hide($request->user(), $moment, $this->note($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Publicación ocultada.']);

        return back();
    }

    public function restoreMoment(Request $request, LegacyMoment $moment, ModerateContent $moderate): RedirectResponse
    {
        $moderate->restore($request->user(), $moment, $this->note($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Publicación restaurada.']);

        return back();
    }

    public function hideComment(Request $request, LegacyMomentComment $comment, ModerateContent $moderate): RedirectResponse
    {
        $moderate->hide($request->user(), $comment, $this->note($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Comentario ocultado.']);

        return back();
    }

    public function restoreComment(Request $request, LegacyMomentComment $comment, ModerateContent $moderate): RedirectResponse
    {
        $moderate->restore($request->user(), $comment, $this->note($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Comentario restaurado.']);

        return back();
    }

    public function destroyMoment(Request $request, LegacyMoment $moment, DeleteMoment $delete): RedirectResponse
    {
        activity('community')
            ->causedBy($request->user())
            ->performedOn($moment)
            ->event('moment.deleted')
            ->log('Publicación eliminada por moderación.');

        $delete->handle($moment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Publicación eliminada por moderación.']);

        return back();
    }

    private function note(Request $request): ?string
    {
        return $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
    }

    /**
     * One query per target type for the whole page (no per-row lookups).
     *
     * @param  Collection<int, Report>  $reports
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function reportTargets(Collection $reports): array
    {
        $ids = fn (ReportTargetType $type) => $reports->filter(fn (Report $r) => $r->target_type === $type)->pluck('target_id')->unique()->values();

        return [
            'moment' => LegacyMoment::withTrashed()
                ->whereIn('id', $ids(ReportTargetType::Moment))
                ->with('author.athleteProfile')
                ->get()
                ->mapWithKeys(fn (LegacyMoment $m) => [$m->id => [...$this->momentRow($m), 'deleted' => $m->trashed()]])
                ->all(),
            'comment' => LegacyMomentComment::withTrashed()
                ->whereIn('id', $ids(ReportTargetType::Comment))
                ->with(['author.athleteProfile', 'moment:id,uuid'])
                ->get()
                ->mapWithKeys(fn (LegacyMomentComment $c) => [$c->id => [...$this->commentRow($c), 'deleted' => $c->trashed()]])
                ->all(),
            'profile' => AthleteProfile::query()
                ->whereIn('id', $ids(ReportTargetType::Profile))
                ->with('user')
                ->get()
                ->mapWithKeys(fn (AthleteProfile $p) => [$p->id => [
                    'username' => $p->username,
                    'author' => $p->user?->name,
                    'excerpt' => Str::limit((string) $p->bio, 140),
                ]])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function momentRow(LegacyMoment $moment): array
    {
        return [
            'uuid' => $moment->uuid,
            'excerpt' => Str::limit((string) $moment->caption, 140),
            'type' => $moment->type->value,
            'visibility' => $moment->visibility->value,
            'author' => $moment->author?->name,
            'username' => $moment->author?->athleteProfile?->username,
            'is_hidden' => $moment->hidden_at !== null,
            'created_at' => $moment->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commentRow(LegacyMomentComment $comment): array
    {
        return [
            'uuid' => $comment->uuid,
            'excerpt' => Str::limit($comment->body, 140),
            'moment_uuid' => $comment->moment?->uuid,
            'author' => $comment->author?->name,
            'username' => $comment->author?->athleteProfile?->username,
            'is_hidden' => $comment->hidden_at !== null,
            'created_at' => $comment->created_at?->toIso8601String(),
        ];
    }
}
