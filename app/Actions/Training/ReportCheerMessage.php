<?php

namespace App\Actions\Training;

use App\Enums\CheerMessageStatus;
use App\Enums\CheerReportReason;
use App\Enums\ReportStatus;
use App\Models\CheerMessage;
use App\Models\CheerMessageReport;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * The athlete reports one of their own cheer messages (web + API, same
 * call). The report is what makes that single message reviewable by staff
 * (Administración → Entrenamientos); the message also leaves the athlete's
 * playlist. Reporting twice is idempotent (updates the reason).
 */
class ReportCheerMessage
{
    /**
     * Validation shared by web and API.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(CheerReportReason::class)],
            'details' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function handle(CheerMessage $message, User $reporter, CheerReportReason $reason, ?string $details = null): CheerMessageReport
    {
        $details = $details !== null && trim($details) !== '' ? trim($details) : null;

        $report = CheerMessageReport::query()->updateOrCreate(
            ['cheer_message_id' => $message->id, 'reporter_user_id' => $reporter->id],
            [
                'cheer_link_id' => $message->cheer_link_id,
                'reason' => $reason,
                'details' => $details,
                'sender_ip_hash' => $message->sender_ip_hash,
                'status' => ReportStatus::Open,
            ],
        );

        $message->forceFill(['status' => CheerMessageStatus::Hidden])->save();

        activity('training')
            ->causedBy($reporter)
            ->performedOn($report)
            ->event('cheer_message.reported')
            ->withProperties(['reason' => $reason->value, 'message_uuid' => $message->uuid])
            ->log('Mensaje de ánimo reportado por el atleta.');

        return $report;
    }
}
