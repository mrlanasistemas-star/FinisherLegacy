<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Plug } from '@lucide/vue';
import HelpPopover from '@/components/HelpPopover.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Connection = {
    id: number;
    name: string;
    provider_key: string;
    status: 'untested' | 'connected' | 'failed';
    last_tested_at: string | null;
    last_successful_sync_at: string | null;
    event_mappings_count: number;
};

defineProps<{
    connections: Connection[];
    providerKeys: string[];
}>();

const form = useForm({
    name: '',
    provider_key: 'mock',
    base_url: '',
    api_key: '',
    auth_type: 'none',
    api_key_header: '',
    basic_username: '',
    test_endpoint: '',
    events_endpoint: '',
    event_endpoint: '',
    participants_endpoint: '',
    results_endpoint: '',
});

function submit() {
    form.post('/admin/integrations', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

const statusClass: Record<Connection['status'], string> = {
    connected: 'border-emerald-500/30 text-emerald-700',
    failed: 'border-red-500/30 text-red-700',
    untested: 'border-foreground/15 text-muted-foreground',
};

const statusLabel: Record<Connection['status'], string> = {
    connected: 'Conectado',
    failed: 'Falló',
    untested: 'Sin probar',
};
</script>

<template>
    <Head title="Integraciones" />

    <div class="p-4 md:p-8">
        <h1
            class="mb-1 flex items-center gap-1.5 text-xl font-bold text-foreground"
        >
            <Plug class="size-5 text-fl-gold-ink" />
            Integraciones
            <HelpPopover
                title="Ingestión unificada"
                text="Cada conexión habla con un proveedor de eventos/timing (o el Mock Event Provider para pruebas) y produce los mismos datos canónicos que un CSV — corredores, resultados y splits terminan en el mismo Athlete/EventParticipant/EventResult de siempre."
            />
        </h1>
        <p class="mb-6 text-sm text-muted-foreground">
            Conexiones a proveedores externos de eventos y resultados.
        </p>

        <div class="mb-8 rounded-xl border border-border bg-card/30 p-4">
            <h2 class="mb-3 text-sm font-semibold text-foreground">
                Nueva conexión
            </h2>
            <form
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                @submit.prevent="submit"
            >
                <div>
                    <Label class="mb-1 block text-xs text-muted-foreground"
                        >Proveedor</Label
                    >
                    <select
                        v-model="form.provider_key"
                        class="h-9 w-full rounded-md border border-border bg-card/60 px-3 text-sm text-foreground"
                    >
                        <option
                            v-for="key in providerKeys"
                            :key="key"
                            :value="key"
                        >
                            {{ key === 'mock' ? '[SIMULACIÓN] mock' : key }}
                        </option>
                    </select>
                </div>
                <div>
                    <Label class="mb-1 block text-xs text-muted-foreground"
                        >Nombre</Label
                    >
                    <Input
                        v-model="form.name"
                        class="border-border bg-card/60 text-foreground"
                        placeholder="Timing Provider — Guadalajara"
                    />
                </div>
                <div>
                    <Label class="mb-1 block text-xs text-muted-foreground"
                        >Base URL (opcional)</Label
                    >
                    <Input
                        v-model="form.base_url"
                        class="border-border bg-card/60 text-foreground"
                        placeholder="https://api.proveedor.com"
                    />
                </div>
                <div>
                    <Label class="mb-1 block text-xs text-muted-foreground"
                        >API Key (opcional)</Label
                    >
                    <Input
                        v-model="form.api_key"
                        type="password"
                        class="border-border bg-card/60 text-foreground"
                    />
                </div>

                <template v-if="form.provider_key === 'generic_rest'">
                    <div>
                        <Label class="mb-1 block text-xs text-muted-foreground"
                            >Autenticación</Label
                        >
                        <select
                            v-model="form.auth_type"
                            class="h-9 w-full rounded-md border border-border bg-card/60 px-3 text-sm text-foreground"
                        >
                            <option value="none">Ninguna</option>
                            <option value="bearer">Bearer token</option>
                            <option value="api_key_header">
                                API Key header
                            </option>
                            <option value="basic">Basic auth</option>
                        </select>
                    </div>
                    <div v-if="form.auth_type === 'api_key_header'">
                        <Label class="mb-1 block text-xs text-muted-foreground"
                            >Nombre del header</Label
                        >
                        <Input
                            v-model="form.api_key_header"
                            class="border-border bg-card/60 text-foreground"
                            placeholder="X-Api-Key"
                        />
                    </div>
                    <div v-if="form.auth_type === 'basic'">
                        <Label class="mb-1 block text-xs text-muted-foreground"
                            >Usuario</Label
                        >
                        <Input
                            v-model="form.basic_username"
                            class="border-border bg-card/60 text-foreground"
                        />
                    </div>
                    <div>
                        <Label class="mb-1 block text-xs text-muted-foreground"
                            >Endpoint de prueba</Label
                        >
                        <Input
                            v-model="form.test_endpoint"
                            class="border-border bg-card/60 text-foreground"
                            placeholder="/health"
                        />
                    </div>
                    <div>
                        <Label class="mb-1 block text-xs text-muted-foreground"
                            >Endpoint participantes</Label
                        >
                        <Input
                            v-model="form.participants_endpoint"
                            class="border-border bg-card/60 text-foreground"
                            placeholder="/events/{external_event_id}/participants"
                        />
                    </div>
                    <div>
                        <Label class="mb-1 block text-xs text-muted-foreground"
                            >Endpoint resultados</Label
                        >
                        <Input
                            v-model="form.results_endpoint"
                            class="border-border bg-card/60 text-foreground"
                            placeholder="/events/{external_event_id}/results"
                        />
                    </div>
                    <div>
                        <Label class="mb-1 block text-xs text-muted-foreground"
                            >Endpoint del evento</Label
                        >
                        <Input
                            v-model="form.event_endpoint"
                            class="border-border bg-card/60 text-foreground"
                            placeholder="/events/{external_event_id}"
                        />
                    </div>
                    <div>
                        <Label class="mb-1 block text-xs text-muted-foreground"
                            >Endpoint listar eventos</Label
                        >
                        <Input
                            v-model="form.events_endpoint"
                            class="border-border bg-card/60 text-foreground"
                            placeholder="/events"
                        />
                    </div>
                    <p
                        class="text-xs text-muted-foreground/80 sm:col-span-2 lg:col-span-4"
                    >
                        El mapeo de campos (field mapping) se configura desde el
                        detalle de la conexión, una vez creada.
                    </p>
                </template>

                <div class="sm:col-span-2 lg:col-span-4">
                    <Button
                        type="submit"
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="form.processing"
                    >
                        Crear conexión
                    </Button>
                    <span
                        v-if="form.errors.name"
                        class="ml-3 text-xs text-red-700"
                        >{{ form.errors.name }}</span
                    >
                </div>
            </form>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border bg-card/40 text-left text-xs text-muted-foreground uppercase"
                    >
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Proveedor</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 font-medium">Última prueba</th>
                        <th class="px-4 py-3 font-medium">Último sync</th>
                        <th class="px-4 py-3 font-medium">Eventos</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in connections"
                        :key="c.id"
                        class="border-b border-border text-foreground last:border-0"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="`/admin/integrations/${c.id}`"
                                class="text-fl-gold-ink hover:underline"
                                >{{ c.name }}</Link
                            >
                        </td>
                        <td class="px-4 py-3">
                            <span
                                v-if="c.provider_key === 'mock'"
                                class="mr-1 text-amber-700"
                                title="Solo para pruebas. No usar con un organizador real."
                                >[SIMULACIÓN]</span
                            >
                            {{ c.provider_key }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
                                :class="statusClass[c.status]"
                                >{{ statusLabel[c.status] }}</Badge
                            >
                        </td>
                        <td class="px-4 py-3">{{ c.last_tested_at ?? '—' }}</td>
                        <td class="px-4 py-3">
                            {{ c.last_successful_sync_at ?? '—' }}
                        </td>
                        <td class="px-4 py-3">{{ c.event_mappings_count }}</td>
                    </tr>
                    <tr v-if="!connections.length">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-muted-foreground/80"
                        >
                            Sin conexiones todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
