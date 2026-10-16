<?php

namespace App\Actions\Social;

use App\Enums\ReportStatus;
use App\Enums\ReportTargetType;
use App\Models\LegacyMoment;
use App\Models\LegacyMomentComment;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin → Comunidad moderation (spec §59). Hiding never deletes: the
 * content disappears for everyone but its author and can be restored.
 * Every action is written to the activity log (causer = moderator) and,
 * when it comes from a report, closes the open reports on that target.
 */
class ModerateContent
{
    public function hide(User $moderator, LegacyMoment|LegacyMomentComment $target, ?string $note = null): void
    {
        $target->forceFill(['hidden_at' => now(), 'hidden_by' => $moderator->id])->save();

        $this->audit($moderator, $target, 'hidden', $note);
        $this->closeReports($moderator, $target, ReportStatus::Resolved, $note ?? 'Contenido ocultado por moderación.');
    }

    public function restore(User $moderator, LegacyMoment|LegacyMomentComment $target, ?string $note = null): void
    {
        $target->forceFill(['hidden_at' => null, 'hidden_by' => null])->save();

        $this->audit($moderator, $target, 'restored', $note);
    }

    public function resolveReport(User $moderator, Report $report, ReportStatus $status, ?string $notes): void
    {
        $report->update([
            'status' => $status,
            'resolution_notes' => $notes,
            'reviewed_by' => $moderator->id,
            'reviewed_at' => now(),
        ]);

        activity('community')
            ->causedBy($moderator)
            ->performedOn($report)
            ->event('report.'.$status->value)
            ->withProperties(['target_type' => $report->target_type->value, 'target_id' => $report->target_id])
            ->log("Reporte {$report->uuid} marcado como {$status->value}.");
    }

    private function audit(User $moderator, Model $target, string $event, ?string $note): void
    {
        $label = $target instanceof LegacyMoment ? 'publicación' : 'comentario';

        activity('community')
            ->causedBy($moderator)
            ->performedOn($target)
            ->event(($target instanceof LegacyMoment ? 'moment.' : 'comment.').$event)
            ->withProperties(array_filter(['note' => $note]))
            ->log(ucfirst($label).' '.($event === 'hidden' ? 'ocultado' : 'restaurado').' por moderación.');
    }

    private function closeReports(User $moderator, Model $target, ReportStatus $status, string $notes): void
    {
        Report::query()
            ->where('target_type', $target instanceof LegacyMoment ? ReportTargetType::Moment : ReportTargetType::Comment)
            ->where('target_id', $target->getKey())
            ->whereIn('status', [ReportStatus::Open, ReportStatus::Reviewing])
            ->update([
                'status' => $status,
                'resolution_notes' => $notes,
                'reviewed_by' => $moderator->id,
                'reviewed_at' => now(),
            ]);
    }
}
