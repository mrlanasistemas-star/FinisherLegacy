<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Badge } from '@/components/ui/badge';

type SyncRunData = {
    id: number;
    status: string;
    sync_type: string;
    provider: string;
    event: string | null;
    edition: string | null;
    started_at: string | null;
    completed_at: string | null;
    events_received: number;
    participants_received: number;
    participants_created: number;
    participants_updated: number;
    results_received: number;
    results_created: number;
    results_updated: number;
    splits_received: number;
    identity_conflicts: number;
    errors_count: number;
};

const props = defineProps<{
    run: SyncRunData;
    errors: {
        entity_type: string;
        external_id: string | null;
        code: string;
        message: string;
        created_at: string;
    }[];
}>();

const run = ref<SyncRunData>(props.run);
let timer: ReturnType<typeof setInterval> | undefined;

// Polls a small JSON endpoint every 4s while the run is active — never a
// full Inertia reload (docs/adr/0005 §41, §36).
onMounted(() => {
    timer = setInterval(async () => {
        if (run.value.status !== 'pending' && run.value.status !== 'running') {
            clearInterval(timer);

            return;
        }

        const response = await fetch(
            `/admin/integrations/sync-runs/${props.run.id}/status`,
            {
                headers: { Accept: 'application/json' },
            },
        );

        if (response.ok) {
            const { data } = await response.json();
            run.value = data;
        }
    }, 4000);
});

onBeforeUnmount(() => {
    if (timer) {
        clearInterval(timer);
    }
});

const statusClass: Record<string, string> = {
    completed: 'border-emerald-500/30 text-emerald-700',
    partial: 'border-amber-500/30 text-amber-700',
    failed: 'border-red-500/30 text-red-700',
    running: 'border-fl-gold/40 text-fl-gold-ink',
    pending: 'border-foreground/15 text-muted-foreground',
};
</script>

<template>
    <Head :title="`Sync #${run.id}`" />

    <div class="p-4 md:p-8">
        <Link
            href="/admin/integrations"
            class="mb-4 inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-3.5" /> Integraciones
        </Link>

        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-foreground">
                    Sincronización #{{ run.id }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ run.provider }} — {{ run.event }} {{ run.edition }} ({{
                        run.sync_type
                    }})
                </p>
            </div>
            <Badge variant="outline" :class="statusClass[run.status]">{{
                run.status
            }}</Badge>
        </div>

        <div class="mb-8 grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
            <div class="rounded-xl border border-border bg-card/30 p-4">
                <p class="text-xs text-muted-foreground/80 uppercase">
                    Eventos
                </p>
                <p class="mt-1 text-lg text-foreground">
                    {{ run.events_received }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card/30 p-4">
                <p class="text-xs text-muted-foreground/80 uppercase">
                    Participantes
                </p>
                <p class="mt-1 text-lg text-foreground">
                    {{ run.participants_received }}
                </p>
                <p class="text-xs text-muted-foreground/80">
                    +{{ run.participants_created }} nuevos ·
                    {{ run.participants_updated }} actualizados
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card/30 p-4">
                <p class="text-xs text-muted-foreground/80 uppercase">
                    Resultados
                </p>
                <p class="mt-1 text-lg text-foreground">
                    {{ run.results_received }}
                </p>
                <p class="text-xs text-muted-foreground/80">
                    +{{ run.results_created }} nuevos ·
                    {{ run.results_updated }} actualizados
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card/30 p-4">
                <p class="text-xs text-muted-foreground/80 uppercase">Splits</p>
                <p class="mt-1 text-lg text-foreground">
                    {{ run.splits_received }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card/30 p-4">
                <p class="text-xs text-muted-foreground/80 uppercase">
                    Conflictos de identidad
                </p>
                <p class="mt-1 text-lg text-foreground">
                    {{ run.identity_conflicts }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card/30 p-4">
                <p class="text-xs text-muted-foreground/80 uppercase">
                    Errores
                </p>
                <p
                    class="mt-1 text-lg"
                    :class="
                        run.errors_count > 0
                            ? 'text-amber-700'
                            : 'text-foreground'
                    "
                >
                    {{ run.errors_count }}
                </p>
            </div>
        </div>

        <h2 class="mb-3 text-sm font-semibold text-foreground">Errores</h2>
        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border bg-card/40 text-left text-xs text-muted-foreground uppercase"
                    >
                        <th class="px-4 py-3 font-medium">Tipo</th>
                        <th class="px-4 py-3 font-medium">ID externo</th>
                        <th class="px-4 py-3 font-medium">Código</th>
                        <th class="px-4 py-3 font-medium">Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(e, i) in errors"
                        :key="i"
                        class="border-b border-border text-foreground last:border-0"
                    >
                        <td class="px-4 py-3">{{ e.entity_type }}</td>
                        <td class="px-4 py-3">{{ e.external_id ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
                                class="border-amber-500/30 text-amber-700"
                                >{{ e.code }}</Badge
                            >
                        </td>
                        <td class="px-4 py-3">{{ e.message }}</td>
                    </tr>
                    <tr v-if="!errors.length">
                        <td
                            colspan="4"
                            class="px-4 py-8 text-center text-muted-foreground/80"
                        >
                            Sin errores.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
