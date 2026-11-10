<script setup lang="ts">
/**
 * Notificaciones → Campañas push: history of push campaigns with status
 * (borrador, programada, enviando, enviada, cancelada, con errores),
 * audience size, devices queued / failed, opens reported by the app, and
 * quick actions (open, duplicate, cancel). docs/renovacion/campanas-push.md
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BellRing,
    CalendarClock,
    Copy,
    Eye,
    Info,
    MailOpen,
    Plus,
    Send,
    Users,
    XCircle,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { vReveal } from '@/composables/useReveal';
import { confirmAction } from '@/lib/swal';
import type { CampaignRow } from './partials/campaign';
import { number, percent, rowMoment, statusChip } from './partials/campaign';

defineProps<{
    campaigns: {
        data: CampaignRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: { status: string | null };
    statuses: Record<string, string>;
    stats: {
        sent_30d: number;
        reached_30d: number;
        opened_30d: number;
        scheduled: number;
    };
    pushConfigured: boolean;
    allLimitPerHour: number;
}>();

const base = '/admin/notificaciones/campanas';

function filter(status: string | null) {
    router.get(base, status ? { status } : {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

function duplicate(row: CampaignRow) {
    router.post(`${base}/${row.uuid}/duplicar`);
}

async function cancel(row: CampaignRow) {
    if (
        await confirmAction({
            title: '¿Cancelar esta campaña?',
            text: 'No se enviará. Puedes duplicarla después si la necesitas.',
            confirmButtonText: 'Cancelar campaña',
        })
    ) {
        router.post(
            `${base}/${row.uuid}/cancelar`,
            {},
            { preserveScroll: true },
        );
    }
}

const statCards = [
    {
        key: 'sent_30d',
        label: 'Campañas enviadas',
        hint: 'últimos 30 días',
        icon: Send,
    },
    {
        key: 'reached_30d',
        label: 'Personas alcanzadas',
        hint: 'últimos 30 días',
        icon: Users,
    },
    {
        key: 'opened_30d',
        label: 'Aperturas',
        hint: 'reportadas por la app',
        icon: MailOpen,
    },
    {
        key: 'scheduled',
        label: 'Programadas',
        hint: 'pendientes de envío',
        icon: CalendarClock,
    },
] as const;

const pageLabel = (label: string) =>
    label
        .replace('&laquo; Previous', '‹')
        .replace('Next &raquo;', '›')
        .replace(/&[a-z]+;/g, '');
</script>

<template>
    <Head title="Campañas push" />

    <div class="w-full space-y-8 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div
            v-reveal
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-[var(--fl-cream)] text-[var(--fl-gold-ink)]"
                    ><BellRing class="size-5"
                /></span>
                <div>
                    <h1 class="text-xl font-semibold">Campañas push</h1>
                    <p class="text-sm text-muted-foreground">
                        Notificaciones a los teléfonos de la comunidad. Siempre
                        se respetan sus preferencias.
                    </p>
                </div>
            </div>
            <Button as-child class="fl-press rounded-full">
                <Link :href="`${base}/crear`"
                    ><Plus class="size-4" /> Nueva campaña</Link
                >
            </Button>
        </div>

        <div
            v-if="!pushConfigured"
            class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            <Info class="mt-0.5 size-4 shrink-0" />
            <p>
                No hay un proveedor de push configurado todavía: las campañas se
                guardan en el centro de notificaciones, pero no llegan al
                teléfono.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="(card, i) in statCards"
                :key="card.key"
                v-reveal="{ index: i }"
                class="fl-card fl-card-hover flex items-start gap-3 p-5"
            >
                <span
                    class="flex size-9 items-center justify-center rounded-lg bg-[var(--fl-paper)] text-[var(--fl-gold-dim)]"
                    ><component :is="card.icon" class="size-4"
                /></span>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ card.label }}
                    </p>
                    <p class="text-2xl font-semibold tabular-nums">
                        {{ number(stats[card.key]) }}
                    </p>
                    <p class="text-[11px] text-muted-foreground">
                        {{ card.hint }}
                    </p>
                </div>
            </div>
        </div>

        <section class="space-y-4">
            <div
                class="flex flex-wrap gap-2"
                role="tablist"
                aria-label="Filtrar por estado"
            >
                <button
                    type="button"
                    role="tab"
                    :aria-selected="!filters.status"
                    class="fl-press rounded-full border px-3 py-1.5 text-xs transition"
                    :class="
                        !filters.status
                            ? 'border-[var(--fl-black)] bg-[var(--fl-black)] text-white'
                            : 'bg-white text-muted-foreground hover:text-foreground'
                    "
                    @click="filter(null)"
                >
                    Todas
                </button>
                <button
                    v-for="(label, value) in statuses"
                    :key="value"
                    type="button"
                    role="tab"
                    :aria-selected="filters.status === value"
                    class="fl-press rounded-full border px-3 py-1.5 text-xs transition"
                    :class="
                        filters.status === value
                            ? 'border-[var(--fl-black)] bg-[var(--fl-black)] text-white'
                            : 'bg-white text-muted-foreground hover:text-foreground'
                    "
                    @click="filter(String(value))"
                >
                    {{ label }}
                </button>
            </div>

            <div class="fl-card overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead
                        class="border-b text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">Campaña</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                            <th class="px-4 py-3 font-medium">Audiencia</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Alcance
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                En cola
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Entregadas / fallidas
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Abiertas
                            </th>
                            <th class="px-4 py-3 font-medium">Fecha</th>
                            <th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, i) in campaigns.data"
                            :key="row.id"
                            v-reveal="{ index: i }"
                            class="border-b transition-colors last:border-0 hover:bg-[var(--fl-paper)]"
                        >
                            <td class="max-w-72 px-4 py-3">
                                <Link
                                    :href="
                                        row.editable
                                            ? `${base}/${row.uuid}/editar`
                                            : `${base}/${row.uuid}`
                                    "
                                    class="fl-link block truncate font-medium"
                                    >{{ row.title }}</Link
                                >
                                <p
                                    v-if="row.name"
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ row.name }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="statusChip[row.status]"
                                    >{{ row.status_label }}</span
                                >
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ row.audience_label }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <template v-if="row.started_at">
                                    {{ number(row.audience_users_count) }}
                                    <span
                                        class="block text-[11px] text-muted-foreground"
                                        >{{
                                            number(row.audience_devices_count)
                                        }}
                                        disp.</span
                                    >
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <template v-if="row.started_at">
                                    {{ number(row.sent_count) }}
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <template v-if="row.started_at">
                                    <span class="text-emerald-700">{{
                                        number(row.delivered_count)
                                    }}</span>
                                    <span
                                        :class="
                                            row.delivery_failed_count +
                                            row.failed_count
                                                ? 'text-rose-700'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        /
                                        {{
                                            number(
                                                row.delivery_failed_count +
                                                    row.failed_count,
                                            )
                                        }}</span
                                    >
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                <template v-if="row.started_at">
                                    {{ number(row.opened_count) }}
                                    <span
                                        class="block text-[11px] text-muted-foreground"
                                        >{{
                                            percent(
                                                row.opened_count,
                                                row.audience_users_count,
                                            )
                                        }}</span
                                    >
                                </template>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span
                                    class="block text-[11px] text-muted-foreground"
                                    >{{ rowMoment(row).label }}</span
                                >
                                {{ rowMoment(row).at }}
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Button
                                    as-child
                                    size="sm"
                                    variant="ghost"
                                    class="rounded-full"
                                >
                                    <Link
                                        :href="`${base}/${row.uuid}`"
                                        :aria-label="`Ver ${row.title}`"
                                        ><Eye class="size-3.5"
                                    /></Link>
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="rounded-full"
                                    :aria-label="`Duplicar ${row.title}`"
                                    title="Duplicar"
                                    @click="duplicate(row)"
                                >
                                    <Copy class="size-3.5" />
                                </Button>
                                <Button
                                    v-if="row.editable"
                                    size="sm"
                                    variant="ghost"
                                    class="rounded-full text-rose-700"
                                    :aria-label="`Cancelar ${row.title}`"
                                    title="Cancelar"
                                    @click="cancel(row)"
                                >
                                    <XCircle class="size-3.5" />
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!campaigns.data.length">
                            <td colspan="9" class="px-4 py-12 text-center">
                                <BellRing
                                    class="mx-auto mb-2 size-6 text-[var(--fl-gold)]"
                                />
                                <p class="font-medium">
                                    Todavía no hay campañas{{
                                        filters.status ? ' con ese estado' : ''
                                    }}.
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    Crea la primera y revísala en la vista
                                    previa antes de enviarla.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="campaigns.links.length > 3"
                class="flex flex-wrap justify-center gap-1"
                aria-label="Paginación"
            >
                <template v-for="(link, i) in campaigns.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="min-w-9 rounded-full border px-3 py-1.5 text-center text-xs"
                        :class="
                            link.active
                                ? 'border-[var(--fl-black)] bg-[var(--fl-black)] text-white'
                                : 'bg-white hover:bg-[var(--fl-paper)]'
                        "
                        >{{ pageLabel(link.label) }}</Link
                    >
                    <span
                        v-else
                        class="min-w-9 px-3 py-1.5 text-center text-xs text-muted-foreground"
                        >{{ pageLabel(link.label) }}</span
                    >
                </template>
            </nav>

            <p class="text-xs text-muted-foreground">
                «En cola» son los mensajes entregados al proveedor de push para
                cada dispositivo; «Entregadas / fallidas» salen de los acuses
                del proveedor (llegan unos 15 minutos después del envío);
                «Abiertas» las reporta la app al tocar la notificación. Límite:
                {{
                    allLimitPerHour === 1
                        ? 'una campaña'
                        : `${allLimitPerHour} campañas`
                }}
                a todos por hora.
            </p>
        </section>
    </div>
</template>
