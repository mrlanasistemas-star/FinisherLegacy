<script setup lang="ts">
/**
 * Header + tab bar of the admin event workspace (spec §9): Resumen,
 * Participantes, Resultados, Prerregistros, Datos, Tracking, Media. Every
 * tab is its own URL under /admin/events/{id}, so it can be shared,
 * bookmarked and opened in a new tab. Tabs follow the viewer's permissions
 * (the routes enforce them again server-side).
 */
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, ExternalLink, MapPin } from '@lucide/vue';
import { computed } from 'vue';
import type {
    EventWorkspaceData,
    EventWorkspaceTabKey,
} from '@/components/admin/events/types';
import { Badge } from '@/components/ui/badge';
import {
    eventEditionPhase,
    statusClass,
    statusLabel,
} from '@/lib/statusLabels';

const props = defineProps<{
    workspace: EventWorkspaceData;
    active: EventWorkspaceTabKey;
}>();

const page = usePage();
const permissions = computed(() => new Set(page.props.auth?.permissions ?? []));

const tabs = computed(() => {
    const base = props.workspace.base_url;
    const all: {
        key: EventWorkspaceTabKey;
        label: string;
        href: string;
        permission: string;
        count?: number;
    }[] = [
        {
            key: 'summary',
            label: 'Resumen',
            href: base,
            permission: 'events.view',
        },
        {
            key: 'participants',
            label: 'Participantes',
            href: `${base}/participants`,
            permission: 'participants.view',
            count: props.workspace.counts.participants,
        },
        {
            key: 'results',
            label: 'Resultados',
            href: `${base}/results`,
            permission: 'participants.view',
            count: props.workspace.counts.results,
        },
        {
            key: 'preregistrations',
            label: 'Prerregistros',
            href: `${base}/preregistrations`,
            permission: 'preregistrations.view',
            count: props.workspace.counts.preregistrations,
        },
        {
            key: 'data',
            label: 'Datos',
            href: `${base}/data`,
            permission: 'events.view',
        },
        {
            key: 'tracking',
            label: 'Tracking',
            // cross-agent: page built by A8 (admin event Tracking).
            href: `${base}/tracking`,
            permission: 'events.view',
        },
        {
            key: 'media',
            label: 'Media',
            href: `${base}/media`,
            permission: 'photos.manage',
            count: props.workspace.counts.media,
        },
    ];

    return all.filter((tab) => permissions.value.has(tab.permission));
});

const dateLabel = computed(() =>
    new Date(`${props.workspace.event_date}T12:00:00`).toLocaleDateString(
        'es-MX',
        { day: 'numeric', month: 'long', year: 'numeric' },
    ),
);
</script>

<template>
    <header class="mb-6">
        <Link
            href="/admin/events"
            class="mb-3 inline-flex min-h-8 items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-3.5" />
            Eventos
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="fl-eyebrow text-fl-gold-ink">
                    {{ workspace.organizer ?? 'Sin organizador' }}
                </p>
                <h1
                    class="mt-1 font-serif text-2xl leading-tight font-semibold text-foreground md:text-3xl"
                >
                    {{ workspace.name }}
                </h1>
                <p
                    class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground"
                >
                    <span>{{ workspace.edition }}</span>
                    <span class="inline-flex items-center gap-1.5"
                        ><CalendarDays class="size-3.5" />{{ dateLabel }}</span
                    >
                    <span class="inline-flex items-center gap-1.5"
                        ><MapPin class="size-3.5" />{{ workspace.city
                        }}<template v-if="workspace.state"
                            >, {{ workspace.state }}</template
                        ></span
                    >
                </p>
            </div>
            <div class="flex items-center gap-2">
                <Badge
                    variant="outline"
                    :class="statusClass(eventEditionPhase, workspace.phase)"
                >
                    {{ statusLabel(eventEditionPhase, workspace.phase) }}
                </Badge>
                <a
                    :href="`/events/${workspace.slug}`"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-border bg-card px-3 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground"
                >
                    Página pública
                    <ExternalLink class="size-3" />
                </a>
            </div>
        </div>

        <nav
            aria-label="Secciones del evento"
            class="mt-5 border-b border-border"
        >
            <div
                class="-mb-px flex [scrollbar-width:none] gap-1 overflow-x-auto [&::-webkit-scrollbar]:hidden"
            >
                <Link
                    v-for="tab in tabs"
                    :key="tab.key"
                    :href="tab.href"
                    :aria-current="tab.key === active ? 'page' : undefined"
                    class="inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-sm font-medium whitespace-nowrap transition-colors focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-fl-gold"
                    :class="
                        tab.key === active
                            ? 'border-fl-gold text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.count !== undefined"
                        class="rounded-full bg-muted px-1.5 py-0.5 text-[11px] leading-none text-muted-foreground tabular-nums"
                        >{{ tab.count.toLocaleString('es-MX') }}</span
                    >
                </Link>
            </div>
        </nav>
    </header>
</template>
