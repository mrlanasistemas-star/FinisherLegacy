<?php

namespace App\Jobs;

use App\Actions\Notifications\CampaignDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * One batch of a push campaign's frozen audience
 * (App\Actions\Notifications\CampaignDispatcher, docs/renovacion/campanas-push.md).
 * Holds only ids; the work (inbox copies + one PushSender call for the
 * whole batch) lives in CampaignDispatcher::sendBatch(), which claims the
 * batch's pending rows first, so a retried job never notifies anyone twice.
 */
class SendPushCampaignBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(
        public readonly int $campaignId,
        public readonly int $batch,
    ) {
        $this->onQueue((string) config('queue.names.realtime', 'default'));
    }

    public function handle(CampaignDispatcher $dispatcher): void
    {
        $dispatcher->sendBatch($this->campaignId, $this->batch);
    }

    public function failed(?Throwable $exception): void
    {
        app(CampaignDispatcher::class)->failBatch($this->campaignId, $this->batch, $exception?->getMessage());
    }
}
