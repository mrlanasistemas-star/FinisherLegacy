<?php

namespace App\Console\Commands;

use App\Contracts\Notifications\PushNotificationGateway;
use App\Models\PushCampaign;
use App\Models\PushDevice;
use App\Models\PushTicket;
use App\Services\Notifications\ExpoPushNotificationGateway;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Expo push receipts (scheduled every 15 min, routes/console.php). A ticket
 * only says Expo accepted the message; the receipt says whether FCM/APNs
 * delivered it. For tickets older than 15 minutes:
 *
 *  - `ok` → checked (and counted as delivered for its push campaign, if any);
 *  - `DeviceNotRegistered` → the device is deactivated (uninstalled app /
 *    revoked token), so we stop sending to it;
 *  - other errors (InvalidCredentials, MessageTooBig…) → logged once, checked
 *    (any receipt error counts as failed for its push campaign);
 *  - no receipt after 24 h (Expo's retention) → checked as `expired`.
 *
 * Checked rows older than two days are pruned.
 */
#[Signature('push:check-receipts {--min-age=15 : Minutos de antigüedad mínima del ticket}')]
#[Description('Lee los recibos de Expo Push, desactiva dispositivos dados de baja y limpia tickets viejos.')]
class CheckPushReceipts extends Command
{
    public function handle(PushNotificationGateway $gateway): int
    {
        if (! $gateway instanceof ExpoPushNotificationGateway) {
            $this->info('El proveedor de push no es Expo; nada que revisar.');

            return self::SUCCESS;
        }

        $minAge = max(0, (int) $this->option('min-age'));
        $checked = 0;
        $deactivated = 0;

        PushTicket::query()
            ->whereNull('checked_at')
            ->where('created_at', '<=', now()->subMinutes($minAge))
            ->orderBy('id')
            ->chunkById(1000, function ($tickets) use ($gateway, &$checked, &$deactivated) {
                $receipts = $gateway->receipts($tickets->pluck('ticket_id')->all());

                if ($receipts === null) {
                    return false; // transient — try on the next run
                }

                /** @var array<int, array{delivered: int, failed: int}> $campaigns */
                $campaigns = [];

                foreach ($tickets as $ticket) {
                    /** @var PushTicket $ticket */
                    $receipt = $receipts[$ticket->ticket_id] ?? null;

                    if (! is_array($receipt)) {
                        if ($ticket->created_at !== null && $ticket->created_at->lt(now()->subDay())) {
                            $ticket->update(['status' => 'expired', 'checked_at' => now()]);
                            $checked++;
                        }

                        continue;
                    }

                    $error = $receipt['details']['error'] ?? null;

                    if (($receipt['status'] ?? null) !== 'ok' && $error === 'DeviceNotRegistered') {
                        PushDevice::query()->whereKey($ticket->push_device_id)->update(['active' => false]);
                        Log::info('push.expo.device_deactivated', ['push_device_id' => $ticket->push_device_id, 'source' => 'receipt']);
                        $deactivated++;
                    } elseif (($receipt['status'] ?? null) !== 'ok') {
                        Log::warning('push.expo.receipt_error', ['push_device_id' => $ticket->push_device_id, 'error' => $error]);
                    }

                    $delivered = ($receipt['status'] ?? null) === 'ok';

                    if ($ticket->push_campaign_id !== null) {
                        $campaigns[$ticket->push_campaign_id] ??= ['delivered' => 0, 'failed' => 0];
                        $campaigns[$ticket->push_campaign_id][$delivered ? 'delivered' : 'failed']++;
                    }

                    $ticket->update([
                        'status' => $delivered ? 'ok' : 'error',
                        'error' => is_string($error) ? mb_substr($error, 0, 64) : null,
                        'checked_at' => now(),
                    ]);
                    $checked++;
                }

                // Real delivery numbers for the campaign panel — kept as
                // counters because the ticket rows are pruned below.
                foreach ($campaigns as $campaignId => $totals) {
                    PushCampaign::query()->whereKey($campaignId)->incrementEach(array_filter([
                        'delivered_count' => $totals['delivered'],
                        'delivery_failed_count' => $totals['failed'],
                    ]));
                }

                return true;
            });

        $pruned = PushTicket::query()
            ->where('created_at', '<', now()->subDays(2))
            ->delete();

        $this->info("Recibos revisados: {$checked}. Dispositivos desactivados: {$deactivated}. Tickets eliminados: {$pruned}.");

        return self::SUCCESS;
    }
}
