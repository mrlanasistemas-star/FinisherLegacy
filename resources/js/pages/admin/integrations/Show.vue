<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, RefreshCw } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type ExternalEvent = {
    external_id: string;
    name: string;
    date: string | null;
    city: string | null;
};
type SyncSummary = {
    id: number;
    status: string;
    started_at: string | null;
    participants_received: number;
    results_received: number;
    errors_count: number;
};
type Mapping = {
    id: number;
    external_event_id: string;
    event: string | null;
    edition: string | null;
    event_edition_id: number;
    last_sync: SyncSummary | null;
};

const props = defineProps<{
    connection: {
        id: number;
        name: string;
        provider_key: string;
        status: 'untested' | 'connected' | 'failed';
        base_url: string | null;
        last_tested_at: string | null;
        last_successful_sync_at: string | null;
    };
    availableEvents: ExternalEvent[];
    listError: string | null;
    mappings: Mapping[];
    editions: { id: number; name: string }[];
    sports: { id: number; name: string }[];
}>();

function testConnection() {
    router.post(
        `/admin/integrations/${props.connection.id}/test`,
        {},
        { preserveScroll: true },
    );
}

function syncNow(mappingId: number, syncType: 'roster' | 'results' | 'full') {
    router.post(
        `/admin/integrations/mappings/${mappingId}/sync`,
        { sync_type: syncType },
        { preserveScroll: true },
    );
}

const linkForm = useForm({
    external_event_id: '',
    mode: 'link' as 'link' | 'create',
    event_edition_id: null as number | null,
    sport_id: null as number | null,
});

function openLink(externalId: string) {
    linkForm.external_event_id = externalId;
    linkForm.mode = props.editions.length ? 'link' : 'create';
}

function submitLink() {
    linkForm.post(`/admin/integrations/${props.connection.id}/events`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="connection.name" />

    <div class="p-4 md:p-8">
        <Link
            href="/admin/integrations"
            class="mb-4 inline-flex items-center gap-1 text-xs text-white/50 hover:text-white"
        >
            <ArrowLeft class="size-3.5" /> Integraciones
        </Link>

        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1
                    class="flex items-center gap-2 text-xl font-bold text-white"
                >
                    {{ connection.name }}
                    <span
                        v-if="connection.provider_key === 'mock'"
                        class="rounded-full border border-amber-500/30 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-amber-400 uppercase"
                        title="Solo para pruebas. No usar con un organizador real."
                        >Simulación</span
                    >
                </h1>
                <p class="text-sm text-white/50">
                    Proveedor: {{ connection.provider_key }}
                </p>
            </div>
            <Button
                variant="outline"
                class="border-white/15 text-white hover:bg-white/10"
                @click="testConnection"
            >
                Probar conexión
            </Button>
        </div>

        <div class="mb-8 grid gap-4 sm:grid-cols-3">
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-4"
            >
                <p class="text-xs text-white/40 uppercase">Estado</p>
                <p class="mt-1 text-white">{{ connection.status }}</p>
            </div>
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-4"
            >
                <p class="text-xs text-white/40 uppercase">Última prueba</p>
                <p class="mt-1 text-white">
                    {{ connection.last_tested_at ?? '—' }}
                </p>
            </div>
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-4"
            >
                <p class="text-xs text-white/40 uppercase">
                    Último sync exitoso
                </p>
                <p class="mt-1 text-white">
                    {{ connection.last_successful_sync_at ?? '—' }}
                </p>
            </div>
        </div>

        <h2 class="mb-3 text-sm font-semibold text-white">
            Eventos vinculados
        </h2>
        <div class="mb-8 overflow-x-auto rounded-xl border border-white/10">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-white/10 bg-fl-graphite/40 text-left text-xs text-white/50 uppercase"
                    >
                        <th class="px-4 py-3 font-medium">Evento</th>
                        <th class="px-4 py-3 font-medium">Último sync</th>
                        <th class="px-4 py-3 font-medium">Participantes</th>
                        <th class="px-4 py-3 font-medium">Resultados</th>
                        <th class="px-4 py-3 font-medium">Errores</th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="m in mappings"
                        :key="m.id"
                        class="border-b border-white/5 text-white/80 last:border-0"
                    >
                        <td class="px-4 py-3">
                            {{ m.event }} — {{ m.edition }}
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                v-if="m.last_sync"
                                :href="`/admin/integrations/sync-runs/${m.last_sync.id}`"
                                class="text-fl-gold hover:underline"
                            >
                                {{ m.last_sync.status }} ·
                                {{ m.last_sync.started_at }}
                            </Link>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3">
                            {{ m.last_sync?.participants_received ?? 0 }}
                        </td>
                        <td class="px-4 py-3">
                            {{ m.last_sync?.results_received ?? 0 }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                v-if="(m.last_sync?.errors_count ?? 0) > 0"
                                variant="outline"
                                class="border-amber-500/30 text-amber-400"
                            >
                                {{ m.last_sync?.errors_count }}
                            </Badge>
                            <span v-else>0</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="border-white/15 text-white hover:bg-white/10"
                                    @click="syncNow(m.id, 'roster')"
                                >
                                    <RefreshCw class="size-3.5" /> Roster
                                </Button>
                                <Button
                                    size="sm"
                                    class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                                    @click="syncNow(m.id, 'results')"
                                >
                                    <RefreshCw class="size-3.5" /> Resultados
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!mappings.length">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-white/40"
                        >
                            Sin eventos vinculados todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2 class="mb-3 text-sm font-semibold text-white">
            Eventos disponibles en el proveedor
        </h2>
        <p v-if="listError" class="mb-4 text-sm text-red-400">
            {{ listError }}
        </p>
        <div class="mb-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="event in availableEvents"
                :key="event.external_id"
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-4"
            >
                <p class="text-white">{{ event.name }}</p>
                <p class="text-xs text-white/50">
                    {{ event.date }} · {{ event.city }}
                </p>
                <Button
                    size="sm"
                    class="mt-3 bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    @click="openLink(event.external_id)"
                >
                    Vincular
                </Button>
            </div>
        </div>

        <div
            v-if="linkForm.external_event_id"
            class="rounded-xl border border-white/10 bg-fl-graphite/30 p-4"
        >
            <h3 class="mb-3 text-sm font-semibold text-white">
                Vincular {{ linkForm.external_event_id }}
            </h3>
            <div class="mb-3 flex gap-4 text-sm text-white/70">
                <label class="flex items-center gap-1.5">
                    <input
                        type="radio"
                        value="link"
                        v-model="linkForm.mode"
                        :disabled="!editions.length"
                    />
                    Evento existente
                </label>
                <label class="flex items-center gap-1.5">
                    <input
                        type="radio"
                        value="create"
                        v-model="linkForm.mode"
                    />
                    Crear evento nuevo
                </label>
            </div>

            <select
                v-if="linkForm.mode === 'link'"
                v-model="linkForm.event_edition_id"
                class="h-9 w-full max-w-md rounded-md border border-white/10 bg-fl-graphite/60 px-3 text-sm text-white"
            >
                <option :value="null" disabled>Selecciona una edición…</option>
                <option v-for="e in editions" :key="e.id" :value="e.id">
                    {{ e.name }}
                </option>
            </select>

            <select
                v-else
                v-model="linkForm.sport_id"
                class="h-9 w-full max-w-md rounded-md border border-white/10 bg-fl-graphite/60 px-3 text-sm text-white"
            >
                <option :value="null" disabled>Selecciona un deporte…</option>
                <option v-for="s in sports" :key="s.id" :value="s.id">
                    {{ s.name }}
                </option>
            </select>

            <div class="mt-3">
                <Button
                    class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    :disabled="linkForm.processing"
                    @click="submitLink"
                >
                    Confirmar
                </Button>
            </div>
        </div>
    </div>
</template>
