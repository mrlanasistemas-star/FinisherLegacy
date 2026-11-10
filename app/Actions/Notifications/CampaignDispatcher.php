<?php

namespace App\Actions\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use App\Jobs\SendPushCampaignBatch;
use App\Models\PushCampaign;
use App\Models\PushCampaignRecipient;
use App\Models\PushDevice;
use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\PushSender;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sends push campaigns (docs/renovacion/campanas-push.md §Envío):
 *
 *  1. start(): flips the campaign to `sending` atomically (a campaign can
 *     never start twice — the scheduler and "Enviar ahora" may race),
 *     re-checks the "Todos" rate guard, freezes the audience into
 *     push_campaign_recipients in batches and queues one
 *     App\Jobs\SendPushCampaignBatch per batch.
 *  2. sendBatch(): claims the batch's pending rows (at-most-once), re-reads
 *     preferences, saves the notification-center copy through
 *     SendAthleteNotification (push = false) and hands every push-allowed
 *     user of the batch to App\Support\Notifications\PushSender in ONE
 *     call — PushSender queues the provider requests per 100 devices.
 *  3. finalize(): when no pending row is left → `sent`, or `failed` (con
 *     errores) when any batch failed.
 *
 * dispatchDue() is what the every-minute scheduler command runs.
 */
final class CampaignDispatcher
{
    public function __construct(
        private readonly CampaignAudience $audience,
        private readonly CampaignRateGuard $rateGuard,
        private readonly NotificationPreferences $preferences,
        private readonly PushSender $pushSender,
        private readonly SendAthleteNotification $notify,
    ) {}

    /** Starts every scheduled campaign whose time has come. */
    public function dispatchDue(): int
    {
        $started = 0;

        PushCampaign::query()
            ->where('status', PushCampaign::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->get()
            ->each(function (PushCampaign $campaign) use (&$started) {
                if ($this->start($campaign)) {
                    $started++;
                }
            });

        return $started;
    }

    public function startById(int $campaignId): bool
    {
        $campaign = PushCampaign::query()->find($campaignId);

        return $campaign !== null && $this->start($campaign);
    }

    public function start(PushCampaign $campaign): bool
    {
        if (! $this->rateGuard->allows($campaign, now())) {
            $campaign->forceFill([
                'status' => PushCampaign::STATUS_FAILED,
                'last_error' => 'No se envió: ya había otra campaña a todos los usuarios en la última hora.',
                'completed_at' => now(),
            ])->save();

            activity()->performedOn($campaign)->log('push_campaign.blocked_by_rate_guard');

            return false;
        }

        $claimed = PushCampaign::query()
            ->whereKey($campaign->id)
            ->whereIn('status', [PushCampaign::STATUS_DRAFT, PushCampaign::STATUS_SCHEDULED])
            ->update(['status' => PushCampaign::STATUS_SENDING, 'started_at' => now(), 'updated_at' => now()]);

        if ($claimed === 0) {
            return false;
        }

        $campaign->refresh();

        $size = max(1, (int) config('push_campaigns.batch_size', 500));
        $count = 0;

        $this->audience->forCampaign($campaign)
            ->select('users.id')
            ->chunkById(1000, function (Collection $users) use ($campaign, $size, &$count) {
                $ids = $users->pluck('id')->map(fn ($id) => (int) $id)->all();

                $devices = PushDevice::query()
                    ->whereIn('user_id', $ids)
                    ->where('active', true)
                    ->groupBy('user_id')
                    ->selectRaw('user_id, count(*) as total')
                    ->pluck('total', 'user_id');

                $now = now();
                $rows = [];

                foreach ($ids as $id) {
                    $rows[] = [
                        'push_campaign_id' => $campaign->id,
                        'user_id' => $id,
                        'batch' => intdiv($count, $size),
                        'devices_count' => min(65535, (int) ($devices[$id] ?? 0)),
                        'status' => PushCampaignRecipient::STATUS_PENDING,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $count++;
                }

                DB::table('push_campaign_recipients')->insertOrIgnore($rows);
            }, 'users.id', 'id');

        $estimate = $this->audience->estimate($campaign->audience_type, $campaign->audience_params ?? [], $campaign->category, $campaign->save_to_inbox);
        $batches = (int) ceil($count / $size);

        $campaign->forceFill([
            'audience_users_count' => $count,
            'audience_devices_count' => $estimate['devices'],
            'batches_total' => $batches,
            'batches_done' => 0,
        ])->save();

        activity()
            ->causedBy($campaign->sent_by !== null ? User::query()->find($campaign->sent_by) : null)
            ->performedOn($campaign)
            ->withProperties(['audience' => $campaign->audience_type, 'users' => $count, 'devices' => $estimate['devices']])
            ->log('push_campaign.started');

        if ($batches === 0) {
            $this->finalize($campaign->id);

            return true;
        }

        for ($batch = 0; $batch < $batches; $batch++) {
            SendPushCampaignBatch::dispatch($campaign->id, $batch);
        }

        return true;
    }

    public function sendBatch(int $campaignId, int $batch): void
    {
        $campaign = PushCampaign::query()->find($campaignId);

        if ($campaign === null || $campaign->status !== PushCampaign::STATUS_SENDING) {
            return;
        }

        $ids = $this->claim($campaign, $batch);

        if ($ids === []) {
            $this->finalize($campaign->id);

            return;
        }

        $category = NotificationCategory::from($campaign->category);
        $destination = CampaignDestination::resolve($campaign);

        $withDevice = PushDevice::query()->whereIn('user_id', $ids)->where('active', true)->distinct()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $pushIds = array_values(array_intersect($this->preferences->filterUsers($ids, $category, 'push'), $withDevice));
        $inboxIds = $campaign->save_to_inbox ? $this->preferences->filterUsers($ids, $category, 'in_app') : [];
        $skipped = array_values(array_diff($ids, $pushIds, $inboxIds));

        if ($skipped !== []) {
            PushCampaignRecipient::query()
                ->where('push_campaign_id', $campaign->id)
                ->whereIn('user_id', $skipped)
                ->update(['status' => PushCampaignRecipient::STATUS_SKIPPED, 'updated_at' => now()]);
        }

        $title = CampaignMessage::plain($campaign->title);
        $body = CampaignMessage::plain($campaign->body);
        $image = $campaign->image_path !== null ? $campaign->imageUrl() : null;

        User::query()->whereIn('id', $inboxIds)->get()->each(function (User $user) use ($campaign, $category, $destination, $title, $body, $image) {
            $this->notify->handle(
                recipient: $user,
                title: $title,
                message: $body,
                type: NotificationType::Custom,
                actionUrl: $destination['action_url'],
                metadata: array_filter([
                    'campaign_uuid' => $campaign->uuid,
                    'target' => $destination['target'],
                    'deep_link' => $destination['deep_link'],
                ]),
                push: false,
                imageUrl: $image,
                category: $category,
            );
        });

        $devices = 0;
        $notConfigured = false;

        if ($pushIds !== []) {
            $summary = $this->pushSender->sendToUsers($pushIds, CampaignMessage::for($campaign));
            $devices = $summary->devices;
            $notConfigured = ! $summary->configured;
        }

        PushCampaign::query()->whereKey($campaign->id)->incrementEach([
            'sent_count' => $devices,
            'inbox_count' => count($inboxIds),
            'skipped_count' => count($skipped),
            'batches_done' => 1,
        ], $notConfigured
            ? ['last_error' => 'No hay un proveedor de push configurado: la campaña solo se guardó en el centro de notificaciones.']
            : []);

        $this->finalize($campaign->id);
    }

    /** A batch exhausted its retries: its claimed / pending rows are failures. */
    public function failBatch(int $campaignId, int $batch, ?string $error): void
    {
        $failed = PushCampaignRecipient::query()
            ->where('push_campaign_id', $campaignId)
            ->where('batch', $batch)
            ->whereIn('status', [PushCampaignRecipient::STATUS_PENDING])
            ->update(['status' => PushCampaignRecipient::STATUS_FAILED, 'error' => Str::limit((string) $error, 250, ''), 'updated_at' => now()]);

        PushCampaign::query()->whereKey($campaignId)->incrementEach(
            ['failed_count' => max(1, $failed), 'batches_done' => 1],
            ['last_error' => Str::limit('Un lote falló: '.($error ?? 'error desconocido'), 495, '…')],
        );

        $this->finalize($campaignId);
    }

    /**
     * Pending rows of this batch → `sent` before any notification goes out,
     * so a retried job finds nothing left to send (at-most-once).
     *
     * @return list<int>
     */
    private function claim(PushCampaign $campaign, int $batch): array
    {
        return array_values(DB::transaction(function () use ($campaign, $batch) {
            $ids = PushCampaignRecipient::query()
                ->where('push_campaign_id', $campaign->id)
                ->where('batch', $batch)
                ->where('status', PushCampaignRecipient::STATUS_PENDING)
                ->lockForUpdate()
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($ids !== []) {
                PushCampaignRecipient::query()
                    ->where('push_campaign_id', $campaign->id)
                    ->where('batch', $batch)
                    ->where('status', PushCampaignRecipient::STATUS_PENDING)
                    ->update(['status' => PushCampaignRecipient::STATUS_SENT, 'sent_at' => now(), 'updated_at' => now()]);
            }

            return $ids;
        }));
    }

    private function finalize(int $campaignId): void
    {
        $pending = PushCampaignRecipient::query()
            ->where('push_campaign_id', $campaignId)
            ->where('status', PushCampaignRecipient::STATUS_PENDING)
            ->exists();

        if ($pending) {
            return;
        }

        $campaign = PushCampaign::query()->find($campaignId);

        if ($campaign === null || $campaign->status !== PushCampaign::STATUS_SENDING) {
            return;
        }

        $done = PushCampaign::query()
            ->whereKey($campaignId)
            ->where('status', PushCampaign::STATUS_SENDING)
            ->update([
                'status' => $campaign->failed_count > 0 ? PushCampaign::STATUS_FAILED : PushCampaign::STATUS_SENT,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($done > 0) {
            activity()
                ->performedOn($campaign)
                ->withProperties([
                    'users' => $campaign->audience_users_count,
                    'devices_queued' => $campaign->sent_count,
                    'inbox' => $campaign->inbox_count,
                    'skipped' => $campaign->skipped_count,
                    'failed' => $campaign->failed_count,
                ])
                ->log('push_campaign.completed');
        }
    }
}
