<?php

namespace App\Console\Commands;

use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\BillingPeriod;
use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use App\Enums\StoryQuotaKind;
use App\Models\MediaEntitlement;
use App\Models\PhotoExpansionPack;
use App\Notifications\PlanExpiryReminder;
use App\Support\I18n\RecipientLocale;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily reminders for prepaid expansion terms (routes/console.php,
 * docs/renovacion/cuotas-y-precios.md §Planes por periodo): 30, 7 and 1
 * day before a term ends and once when it has ended — in-app + push
 * (SendAthleteNotification, category Pedidos, honouring the user's
 * preferences) and an e-mail, which always goes out: like an order
 * receipt it is a service notice about something the user paid for, not
 * marketing. A term that was already renewed (a later term of the same
 * pack exists) gets none.
 *
 * Each stage is sent once: the ones already sent are kept on the
 * entitlement (`expiry_reminders`). If the command did not run for days,
 * only the closest stage is sent — never a burst.
 */
#[Signature('quotas:remind-expiring {--dry-run : Mostrar a quién se avisaría sin enviar nada}')]
#[Description('Avisa a los dueños de recuerdos cuyo plan por periodo está por vencer o ya venció.')]
class RemindExpiringPlans extends Command
{
    /** Days before the end at which a reminder goes out, closest first. */
    public const array STAGES = [1, 7, 30];

    /** A term that ended longer ago than this is no longer announced. */
    public const int EXPIRED_NOTICE_DAYS = 14;

    public function handle(SendAthleteNotification $send): int
    {
        $now = now();
        $dry = (bool) $this->option('dry-run');
        $sent = 0;

        MediaEntitlement::query()
            ->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)
            ->where('billing_period', '!=', BillingPeriod::OneTime->value)
            ->whereNull('revoked_at')
            ->whereNotNull('expires_at')
            ->whereNotNull('legacy_story_id')
            ->where('expires_at', '<=', $now->copy()->addDays(max(self::STAGES)))
            ->where('expires_at', '>=', $now->copy()->subDays(self::EXPIRED_NOTICE_DAYS))
            ->with(['user', 'legacyStory', 'photoExpansionPack'])
            ->orderBy('id')
            ->chunkById(200, function ($entitlements) use ($send, $now, $dry, &$sent) {
                foreach ($entitlements as $entitlement) {
                    /** @var MediaEntitlement $entitlement */
                    $stage = $this->dueStage($entitlement, $now);
                    $user = $entitlement->user;
                    $story = $entitlement->legacyStory;

                    if ($stage === null || $user === null || $story === null || $this->wasRenewed($entitlement)) {
                        continue;
                    }

                    $kind = $entitlement->extra_videos > 0 ? StoryQuotaKind::Videos : ($entitlement->extra_audio > 0 ? StoryQuotaKind::Audio : StoryQuotaKind::Photos);
                    $units = (int) $entitlement->getAttribute($kind->entitlementColumn());
                    $url = route('my-legacy.edit', $story->public_id);
                    $expired = $stage === 'expired';

                    // Written once, in the owner's language (not the worker's):
                    // the in-app row, the push and the e-mail share these texts.
                    [$headline, $body] = in_locale_of($user, function () use ($entitlement, $story, $kind, $units, $stage, $expired): array {
                        $values = [
                            'amount' => $kind->amount($units),
                            'title' => $story->title ?: __('tu recuerdo'),
                            'date' => $entitlement->expires_at?->isoFormat('LL'),
                        ];

                        return $expired
                            ? [
                                __('Tu plan venció'),
                                __('El plan de +:amount de «:title» terminó. Todo tu contenido sigue visible; para agregar más, renuévalo.', $values),
                            ]
                            : [
                                $stage === '1' ? __('Tu plan vence mañana') : __('Tu plan vence en :days días', ['days' => $stage]),
                                __('El plan de +:amount de «:title» vence el :date. Renuévalo para conservar ese espacio.', $values),
                            ];
                    });

                    $this->line(sprintf('  %s → %s (%s, +%d %s)', $stage, $user->email, $story->public_id, $units, $kind->value));
                    $sent++;

                    if ($dry) {
                        continue;
                    }

                    // Marked first: a failure while sending must not repeat it forever.
                    $entitlement->forceFill(['expiry_reminders' => $this->markSent($entitlement, $stage)])->save();

                    $send->handle(
                        $user,
                        $headline,
                        $body,
                        NotificationType::Custom,
                        $url,
                        ['kind' => 'plan_expiry', 'stage' => $stage, 'story' => $story->public_id, 'quota_kind' => $kind->value],
                        push: true,
                        category: NotificationCategory::Pedidos,
                    );

                    $user->notify(
                        (new PlanExpiryReminder($headline.' · Finisher Legacy', $headline, $body, $url, $expired))
                            ->locale(RecipientLocale::of($user)),
                    );
                }
            });

        $this->info(($dry ? '[simulación] ' : '')."Avisos de vencimiento: {$sent}.");

        return self::SUCCESS;
    }

    /** The stage to send now ('30' | '7' | '1' | 'expired'), or null. */
    private function dueStage(MediaEntitlement $entitlement, \DateTimeInterface $now): ?string
    {
        $done = $entitlement->expiry_reminders ?? [];

        if ($entitlement->isExpired($now)) {
            return in_array('expired', $done, true) ? null : 'expired';
        }

        $daysLeft = (int) ceil(now()->diffInSeconds($entitlement->expires_at) / 86400);

        foreach (self::STAGES as $stage) {
            if ($daysLeft <= $stage) {
                return in_array((string) $stage, $done, true) ? null : (string) $stage;
            }
        }

        return null;
    }

    /**
     * The stage sent plus every earlier (farther) one, so a late run never
     * sends "30 días" after "7 días".
     *
     * @return list<string>
     */
    private function markSent(MediaEntitlement $entitlement, string $stage): array
    {
        $done = $entitlement->expiry_reminders ?? [];
        $done[] = $stage;

        if ($stage !== 'expired') {
            foreach (self::STAGES as $other) {
                if ($other >= (int) $stage) {
                    $done[] = (string) $other;
                }
            }
        }

        return array_values(array_unique($done));
    }

    private function wasRenewed(MediaEntitlement $entitlement): bool
    {
        return MediaEntitlement::query()
            ->where('legacy_story_id', $entitlement->legacy_story_id)
            ->where('photo_expansion_pack_id', $entitlement->getAttribute('photo_expansion_pack_id'))
            ->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)
            ->whereNull('revoked_at')
            ->whereKeyNot($entitlement->id)
            // Its renewal, or a newer term of the same pack bought later.
            ->where('expires_at', '>', $entitlement->expires_at)
            ->exists();
    }
}
