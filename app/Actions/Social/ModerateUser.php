<?php

namespace App\Actions\Social;

use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\NotificationType;
use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Models\Report;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Administración → Comunidad: actions on a PERSON (ModerateContent acts on
 * a post/comment). Both are written to the `community` activity log with
 * the moderator as causer, and both can close the report they came from.
 *
 *  - warn():    the athlete gets an in-app notification (+ push) with the
 *               moderator's message. Nothing else changes on the account.
 *  - suspend(): status → suspended, every session/API token revoked at once
 *               (EnsureUserIsActive rejects whatever is left). Reversible
 *               only from Administración → Usuarios, which owns account
 *               status. Staff accounts are never suspended from here.
 */
class ModerateUser
{
    public function __construct(private readonly SendAthleteNotification $notify) {}

    public function warn(User $moderator, User $user, string $message, ?Report $report = null): void
    {
        $this->assertModeratable($moderator, $user);

        $this->notify->handle(
            $user,
            fn () => __('Aviso de la comunidad Finisher Legacy'),
            $message,
            NotificationType::Custom,
            metadata: ['moderation' => 'warning'],
            sentBy: $moderator,
            push: true,
        );

        activity('community')
            ->causedBy($moderator)
            ->performedOn($user)
            ->event('user.warned')
            ->withProperties(array_filter(['message' => $message, 'report' => $report?->uuid]))
            ->log("Advertencia enviada a {$user->name}.");

        $this->closeReport($moderator, $report, 'Usuario advertido: '.$message);
    }

    public function suspend(User $moderator, User $user, string $reason, ?Report $report = null): void
    {
        $this->assertModeratable($moderator, $user);

        $previous = $user->status;
        $user->status = UserStatus::Suspended;
        $user->revokeAllAccess();
        $user->save();

        activity('community')
            ->causedBy($moderator)
            ->performedOn($user)
            ->event('user.suspended')
            ->withProperties(array_filter(['from' => $previous->value, 'reason' => $reason, 'report' => $report?->uuid]))
            ->log("Cuenta de {$user->name} suspendida por moderación.");

        $this->closeReport($moderator, $report, 'Usuario suspendido: '.$reason);
    }

    /**
     * Moderators act on athletes only: not on themselves, and never on
     * staff (anyone who can open the admin panel) — those accounts are
     * managed in Administración → Usuarios.
     */
    private function assertModeratable(User $moderator, User $user): void
    {
        if ($user->is($moderator)) {
            throw ValidationException::withMessages(['user' => __('No puedes moderar tu propia cuenta.')]);
        }

        if ($user->can('dashboard.admin.view')) {
            throw ValidationException::withMessages(['user' => __('Las cuentas del equipo se gestionan en Administración → Usuarios.')]);
        }

        if ($user->trashed()) {
            throw ValidationException::withMessages(['user' => __('Esta cuenta ya fue eliminada.')]);
        }
    }

    private function closeReport(User $moderator, ?Report $report, string $notes): void
    {
        if ($report === null || ! in_array($report->status, [ReportStatus::Open, ReportStatus::Reviewing], true)) {
            return;
        }

        $report->update([
            'status' => ReportStatus::Resolved,
            'resolution_notes' => $notes,
            'reviewed_by' => $moderator->id,
            'reviewed_at' => now(),
        ]);
    }
}
