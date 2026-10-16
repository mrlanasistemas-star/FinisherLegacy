<?php

namespace App\Support\Observability;

use App\Exceptions\PaymentAmountMismatchException;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Structured operational logging (docs/ARCHITECTURE.md §Observability).
 * request_id comes for free from Laravel Context (AssignRequestId), also
 * inside queued jobs. Admins never see these raw logs — ops reads them.
 */
final class OperationalLog
{
    /**
     * Any exception while handling a payment webhook, or an amount mismatch
     * anywhere, is a payment incident: copied to the `payments` channel
     * (default reporting still happens too).
     */
    public static function reportPaymentIncident(Throwable $e, ?Request $request = null): void
    {
        $isWebhook = $request?->is('api/webhooks/*') ?? false;

        if (! $isWebhook && ! $e instanceof PaymentAmountMismatchException) {
            return;
        }

        Log::channel('payments')->error('payment.incident', [
            'exception' => $e::class,
            'message' => mb_strimwidth($e->getMessage(), 0, 300, '…'),
            'path' => $request?->path(),
            'provider' => $isWebhook ? $request->segment(3) : null,
        ]);
    }

    public static function jobFailed(JobFailed $event): void
    {
        $name = $event->job->resolveName();

        Log::error('queue.job_failed', [
            'job' => $name,
            'connection' => $event->connectionName,
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),
            'exception' => $event->exception::class,
            'message' => mb_strimwidth($event->exception->getMessage(), 0, 300, '…'),
        ]);

        if (str_contains($name, 'Commerce') || str_contains($name, 'Payment')) {
            Log::channel('payments')->error('payment.job_failed', [
                'job' => $name,
                'exception' => $event->exception::class,
            ]);
        }
    }
}
