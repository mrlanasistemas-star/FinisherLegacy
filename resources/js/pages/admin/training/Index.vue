<script setup lang="ts">
/**
 * Administración → Contenido → Entrenamientos: moderación de «Envíame
 * ánimo». Los mensajes son privados entre la familia y el atleta, así que
 * aquí solo hay conteos (enlaces, atletas, remitentes bloqueados, envíos
 * frenados por el límite) y los mensajes que el atleta REPORTÓ. El audio de
 * un mensaje reportado se pide uno por uno (URL firmada de 10 min, queda en
 * la auditoría). Acciones: desactivar enlace, resolver reporte, eliminar
 * los mensajes del remitente reportado.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    Ban,
    Check,
    Flag,
    Headphones,
    Link2,
    Power,
    RotateCcw,
    ShieldAlert,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';
import {
    disable as disableLink,
    enable as enableLink,
} from '@/routes/admin/training/links';
import {
    audio as reportAudio,
    purge as purgeReport,
    update as updateReport,
} from '@/routes/admin/training/reports';

type Tab = 'reportes' | 'enlaces' | 'atletas' | 'remitentes';

type Report = {
    uuid: string;
    reason: string;
    reason_label: string;
    details: string | null;
    status: string;
    open: boolean;
    resolution_notes: string | null;
    reviewed_at: string | null;
    created_at: string;
    reporter: string | null;
    link: { uuid: string; title: string; disabled: boolean } | null;
    sender_ref: string | null;
    message: {
        sender_name: string;
        relationship: string | null;
        body: string | null;
        icon: string;
        has_audio: boolean;
        duration_seconds: number;
        visible: boolean;
        created_at: string;
    } | null;
};

type LinkRow = {
    uuid: string;
    title: string;
    owner: string | null;
    state: string;
    messages_count: number;
    messages_24h: number;
    blocks_count: number;
    reports_count: number;
    rate_hits_7d: number;
    signals: string[];
    created_at: string | null;
};

const props = defineProps<{
    stats: {
        links: number;
        active_links: number;
        disabled_links: number;
        messages: number;
        messages_24h: number;
        blocks: number;
        rate_hits_7d: number;
        open_reports: number;
    };
    reports: Report[];
    links: LinkRow[];
    athletes: {
        name: string | null;
        links: number;
        messages: number;
        blocks: number;
        reports: number;
    }[];
    senders: {
        ref: string;
        blocks: number;
        athletes: number;
        reports: number;
        messages: number;
        rate_hits_7d: number;
    }[];
    thresholds: { blocks: number; rate_hits_7d: number; messages_24h: number };
}>();

const tab = ref<Tab>('reportes');

const tabs = computed(() => [
    {
        key: 'reportes' as Tab,
        label: 'Reportes',
        count: props.stats.open_reports,
        icon: Flag,
    },
    {
        key: 'enlaces' as Tab,
        label: 'Enlaces',
        count: props.links.filter((l) => l.signals.length > 0).length,
        icon: Link2,
    },
    {
        key: 'atletas' as Tab,
        label: 'Atletas',
        count: 0,
        icon: Users,
    },
    {
        key: 'remitentes' as Tab,
        label: 'Remitentes bloqueados',
        count: props.senders.length,
        icon: Ban,
    },
]);

const stateLabels: Record<string, string> = {
    active: 'Activo',
    revoked: 'Desactivado por el atleta',
    expired: 'Vencido',
    full: 'Lleno',
    disabled: 'Desactivado por moderación',
};

const statusLabels: Record<string, string> = {
    open: 'Abierto',
    reviewing: 'En revisión',
    resolved: 'Resuelto',
    dismissed: 'Descartado',
};

const options = { preserveScroll: true, preserveState: true };

// ── Audio of a reported message (one signed URL per request, audited) ──
const audioFor = ref<{ report: string; url: string } | null>(null);

function requestAudio(report: Report) {
    router.post(
        reportAudio.url(report.uuid),
        {},
        {
            ...options,
            onFlash: (flash) => {
                const data = (flash as Record<string, unknown>)
                    .cheer_report_audio as
                    { report: string; url: string } | undefined;

                if (data) {
                    audioFor.value = data;
                }
            },
        },
    );
}

function resolve(report: Report, status: 'resolved' | 'dismissed') {
    router.patch(
        updateReport.url(report.uuid),
        {
            status,
            restore_message: status === 'dismissed',
        },
        options,
    );
}

async function purge(report: Report) {
    const ok = await confirmAction({
        title: '¿Eliminar los mensajes de este remitente?',
        text: 'Se borran (con su audio) todos los mensajes que este remitente dejó a este atleta y queda bloqueado en el enlace. Es permanente.',
        confirmButtonText: 'Eliminar mensajes',
    });

    if (ok) {
        router.post(purgeReport.url(report.uuid), {}, options);
    }
}

async function toggleLink(link: { uuid: string; disabled: boolean }) {
    if (link.disabled) {
        router.post(enableLink.url(link.uuid), {}, options);

        return;
    }

    const ok = await confirmAction({
        title: '¿Desactivar este enlace?',
        text: 'Dejará de recibir mensajes de inmediato. Los mensajes recibidos se conservan para el atleta.',
        confirmButtonText: 'Desactivar',
    });

    if (ok) {
        router.post(disableLink.url(link.uuid), {}, options);
    }
}

function formatSeconds(total: number): string {
    const m = Math.floor(total / 60);
    const s = total % 60;

    return `${m}:${String(s).padStart(2, '0')}`;
}
</script>

<template>
    <Head title="Entrenamientos · Moderación" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-semibold">
                    <ShieldAlert class="size-5 text-fl-gold-ink" />
                    Entrenamientos
                </h1>
                <p class="mt-1 max-w-2xl text-xs text-muted-foreground">
                    Los mensajes de ánimo son privados entre la familia y el
                    atleta. Aquí solo ves conteos; el contenido de un mensaje
                    aparece únicamente cuando el atleta lo reporta.
                </p>
            </div>
            <p class="legacy-numeric text-xs text-muted-foreground">
                {{ stats.active_links }} enlaces activos de {{ stats.links }} ·
                {{ stats.messages }} mensajes ({{ stats.messages_24h }} en 24 h)
                · {{ stats.blocks }} bloqueos · {{ stats.rate_hits_7d }} envíos
                frenados en 7 días
            </p>
        </div>

        <nav
            class="flex gap-1 overflow-x-auto border-b border-border"
            aria-label="Moderación de entrenamientos"
        >
            <button
                v-for="item in tabs"
                :key="item.key"
                type="button"
                class="-mb-px inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-sm font-medium"
                :class="
                    tab === item.key
                        ? 'border-foreground text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                :aria-current="tab === item.key ? 'page' : undefined"
                @click="tab = item.key"
            >
                <component :is="item.icon" class="size-4" />
                {{ item.label }}
                <span
                    v-if="item.count"
                    class="legacy-numeric rounded-full px-1.5 text-[11px]"
                    :class="
                        item.key === 'reportes'
                            ? 'bg-red-100 text-red-800'
                            : 'bg-muted text-muted-foreground'
                    "
                    >{{ item.count }}</span
                >
            </button>
        </nav>

        <!-- Reportes -->
        <section v-if="tab === 'reportes'" class="mt-5">
            <div class="fl-card divide-y divide-border">
                <article
                    v-for="report in reports"
                    :key="report.uuid"
                    class="grid gap-3 p-4 md:grid-cols-[1fr_auto] md:items-start"
                >
                    <div class="min-w-0 space-y-1.5">
                        <p class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-medium">{{
                                report.reason_label
                            }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px]"
                                :class="
                                    report.open
                                        ? 'bg-red-100 text-red-800'
                                        : 'bg-muted text-muted-foreground'
                                "
                                >{{
                                    statusLabels[report.status] ?? report.status
                                }}</span
                            >
                            <span class="text-xs text-muted-foreground"
                                >· Reportó {{ report.reporter ?? 'Atleta' }} ·
                                {{ shortDate(report.created_at) }}</span
                            >
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Enlace «{{ report.link?.title ?? 'eliminado' }}»
                            <template v-if="report.sender_ref">
                                · Remitente
                                <code class="font-mono">{{
                                    report.sender_ref
                                }}</code>
                            </template>
                        </p>
                        <div
                            v-if="report.message"
                            class="rounded-lg border border-border bg-muted/40 p-3 text-sm"
                        >
                            <p class="font-medium">
                                {{ report.message.sender_name }}
                                <span
                                    v-if="report.message.relationship"
                                    class="font-normal text-muted-foreground"
                                    >· {{ report.message.relationship }}</span
                                >
                            </p>
                            <p
                                v-if="report.message.body"
                                class="mt-1 whitespace-pre-line"
                            >
                                {{ report.message.body }}
                            </p>
                            <p
                                v-if="report.message.has_audio"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Audio de
                                {{
                                    formatSeconds(
                                        report.message.duration_seconds,
                                    )
                                }}
                            </p>
                            <audio
                                v-if="audioFor?.report === report.uuid"
                                :src="audioFor.url"
                                controls
                                autoplay
                                class="mt-2 w-full"
                            />
                        </div>
                        <p v-else class="text-xs text-muted-foreground">
                            Mensaje eliminado.
                        </p>
                        <p
                            v-if="report.details"
                            class="text-xs text-muted-foreground"
                        >
                            Detalle del atleta: {{ report.details }}
                        </p>
                        <p
                            v-if="report.resolution_notes"
                            class="text-xs text-muted-foreground"
                        >
                            Nota: {{ report.resolution_notes }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <Button
                            v-if="report.message?.has_audio"
                            size="sm"
                            variant="outline"
                            @click="requestAudio(report)"
                        >
                            <Headphones class="size-3.5" /> Escuchar audio
                        </Button>
                        <Button
                            v-if="report.open"
                            size="sm"
                            variant="outline"
                            @click="resolve(report, 'resolved')"
                        >
                            <Check class="size-3.5" /> Resolver
                        </Button>
                        <Button
                            v-if="report.open"
                            size="sm"
                            variant="outline"
                            title="Descarta el reporte y devuelve el mensaje a la lista del atleta"
                            @click="resolve(report, 'dismissed')"
                        >
                            <X class="size-3.5" /> Descartar
                        </Button>
                        <Button
                            v-if="report.link"
                            size="sm"
                            variant="outline"
                            @click="
                                toggleLink({
                                    uuid: report.link.uuid,
                                    disabled: report.link.disabled,
                                })
                            "
                        >
                            <component
                                :is="report.link.disabled ? RotateCcw : Power"
                                class="size-3.5"
                            />
                            {{
                                report.link.disabled
                                    ? 'Reactivar enlace'
                                    : 'Desactivar enlace'
                            }}
                        </Button>
                        <Button
                            v-if="report.message"
                            size="sm"
                            variant="outline"
                            class="text-red-700"
                            @click="purge(report)"
                        >
                            <Trash2 class="size-3.5" /> Eliminar mensajes del
                            remitente
                        </Button>
                    </div>
                </article>
                <p
                    v-if="!reports.length"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    No hay mensajes reportados.
                </p>
            </div>
        </section>

        <!-- Enlaces -->
        <section v-else-if="tab === 'enlaces'" class="mt-5">
            <p class="mb-3 text-xs text-muted-foreground">
                Señales de abuso: algún reporte, {{ thresholds.blocks }}+
                bloqueos, {{ thresholds.rate_hits_7d }}+ envíos frenados en 7
                días o {{ thresholds.messages_24h }}+ mensajes en 24 h.
            </p>
            <div class="fl-card overflow-x-auto">
                <table class="w-full min-w-[720px] text-sm">
                    <thead
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="p-3 font-medium">Enlace</th>
                            <th class="p-3 font-medium">Mensajes</th>
                            <th class="p-3 font-medium">24 h</th>
                            <th class="p-3 font-medium">Bloqueos</th>
                            <th class="p-3 font-medium">Reportes</th>
                            <th class="p-3 font-medium">Frenados 7 d</th>
                            <th class="p-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="link in links" :key="link.uuid">
                            <td class="p-3">
                                <p class="font-medium">{{ link.title }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ link.owner ?? 'Atleta' }} ·
                                    {{ stateLabels[link.state] ?? link.state }}
                                </p>
                                <p
                                    v-if="link.signals.length"
                                    class="mt-1 flex flex-wrap gap-1"
                                >
                                    <span
                                        v-for="signal in link.signals"
                                        :key="signal"
                                        class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] text-amber-900"
                                        >{{ signal }}</span
                                    >
                                </p>
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ link.messages_count }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ link.messages_24h }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ link.blocks_count }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ link.reports_count }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ link.rate_hits_7d }}
                            </td>
                            <td class="p-3 text-right">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="
                                        toggleLink({
                                            uuid: link.uuid,
                                            disabled: link.state === 'disabled',
                                        })
                                    "
                                >
                                    {{
                                        link.state === 'disabled'
                                            ? 'Reactivar'
                                            : 'Desactivar'
                                    }}
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!links.length"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    Todavía no hay enlaces de ánimo.
                </p>
            </div>
        </section>

        <!-- Atletas -->
        <section v-else-if="tab === 'atletas'" class="mt-5">
            <div class="fl-card overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="p-3 font-medium">Atleta</th>
                            <th class="p-3 font-medium">Enlaces</th>
                            <th class="p-3 font-medium">Mensajes</th>
                            <th class="p-3 font-medium">Bloqueos</th>
                            <th class="p-3 font-medium">Reportes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="(athlete, i) in athletes" :key="i">
                            <td class="p-3 font-medium">
                                {{ athlete.name ?? 'Cuenta eliminada' }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ athlete.links }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ athlete.messages }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ athlete.blocks }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ athlete.reports }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!athletes.length"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    Todavía no hay mensajes de ánimo.
                </p>
            </div>
        </section>

        <!-- Remitentes -->
        <section v-else class="mt-5">
            <p class="mb-3 text-xs text-muted-foreground">
                Los remitentes no tienen cuenta: se identifican con una
                referencia corta derivada de su conexión (nunca la IP).
            </p>
            <div class="fl-card overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <tr>
                            <th class="p-3 font-medium">Referencia</th>
                            <th class="p-3 font-medium">Bloqueos</th>
                            <th class="p-3 font-medium">Atletas</th>
                            <th class="p-3 font-medium">Reportes</th>
                            <th class="p-3 font-medium">Mensajes</th>
                            <th class="p-3 font-medium">Frenados 7 d</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="sender in senders" :key="sender.ref">
                            <td class="p-3 font-mono text-xs">
                                {{ sender.ref }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ sender.blocks }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ sender.athletes }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ sender.reports }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ sender.messages }}
                            </td>
                            <td class="legacy-numeric p-3">
                                {{ sender.rate_hits_7d }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p
                    v-if="!senders.length"
                    class="p-6 text-center text-sm text-muted-foreground"
                >
                    Nadie ha bloqueado ni reportado remitentes.
                </p>
            </div>
        </section>
    </div>
</template>
