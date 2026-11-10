<script setup lang="ts">
/**
 * Notificaciones → Campañas push → detail: status, delivery numbers
 * (audience frozen at send time, devices queued, notification-center
 * copies, skipped by preference, failures, opens reported by the app),
 * the content as it was sent, and the campaign's own audit trail. Polls
 * while the campaign is sending. docs/renovacion/campanas-push.md
 */
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Copy,
    History,
    Pencil,
    Undo2,
    XCircle,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { vReveal } from '@/composables/useReveal';
import { confirmAction } from '@/lib/swal';
import type { CampaignContent, CampaignRow } from './partials/campaign';
import { mxDateTime, number, percent, statusChip } from './partials/campaign';
import PhonePreview from './partials/PhonePreview.vue';

const props = defineProps<{
    campaign: CampaignRow &
        CampaignContent & {
            sender: string | null;
            duplicated_from: { uuid: string; title: string } | null;
            audience_summary: string;
        };
    breakdown: {
        pending: number;
        sent: number;
        skipped: number;
        failed: number;
    };
    audit: {
        id: number;
        label: string;
        by: string | null;
        at: string | null;
    }[];
}>();

const base = '/admin/notificaciones/campanas';
const c = computed(() => props.campaign);

const { start, stop } = usePoll(
    3000,
    { only: ['campaign', 'breakdown', 'audit'] },
    { autoStart: false },
);
watch(
    () => c.value.status,
    (status) =>
        status === 'sending' || (status === 'scheduled' && isDue())
            ? start()
            : stop(),
    { immediate: true },
);

function isDue() {
    return (
        !!c.value.scheduled_at &&
        new Date(c.value.scheduled_at).getTime() <= Date.now()
    );
}

const progress = computed(() =>
    c.value.batches_total > 0
        ? Math.min(
              100,
              Math.round((c.value.batches_done / c.value.batches_total) * 100),
          )
        : 0,
);

const numbers = computed(() => [
    {
        label: 'Audiencia',
        value: number(c.value.audience_users_count),
        hint: 'personas al iniciar el envío',
    },
    {
        label: 'Push en cola',
        value: number(c.value.sent_count),
        hint: `de ${number(c.value.audience_devices_count)} dispositivos`,
    },
    {
        label: 'Entregadas',
        value: number(c.value.delivered_count),
        hint:
            c.value.awaiting_receipt_count > 0
                ? `${number(c.value.awaiting_receipt_count)} esperando acuse`
                : 'confirmadas por el proveedor',
    },
    {
        label: 'No entregadas',
        value: number(c.value.delivery_failed_count),
        hint: 'rechazadas o con acuse de error',
        danger: c.value.delivery_failed_count > 0,
    },
    {
        label: 'Centro de notificaciones',
        value: c.value.save_to_inbox ? number(c.value.inbox_count) : 'No',
        hint: 'copias guardadas',
    },
    {
        label: 'Omitidas',
        value: number(c.value.skipped_count),
        hint: 'cambiaron sus preferencias',
    },
    {
        label: 'Sin procesar',
        value: number(c.value.failed_count),
        hint: 'personas en lotes con error',
        danger: c.value.failed_count > 0,
    },
    {
        label: 'Abiertas',
        value: number(c.value.opened_count),
        hint:
            percent(c.value.opened_count, c.value.audience_users_count) +
            ' de la audiencia',
    },
]);

async function cancel() {
    if (
        await confirmAction({
            title: '¿Cancelar esta campaña?',
            text: 'No se enviará. Puedes duplicarla después si la necesitas.',
            confirmButtonText: 'Cancelar campaña',
        })
    ) {
        router.post(`${base}/${c.value.uuid}/cancelar`);
    }
}
</script>

<template>
    <Head :title="`Campaña: ${campaign.title}`" />

    <div class="w-full space-y-6 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div
            v-reveal
            class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex min-w-0 items-center gap-3">
                <Link
                    :href="base"
                    class="fl-press flex size-10 shrink-0 items-center justify-center rounded-full border bg-white text-muted-foreground hover:text-foreground"
                    aria-label="Volver a campañas"
                    ><ArrowLeft class="size-4"
                /></Link>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate text-xl font-semibold">
                            {{ campaign.title }}
                        </h1>
                        <span
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="statusChip[campaign.status]"
                            >{{ campaign.status_label }}</span
                        >
                    </div>
                    <p class="truncate text-sm text-muted-foreground">
                        {{ campaign.name ?? campaign.audience_summary }}
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <template v-if="campaign.editable">
                    <Button as-child variant="outline" class="rounded-full">
                        <Link :href="`${base}/${campaign.uuid}/editar`"
                            ><Pencil class="size-4" /> Editar</Link
                        >
                    </Button>
                    <Button
                        v-if="campaign.status === 'scheduled'"
                        variant="outline"
                        class="rounded-full"
                        @click="
                            router.post(`${base}/${campaign.uuid}/borrador`)
                        "
                        ><Undo2 class="size-4" /> Regresar a borrador</Button
                    >
                    <Button
                        variant="outline"
                        class="rounded-full text-rose-700"
                        @click="cancel"
                        ><XCircle class="size-4" /> Cancelar</Button
                    >
                </template>
                <Button
                    class="fl-press rounded-full"
                    @click="router.post(`${base}/${campaign.uuid}/duplicar`)"
                    ><Copy class="size-4" /> Duplicar</Button
                >
            </div>
        </div>

        <div
            v-if="campaign.status === 'sending'"
            class="fl-card space-y-2 p-5"
            aria-live="polite"
        >
            <div class="flex items-center justify-between text-sm">
                <span class="font-medium">Enviando…</span>
                <span class="text-muted-foreground tabular-nums"
                    >{{ campaign.batches_done }}/{{
                        campaign.batches_total
                    }}
                    lotes</span
                >
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-[var(--fl-paper)]">
                <div
                    class="h-full rounded-full bg-[var(--fl-gold)] transition-all duration-500"
                    :style="{ width: `${progress}%` }"
                />
            </div>
        </div>

        <div
            v-if="campaign.last_error"
            class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
            <p>{{ campaign.last_error }}</p>
        </div>

        <div
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)] xl:grid-cols-[minmax(0,1fr)_minmax(0,620px)]"
        >
            <div class="min-w-0 space-y-6">
                <div
                    v-if="campaign.started_at"
                    class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <div
                        v-for="(item, i) in numbers"
                        :key="item.label"
                        v-reveal="{ index: i }"
                        class="fl-card fl-card-hover p-4"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ item.label }}
                        </p>
                        <p
                            class="mt-1 text-2xl font-semibold tabular-nums"
                            :class="item.danger ? 'text-rose-700' : ''"
                        >
                            {{ item.value }}
                        </p>
                        <p class="text-[11px] text-muted-foreground">
                            {{ item.hint }}
                        </p>
                    </div>
                </div>

                <section v-reveal class="fl-card p-5">
                    <h2 class="mb-4 font-semibold">Detalles</h2>
                    <dl
                        class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[10rem_1fr]"
                    >
                        <dt class="text-muted-foreground">Audiencia</dt>
                        <dd>{{ campaign.audience_summary }}</dd>
                        <dt class="text-muted-foreground">Tipo de aviso</dt>
                        <dd>{{ campaign.category_label }}</dd>
                        <dt class="text-muted-foreground">Abre</dt>
                        <dd class="break-all">
                            {{ campaign.link_label ?? '—' }}
                        </dd>
                        <dt class="text-muted-foreground">Canal Android</dt>
                        <dd>{{ campaign.channel }}</dd>
                        <dt class="text-muted-foreground">Programada para</dt>
                        <dd>{{ mxDateTime(campaign.scheduled_at) }}</dd>
                        <dt class="text-muted-foreground">Inició / terminó</dt>
                        <dd>
                            {{ mxDateTime(campaign.started_at) }} ·
                            {{ mxDateTime(campaign.completed_at) }}
                        </dd>
                        <template v-if="campaign.cancelled_at">
                            <dt class="text-muted-foreground">Cancelada</dt>
                            <dd>{{ mxDateTime(campaign.cancelled_at) }}</dd>
                        </template>
                        <dt class="text-muted-foreground">Creada por</dt>
                        <dd>{{ campaign.creator ?? '—' }}</dd>
                        <dt class="text-muted-foreground">Enviada por</dt>
                        <dd>{{ campaign.sender ?? '—' }}</dd>
                        <template v-if="campaign.duplicated_from">
                            <dt class="text-muted-foreground">Copia de</dt>
                            <dd>
                                <Link
                                    :href="`${base}/${campaign.duplicated_from.uuid}`"
                                    class="fl-link"
                                    >{{ campaign.duplicated_from.title }}</Link
                                >
                            </dd>
                        </template>
                    </dl>
                    <p
                        v-if="campaign.started_at"
                        class="mt-4 rounded-xl bg-[var(--fl-paper)] p-3 text-xs text-muted-foreground"
                    >
                        Destinatarios: {{ number(breakdown.sent) }} procesados ·
                        {{ number(breakdown.skipped) }} omitidos ·
                        {{ number(breakdown.failed) }} con error ·
                        {{ number(breakdown.pending) }} pendientes. «Push en
                        cola» cuenta los mensajes entregados al proveedor;
                        «Entregadas» y «No entregadas» salen de sus acuses por
                        dispositivo, que llegan unos 15 minutos después del
                        envío.
                    </p>
                </section>

                <section v-reveal class="fl-card p-5">
                    <h2 class="mb-4 flex items-center gap-2 font-semibold">
                        <History class="size-4 text-[var(--fl-gold-dim)]" />
                        Bitácora
                    </h2>
                    <ol class="space-y-3">
                        <li
                            v-for="entry in audit"
                            :key="entry.id"
                            class="flex gap-3 text-sm"
                        >
                            <span
                                class="mt-1.5 size-2 shrink-0 rounded-full bg-[var(--fl-gold)]"
                            />
                            <div>
                                <p>
                                    <span class="font-medium">{{
                                        entry.label
                                    }}</span>
                                    <span
                                        v-if="entry.by"
                                        class="text-muted-foreground"
                                    >
                                        · {{ entry.by }}</span
                                    >
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ mxDateTime(entry.at) }}
                                </p>
                            </div>
                        </li>
                        <li
                            v-if="!audit.length"
                            class="text-sm text-muted-foreground"
                        >
                            Sin movimientos registrados.
                        </li>
                    </ol>
                </section>
            </div>

            <aside class="min-w-0 lg:sticky lg:top-20 lg:self-start">
                <div v-reveal class="fl-card p-4 md:p-5">
                    <PhonePreview
                        :title="campaign.title"
                        :body="campaign.body"
                        :image-url="campaign.image_url"
                        :include-logo="campaign.include_logo"
                        logo-url="/images/brand/logo/fl-monograma.png"
                    />
                </div>
            </aside>
        </div>
    </div>
</template>
