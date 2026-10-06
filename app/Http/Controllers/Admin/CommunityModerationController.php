<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Social\DeleteMoment;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Models\AthleteFollow;
use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Comunidad: the review panel the social architecture
 * left for later (docs/architecture/social.md §Moderación) — open
 * reports, recent publications, and removal through the same
 * DeleteMoment action an author uses.
 */
class CommunityModerationController extends Controller
{
    public function index(): Response
    {
        $reports = Report::query()
            ->whereIn('status', [ReportStatus::Open, ReportStatus::Reviewing])
            ->with('reporter')
            ->latest('id')
            ->limit(50)
            ->get();

        // One lookup for every reported moment (no per-row query).
        $momentUuids = LegacyMoment::query()
            ->whereIn('id', $reports->filter(fn (Report $r) => $r->target_type->value === 'moment')->pluck('target_id'))
            ->pluck('uuid', 'id');

        return Inertia::render('admin/community/Index', [
            'stats' => [
                'moments' => LegacyMoment::query()->count(),
                'moments_week' => LegacyMoment::query()->where('created_at', '>=', now()->subDays(7))->count(),
                'comments' => LegacyMomentComment::query()->count(),
                'follows' => AthleteFollow::query()->count(),
                'open_reports' => Report::query()->where('status', ReportStatus::Open)->count(),
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
                'moment_uuid' => $report->target_type->value === 'moment' ? ($momentUuids[$report->target_id] ?? null) : null,
            ]),
            'recent' => LegacyMoment::query()
                ->with('author.athleteProfile')
                ->withCount(['comments', 'reactions'])
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (LegacyMoment $moment) => [
                    'uuid' => $moment->uuid,
                    'caption' => Str::limit((string) $moment->caption, 140),
                    'type' => $moment->type->value,
                    'visibility' => $moment->visibility->value,
                    'author' => $moment->author?->name,
                    'username' => $moment->author?->athleteProfile?->username,
                    'comments_count' => $moment->comments_count,
                    'reactions_count' => $moment->reactions_count,
                    'created_at' => $moment->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function updateReport(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ReportStatus::class)],
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $report->update([
            ...$data,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reporte actualizado.']);

        return back();
    }

    public function destroyMoment(LegacyMoment $moment, DeleteMoment $delete): RedirectResponse
    {
        $delete->handle($moment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Publicación eliminada por moderación.']);

        return back();
    }
}
