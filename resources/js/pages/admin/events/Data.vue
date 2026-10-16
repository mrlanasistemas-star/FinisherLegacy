<script setup lang="ts">
/**
 * Event workspace › Datos: where this event's participants and results come
 * from (organizer data sources, per purpose), the sync shortcut and the
 * file imports for this event — formerly three separate menu entries
 * (Importaciones, Fuentes de datos, Integraciones).
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { FileUp, RefreshCw } from '@lucide/vue';
import { computed } from 'vue';
import EventWorkspace from '@/components/admin/events/EventWorkspace.vue';
import type { EventWorkspaceData } from '@/components/admin/events/types';
import FancySelect from '@/components/forms/FancySelect.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

type Source = {
    id: number;
    name: string | null;
    type: string;
    purpose: string;
    is_default: boolean;
};
type Resolved = {
    type: string;
    provider_connection: string | null;
    is_override: boolean;
} | null;

const props = defineProps<{
    workspace: EventWorkspaceData;
    organizer: { id: number; name: string } | null;
    dataSource: {
        type: string | null;
        provider_connection: string | null;
        inherited: boolean;
        participants_data_source_id: number | null;
        results_data_source_id: number | null;
        organizer_sources: Source[];
        resolved_participants: Resolved;
        resolved_results: Resolved;
    };
    syncMapping: { id: number; provider_connection: string } | null;
    imports: {
        id: number;
        filename: string;
        status: string;
        total_rows: number | null;
        successful_rows: number | null;
        failed_rows: number | null;
        created_at: string | null;
    }[];
}>();

const page = usePage();
const permissions = computed(() => new Set(page.props.auth?.permissions ?? []));
const canEditSources = computed(() =>
    permissions.value.has('eventdata.manage'),
);
const canSync = computed(() => permissions.value.has('integrations.sync'));
const canImport = computed(() => permissions.value.has('imports.manage'));

const typeLabels: Record<string, string> = {
    manual: 'Captura manual',
    file: 'Archivo',
    api: 'Conexión automática',
};

const importStatusLabels: Record<string, string> = {
    pending: 'En espera',
    processing: 'Procesando',
    completed: 'Completada',
    completed_with_errors: 'Con errores',
    failed: 'Falló',
};

function sourceOptions(purpose: 'participants' | 'results') {
    return [
        { value: null, label: 'Usar la del organizador' },
        ...props.dataSource.organizer_sources
            .filter((s) => s.purpose === purpose || s.purpose === 'both')
            .map((s) => ({
                value: s.id,
                label: `${s.name ?? typeLabels[s.type] ?? s.type}${s.is_default ? ' (predeterminada)' : ''}`,
            })),
    ];
}

const participantOptions = computed(() => sourceOptions('participants'));
const resultOptions = computed(() => sourceOptions('results'));

const form = useForm({
    participants_data_source_id: props.dataSource
        .participants_data_source_id as string | number | null,
    results_data_source_id: props.dataSource.results_data_source_id as
        string | number | null,
});

function save() {
    form.put(`/admin/editions/${props.workspace.id}/data-sources`, {
        preserveScroll: true,
    });
}

function syncNow() {
    if (!props.syncMapping) {
        return;
    }

    router.post(
        `/admin/integrations/mappings/${props.syncMapping.id}/sync`,
        {},
        { preserveScroll: true },
    );
}

function resolvedLabel(resolved: Resolved): string {
    if (!resolved) {
        return 'Sin fuente configurada';
    }

    const base = typeLabels[resolved.type] ?? resolved.type;

    return resolved.provider_connection
        ? `${base} · ${resolved.provider_connection}`
        : base;
}

function formatDate(iso: string | null): string {
    return iso
        ? new Date(iso).toLocaleString('es-MX', {
              dateStyle: 'medium',
              timeStyle: 'short',
          })
        : '—';
}
</script>

<template>
    <Head :title="`Datos — ${workspace.name}`" />

    <EventWorkspace :workspace="workspace" active="data">
        <div class="grid gap-6 xl:grid-cols-2">
            <section class="fl-card p-5">
                <h2 class="text-sm font-semibold text-foreground">
                    Fuentes de datos
                </h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Organizador:
                    <Link
                        v-if="organizer"
                        :href="`/admin/organizers/${organizer.id}`"
                        class="font-medium text-fl-gold-ink hover:underline"
                        >{{ organizer.name }}</Link
                    >
                    <span v-else>sin organizador</span>
                </p>

                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label class="text-xs text-muted-foreground"
                            >Participantes</Label
                        >
                        <FancySelect
                            v-model="form.participants_data_source_id"
                            :options="participantOptions"
                            :disabled="!canEditSources"
                            aria-label="Fuente de participantes"
                        />
                        <p class="text-xs text-muted-foreground">
                            Se usa:
                            {{
                                resolvedLabel(dataSource.resolved_participants)
                            }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label class="text-xs text-muted-foreground"
                            >Resultados</Label
                        >
                        <FancySelect
                            v-model="form.results_data_source_id"
                            :options="resultOptions"
                            :disabled="!canEditSources"
                            aria-label="Fuente de resultados"
                        />
                        <p class="text-xs text-muted-foreground">
                            Se usa:
                            {{ resolvedLabel(dataSource.resolved_results) }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <Button
                        v-if="canEditSources"
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="form.processing || !form.isDirty"
                        @click="save"
                    >
                        Guardar fuentes
                    </Button>
                    <Button
                        v-if="syncMapping && canSync"
                        variant="outline"
                        @click="syncNow"
                    >
                        <RefreshCw class="size-4" />
                        Sincronizar ahora
                    </Button>
                </div>

                <p
                    v-if="!syncMapping"
                    class="mt-4 text-xs text-muted-foreground"
                >
                    Este evento no tiene una conexión automática vinculada —
                    <Link
                        href="/admin/integrations"
                        class="font-medium text-fl-gold-ink hover:underline"
                        >vincúlalo desde Configuración › Conexiones</Link
                    >.
                </p>
                <p v-else class="mt-4 text-xs text-muted-foreground">
                    Conectado con {{ syncMapping.provider_connection }}.
                </p>
                <p
                    v-if="!dataSource.organizer_sources.length"
                    class="mt-2 text-xs text-muted-foreground"
                >
                    El organizador no tiene fuentes todavía —
                    <Link
                        :href="
                            organizer
                                ? `/admin/organizers/${organizer.id}`
                                : '/admin/organizers'
                        "
                        class="font-medium text-fl-gold-ink hover:underline"
                        >agrégalas desde Organizadores</Link
                    >.
                </p>
            </section>

            <section class="fl-card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-foreground">
                            Importaciones de archivo
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            CSV o Excel con participantes de este evento.
                        </p>
                    </div>
                    <Button v-if="canImport" as-child size="sm">
                        <Link
                            :href="`/imports/create?event_edition_id=${workspace.id}`"
                        >
                            <FileUp class="size-4" />
                            Importar archivo
                        </Link>
                    </Button>
                </div>

                <ul class="mt-4 divide-y divide-border text-sm">
                    <li
                        v-for="item in imports"
                        :key="item.id"
                        class="flex flex-wrap items-center justify-between gap-2 py-3"
                    >
                        <span class="min-w-0">
                            <Link
                                v-if="canImport"
                                :href="`/imports/${item.id}`"
                                class="block truncate font-medium text-foreground hover:underline"
                                >{{ item.filename }}</Link
                            >
                            <span
                                v-else
                                class="block truncate font-medium text-foreground"
                                >{{ item.filename }}</span
                            >
                            <span class="text-xs text-muted-foreground"
                                >{{ formatDate(item.created_at) }} ·
                                {{ item.successful_rows ?? 0 }} de
                                {{ item.total_rows ?? 0 }} filas</span
                            >
                        </span>
                        <Badge
                            variant="outline"
                            :class="
                                (item.failed_rows ?? 0) > 0
                                    ? 'border-amber-500/30 text-amber-700'
                                    : 'border-emerald-500/30 text-emerald-700'
                            "
                        >
                            {{ importStatusLabels[item.status] ?? item.status }}
                        </Badge>
                    </li>
                    <li
                        v-if="!imports.length"
                        class="py-6 text-center text-muted-foreground"
                    >
                        Sin importaciones para este evento.
                    </li>
                </ul>
            </section>
        </div>
    </EventWorkspace>
</template>
