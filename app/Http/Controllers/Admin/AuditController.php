<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditController extends Controller
{
    private const EVENT_LABELS = [
        'created' => 'Creado',
        'updated' => 'Actualizado',
        'deleted' => 'Eliminado',
        'restored' => 'Restaurado',
    ];

    private const EVENT_VERBS = [
        'created' => 'creó',
        'updated' => 'actualizó',
        'deleted' => 'eliminó',
        'restored' => 'restauró',
    ];

    private const SUBJECT_LABELS = [
        'User' => 'un usuario',
        'Plate' => 'una placa',
        'PlateTemplate' => 'un molde de placa',
        'PlateTemplateVersion' => 'una versión de molde',
        'PlateReprint' => 'una reimpresión de placa',
        'ProductionJob' => 'un trabajo de producción',
        'ProductionDevice' => 'una estación de producción',
        'MachineProfile' => 'un perfil de máquina',
        'LegacyCode' => 'un Legacy Code',
        'EventResult' => 'un resultado',
        'EventResultSplit' => 'un parcial de resultado',
        'EventParticipant' => 'un participante',
        'EventIncident' => 'una incidencia',
        'EventEdition' => 'una edición de evento',
        'EventRace' => 'una carrera',
        'Event' => 'un evento',
        'Organizer' => 'un organizador',
        'Role' => 'un rol',
        'Medal' => 'una medalla',
        'Athlete' => 'un atleta',
        'AthleteIdentityConflict' => 'un conflicto de identidad de atleta',
    ];

    public function index(Request $request): Response
    {
        $activities = Activity::query()
            ->with('causer')
            ->when($request->integer('user_id'), fn ($q, $userId) => $q->where('causer_id', $userId)->where('causer_type', User::class))
            ->when($request->string('event')->toString(), fn ($q, $event) => $q->where('event', $event))
            ->when($request->string('subject_type')->toString(), fn ($q, $type) => $q->where('subject_type', $type))
            ->when($request->date('from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->date('to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $activities->through(function (Activity $activity) {
            $causerName = $activity->causer instanceof User ? $activity->causer->name : null;
            $causerId = $activity->causer instanceof User ? $activity->causer->id : null;
            $subjectBaseName = $activity->subject_type ? class_basename($activity->subject_type) : null;

            return [
                'id' => $activity->id,
                'causer' => $causerName ?? 'Sistema',
                'event' => $activity->event ?? '—',
                'event_label' => self::EVENT_LABELS[$activity->event] ?? ($activity->event ?? '—'),
                'subject_type' => $subjectBaseName ?? '—',
                'subject_type_label' => $subjectBaseName !== null
                    ? ucfirst(self::SUBJECT_LABELS[$subjectBaseName] ?? $subjectBaseName)
                    : '—',
                'subject_id' => $activity->subject_id,
                'description' => $this->describe($activity, $causerName, $causerId, $subjectBaseName),
                'created_at' => $activity->created_at->format('d/m/Y H:i'),
                'created_at_iso' => $activity->created_at->toIso8601String(),
            ];
        });

        return Inertia::render('admin/audit/Index', [
            'activities' => $activities,
            'events' => Activity::query()->distinct()->orderBy('event')->pluck('event')->filter()
                ->map(fn (string $event) => ['value' => $event, 'label' => self::EVENT_LABELS[$event] ?? $event])
                ->values(),
            'subjectTypes' => Activity::query()
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->filter()
                ->map(function (string $type) {
                    $base = class_basename($type);

                    return ['value' => $type, 'label' => ucfirst(self::SUBJECT_LABELS[$base] ?? $base)];
                })
                ->values(),
            'users' => User::query()->orderBy('first_name')->get()->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
            ]),
            'filters' => [
                'user_id' => $request->integer('user_id') ?: null,
                'event' => $request->string('event')->toString(),
                'subject_type' => $request->string('subject_type')->toString(),
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
            ],
        ]);
    }

    /**
     * Builds a single readable Spanish sentence from the activity's
     * structured fields — never trusts Spatie's stored `description` alone,
     * since by default that's just the raw event word ("updated"). This
     * runs at read time against the existing causer/event/subject columns,
     * so it applies to every historical row too, not only future ones.
     */
    private function describe(Activity $activity, ?string $causerName, ?int $causerId, ?string $subjectBaseName): string
    {
        $verb = self::EVENT_VERBS[$activity->event] ?? ($activity->event ?? 'modificó');
        $subject = $subjectBaseName !== null
            ? (self::SUBJECT_LABELS[$subjectBaseName] ?? 'un registro de '.$subjectBaseName)
            : 'un registro';
        $subjectId = $activity->subject_id ? " (#{$activity->subject_id})" : '';

        $who = $causerName !== null
            ? "El usuario con ID {$causerId} de nombre \"{$causerName}\""
            : 'El sistema';

        return "{$who} {$verb} {$subject}{$subjectId}.";
    }
}
