<?php

namespace App\Actions\Notifications;

use App\Models\PushCampaign;
use App\Models\User;
use App\Support\Notifications\PushDispatchSummary;
use App\Support\Notifications\PushSender;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Admin-side lifecycle of a push campaign (docs/renovacion/campanas-push.md):
 * save a draft, schedule / reschedule, send now, cancel, duplicate and the
 * "Enviarme una prueba" test send. Every state change is audited
 * (activity log `push_campaign.*`) with who did it — never the content.
 *
 * Sending itself lives in CampaignDispatcher; "Enviar ahora" just queues
 * its start so the request returns immediately.
 */
final class CampaignManager
{
    public function __construct(
        private readonly CampaignRateGuard $rateGuard,
        private readonly PushSender $pushSender,
        private readonly SendAthleteNotification $notify,
    ) {}

    /**
     * Create or update a draft / scheduled campaign from validated input.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(?PushCampaign $campaign, array $data, ?UploadedFile $image, bool $removeImage, User $by): PushCampaign
    {
        $campaign ??= new PushCampaign(['status' => PushCampaign::STATUS_DRAFT, 'created_by' => $by->id]);

        if ($campaign->exists && ! $campaign->isEditable()) {
            throw ValidationException::withMessages(['campaign' => 'Esta campaña ya se envió o se canceló: duplícala para hacer otra.']);
        }

        $linkType = (string) $data['link_type'];
        $linkValue = in_array($linkType, CampaignDestination::WITH_VALUE, true) ? (string) $data['link_value'] : null;

        $campaign->fill([
            'name' => $data['name'] ?? null,
            'title' => CampaignMessage::plain((string) $data['title']),
            'body' => CampaignMessage::plain((string) $data['body']),
            'include_logo' => (bool) ($data['include_logo'] ?? true),
            'color' => isset($data['color']) ? strtoupper((string) $data['color']) : null,
            'channel' => (string) ($data['channel'] ?? 'avisos'),
            'category' => (string) $data['category'],
            'save_to_inbox' => (bool) ($data['save_to_inbox'] ?? true),
            'link_type' => $linkType,
            'link_value' => $linkValue,
            'link_label' => CampaignDestination::label($linkType, $linkValue),
            'audience_type' => (string) $data['audience_type'],
            'audience_params' => self::audienceParams((string) $data['audience_type'], (array) ($data['audience_params'] ?? [])),
            'updated_by' => $by->id,
        ]);

        if ($removeImage || $image !== null) {
            $this->deleteImage($campaign);
            $campaign->image_path = null;
        }

        if ($image !== null) {
            $path = $image->store('push-campaigns', 'public');

            if ($path === false) {
                throw ValidationException::withMessages(['image' => 'No pudimos guardar la imagen. Intenta de nuevo.']);
            }

            $campaign->image_path = $path;
        }

        $isNew = ! $campaign->exists;
        $campaign->save();

        activity()->causedBy($by)->performedOn($campaign)->log($isNew ? 'push_campaign.created' : 'push_campaign.updated');

        return $campaign;
    }

    /** Back to draft (keeps everything, removes the date). */
    public function unschedule(PushCampaign $campaign, User $by): void
    {
        if ($campaign->status !== PushCampaign::STATUS_SCHEDULED) {
            return;
        }

        $campaign->forceFill(['status' => PushCampaign::STATUS_DRAFT, 'scheduled_at' => null, 'updated_by' => $by->id])->save();

        activity()->causedBy($by)->performedOn($campaign)->log('push_campaign.unscheduled');
    }

    public function schedule(PushCampaign $campaign, CarbonInterface $at, User $by): void
    {
        $this->ensureEditable($campaign);
        $this->rateGuard->ensure($campaign, $at);

        $wasScheduled = $campaign->status === PushCampaign::STATUS_SCHEDULED;

        $campaign->forceFill([
            'status' => PushCampaign::STATUS_SCHEDULED,
            'scheduled_at' => $at,
            'sent_by' => $by->id,
            'updated_by' => $by->id,
        ])->save();

        activity()
            ->causedBy($by)
            ->performedOn($campaign)
            ->withProperties(['scheduled_at' => $at->toIso8601String(), 'audience' => $campaign->audience_type])
            ->log($wasScheduled ? 'push_campaign.rescheduled' : 'push_campaign.scheduled');
    }

    public function sendNow(PushCampaign $campaign, User $by): void
    {
        $this->ensureEditable($campaign);
        $this->rateGuard->ensure($campaign, now());

        $campaign->forceFill([
            'status' => PushCampaign::STATUS_SCHEDULED,
            'scheduled_at' => now(),
            'sent_by' => $by->id,
            'updated_by' => $by->id,
        ])->save();

        activity()->causedBy($by)->performedOn($campaign)->withProperties(['audience' => $campaign->audience_type])->log('push_campaign.send_now');

        $id = $campaign->id;

        // Queued so a big audience snapshot never holds the request; the
        // every-minute scheduler would also pick it up (start() is atomic).
        dispatch(function () use ($id) {
            app(CampaignDispatcher::class)->startById($id);
        });
    }

    public function cancel(PushCampaign $campaign, User $by): void
    {
        $this->ensureEditable($campaign);

        $updated = PushCampaign::query()
            ->whereKey($campaign->id)
            ->whereIn('status', [PushCampaign::STATUS_DRAFT, PushCampaign::STATUS_SCHEDULED])
            ->update([
                'status' => PushCampaign::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $by->id,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw ValidationException::withMessages(['campaign' => 'La campaña ya empezó a enviarse: no se puede cancelar.']);
        }

        activity()->causedBy($by)->performedOn($campaign)->log('push_campaign.cancelled');
    }

    public function duplicate(PushCampaign $source, User $by): PushCampaign
    {
        $copy = new PushCampaign([
            ...$source->only([
                'title', 'body', 'include_logo', 'color', 'channel', 'category', 'save_to_inbox',
                'link_type', 'link_value', 'link_label', 'audience_type', 'audience_params',
            ]),
            'name' => $source->name !== null ? mb_strimwidth('Copia de '.$source->name, 0, 120, '') : null,
            'status' => PushCampaign::STATUS_DRAFT,
            'duplicated_from_id' => $source->id,
            'created_by' => $by->id,
            'updated_by' => $by->id,
        ]);

        if ($source->image_path !== null && Storage::disk('public')->exists($source->image_path)) {
            $extension = pathinfo($source->image_path, PATHINFO_EXTENSION);
            $path = 'push-campaigns/'.bin2hex(random_bytes(16)).($extension !== '' ? '.'.$extension : '');
            Storage::disk('public')->copy($source->image_path, $path);
            $copy->image_path = $path;
        }

        $copy->save();

        activity()->causedBy($by)->performedOn($copy)->withProperties(['from' => $source->uuid])->log('push_campaign.duplicated');

        return $copy;
    }

    /**
     * Push to the admin's own devices only (no preference check, title
     * prefixed "[Prueba]") plus a notification-center copy when the
     * campaign saves one, so the result is visible even without the app.
     */
    public function testSend(PushCampaign $campaign, User $by): PushDispatchSummary
    {
        $summary = $this->pushSender->sendToUser($by, CampaignMessage::for($campaign, test: true));

        if ($campaign->save_to_inbox) {
            $destination = CampaignDestination::resolve($campaign);

            $this->notify->handle(
                recipient: $by,
                title: mb_strimwidth('[Prueba] '.$campaign->title, 0, 65, '…'),
                message: $campaign->body,
                actionUrl: $destination['action_url'],
                metadata: array_filter(['campaign_uuid' => $campaign->uuid, 'target' => $destination['target'], 'test' => true]),
                imageUrl: $campaign->image_path !== null ? $campaign->imageUrl() : null,
            );
        }

        activity()->causedBy($by)->performedOn($campaign)->withProperties(['devices' => $summary->devices])->log('push_campaign.test_sent');

        return $summary;
    }

    /**
     * Only the keys each audience type uses — never arbitrary input.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function audienceParams(string $type, array $params): array
    {
        return match ($type) {
            'users' => ['user_ids' => CampaignAudience::intList($params['user_ids'] ?? [])],
            'event' => array_filter([
                'event_id' => (int) ($params['event_id'] ?? 0),
                'edition_id' => isset($params['edition_id']) ? (int) $params['edition_id'] : null,
                'race_id' => isset($params['race_id']) ? (int) $params['race_id'] : null,
            ]),
            'collection' => ['collection_id' => (int) ($params['collection_id'] ?? 0)],
            'product' => ['product_id' => (int) ($params['product_id'] ?? 0)],
            'role' => ['role' => (string) ($params['role'] ?? '')],
            default => [],
        };
    }

    private function ensureEditable(PushCampaign $campaign): void
    {
        if (! $campaign->isEditable()) {
            throw ValidationException::withMessages(['campaign' => 'Esta campaña ya se envió o se canceló.']);
        }
    }

    private function deleteImage(PushCampaign $campaign): void
    {
        if ($campaign->image_path === null) {
            return;
        }

        // A duplicate gets its own copy, so the file is never shared.
        Storage::disk('public')->delete($campaign->image_path);
    }
}
