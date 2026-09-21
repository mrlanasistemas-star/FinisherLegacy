<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Building2, PlugZap, Plus } from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

type ProviderConnectionSummary = {
    id: number;
    name: string;
    provider_key: string;
    status: string;
    last_tested_at: string | null;
    last_successful_sync_at: string | null;
};

type DataSource = {
    id: number;
    name: string | null;
    type: string;
    purpose: string;
    is_default: boolean;
    active: boolean;
    provider_connection: ProviderConnectionSummary | null;
};

const props = defineProps<{
    organizer: {
        id: number;
        name: string;
        legal_name: string | null;
        email: string | null;
        phone: string | null;
        website: string | null;
        status: string;
        logo_url: string | null;
    };
    events: {
        id: number;
        name: string;
        slug: string;
        sport: string;
        status: string;
    }[];
    dataSources: DataSource[];
    providerConnections: { id: number; name: string; provider_key: string }[];
}>();

const typeLabels: Record<string, string> = {
    manual: 'Manual',
    file: 'Archivo',
    api: 'API',
};

const purposeLabels: Record<string, string> = {
    participants: 'Participantes',
    results: 'Resultados',
    both: 'Ambos',
};

const showAddForm = ref(false);

const addForm = useForm({
    name: '',
    type: 'manual',
    purpose: 'both',
    provider_connection_id: '' as number | '',
    is_default: false,
});

function addDataSource() {
    addForm.post(`/admin/organizers/${props.organizer.id}/data-sources`, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAddForm.value = false;
        },
    });
}

function deactivate(sourceId: number) {
    router.post(
        `/admin/data-sources/${sourceId}/deactivate`,
        {},
        { preserveScroll: true },
    );
}

function testConnection(connectionId: number) {
    router.post(
        `/admin/provider-connections/${connectionId}/test`,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="organizer.name" />

    <div class="p-4 md:p-8">
        <div class="mb-6 flex items-center gap-4">
            <div
                class="flex size-14 items-center justify-center overflow-hidden rounded-xl border border-white/10 bg-fl-graphite/40"
            >
                <img
                    v-if="organizer.logo_url"
                    :src="organizer.logo_url"
                    alt=""
                    class="size-full object-cover"
                />
                <Building2 v-else class="size-6 text-white/20" />
            </div>
            <div>
                <h1 class="text-xl font-bold text-white">
                    {{ organizer.name }}
                </h1>
                <p class="text-sm text-white/50">
                    {{ organizer.legal_name ?? 'Sin razón social' }}
                </p>
            </div>
            <Badge
                variant="outline"
                class="ml-auto border-emerald-500/30 text-emerald-400"
            >
                {{ organizer.status === 'active' ? 'Activo' : 'Inactivo' }}
            </Badge>
        </div>

        <Tabs default-value="general">
            <TabsList>
                <TabsTrigger value="general">General</TabsTrigger>
                <TabsTrigger value="events">Eventos</TabsTrigger>
                <TabsTrigger value="data">Datos / Integración</TabsTrigger>
            </TabsList>

            <TabsContent value="general" class="mt-6">
                <div
                    class="grid grid-cols-2 gap-4 rounded-xl border border-white/10 bg-fl-graphite/30 p-5 text-sm sm:grid-cols-3"
                >
                    <div>
                        <p class="text-xs text-white/30 uppercase">Correo</p>
                        <p class="text-white">{{ organizer.email ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-white/30 uppercase">Teléfono</p>
                        <p class="text-white">{{ organizer.phone ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-white/30 uppercase">Sitio web</p>
                        <a
                            v-if="organizer.website"
                            :href="organizer.website"
                            target="_blank"
                            class="text-fl-gold hover:underline"
                            >{{ organizer.website }}</a
                        >
                        <p v-else class="text-white">—</p>
                    </div>
                </div>
            </TabsContent>

            <TabsContent value="events" class="mt-6">
                <div class="overflow-x-auto rounded-xl border border-white/10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-white/10 bg-fl-graphite/40 text-left text-xs text-white/50 uppercase"
                            >
                                <th class="px-4 py-3 font-medium">Evento</th>
                                <th class="px-4 py-3 font-medium">Deporte</th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="event in events"
                                :key="event.id"
                                class="border-b border-white/5 text-white/80 last:border-0"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="`/events/${event.slug}`"
                                        class="hover:text-fl-gold"
                                        >{{ event.name }}</Link
                                    >
                                </td>
                                <td class="px-4 py-3">{{ event.sport }}</td>
                                <td class="px-4 py-3">{{ event.status }}</td>
                            </tr>
                            <tr v-if="!events.length">
                                <td
                                    colspan="3"
                                    class="px-4 py-10 text-center text-white/30"
                                >
                                    Sin eventos todavía.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </TabsContent>

            <TabsContent value="data" class="mt-6 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3
                            class="text-sm font-semibold text-white/70 uppercase"
                        >
                            Fuentes de datos
                        </h3>
                        <p class="text-xs text-white/40">
                            Un organizador puede tener varias — Manual, Archivo
                            y una o más conexiones API. Cada evento puede elegir
                            una específica o heredar el default.
                        </p>
                    </div>
                    <Button
                        size="sm"
                        class="shrink-0 bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        @click="showAddForm = !showAddForm"
                    >
                        <Plus class="size-3.5" /> Agregar fuente de datos
                    </Button>
                </div>

                <div
                    v-if="showAddForm"
                    class="rounded-xl border border-fl-gold/20 bg-fl-graphite/30 p-5"
                >
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="grid gap-2">
                            <Label class="text-xs text-white/50">Nombre</Label>
                            <Input
                                v-model="addForm.name"
                                class="border-white/10 bg-fl-black text-white"
                                placeholder="API Resultados"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-xs text-white/50">Tipo</Label>
                            <Select v-model="addForm.type">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="manual"
                                        >Manual</SelectItem
                                    >
                                    <SelectItem value="file"
                                        >Archivo</SelectItem
                                    >
                                    <SelectItem value="api">API</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-xs text-white/50"
                                >Propósito</Label
                            >
                            <Select v-model="addForm.purpose">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                    ><SelectValue
                                /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="participants"
                                        >Participantes</SelectItem
                                    >
                                    <SelectItem value="results"
                                        >Resultados</SelectItem
                                    >
                                    <SelectItem value="both">Ambos</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div v-if="addForm.type === 'api'" class="grid gap-2">
                            <Label class="text-xs text-white/50"
                                >Conexión</Label
                            >
                            <Select v-model="addForm.provider_connection_id">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                >
                                    <SelectValue
                                        placeholder="Selecciona una conexión"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="connection in providerConnections"
                                        :key="connection.id"
                                        :value="connection.id"
                                        :title="
                                            connection.provider_key === 'mock'
                                                ? 'Solo para pruebas. No usar con un organizador real.'
                                                : undefined
                                        "
                                    >
                                        <span
                                            v-if="
                                                connection.provider_key ===
                                                'mock'
                                            "
                                            class="mr-1 text-amber-400"
                                            >[SIMULACIÓN]</span
                                        >
                                        {{ connection.name }} ({{
                                            connection.provider_key
                                        }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <label
                        class="mt-4 flex items-center gap-2 text-sm text-white/70"
                    >
                        <Checkbox
                            :model-value="addForm.is_default"
                            @update:model-value="
                                (v) => (addForm.is_default = !!v)
                            "
                        />
                        Marcar como default para este propósito
                    </label>
                    <Button
                        class="mt-4 bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="addForm.processing"
                        @click="addDataSource"
                    >
                        Guardar fuente
                    </Button>
                </div>

                <div
                    v-if="!dataSources.length"
                    class="rounded-xl border border-dashed border-white/15 bg-fl-graphite/20 p-8 text-center text-sm text-white/40"
                >
                    No hay fuentes de datos todavía.
                </div>

                <div
                    v-for="source in dataSources"
                    :key="source.id"
                    class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-white">
                                    <span
                                        v-if="
                                            source.provider_connection
                                                ?.provider_key === 'mock'
                                        "
                                        class="mr-1 text-amber-400"
                                        title="Solo para pruebas. No usar con un organizador real."
                                        >[SIMULACIÓN]</span
                                    >
                                    {{ source.name ?? typeLabels[source.type] }}
                                </p>
                                <Badge
                                    v-if="source.is_default"
                                    variant="outline"
                                    class="border-fl-gold/30 text-fl-gold-soft"
                                    >Default</Badge
                                >
                                <Badge
                                    v-if="!source.active"
                                    variant="outline"
                                    class="border-white/20 text-white/40"
                                    >Inactiva</Badge
                                >
                            </div>
                            <p class="mt-1 text-xs text-white/40">
                                {{ typeLabels[source.type] }} ·
                                {{ purposeLabels[source.purpose] }}
                            </p>
                            <div
                                v-if="source.provider_connection"
                                class="mt-2 text-xs text-white/40"
                            >
                                <p class="text-white/60">
                                    {{ source.provider_connection.name }} ({{
                                        source.provider_connection.provider_key
                                    }})
                                </p>
                                <p>
                                    Estado:
                                    {{ source.provider_connection.status }} ·
                                    Última prueba:
                                    {{
                                        source.provider_connection
                                            .last_tested_at ?? 'nunca'
                                    }}
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <Button
                                v-if="source.provider_connection"
                                size="sm"
                                variant="outline"
                                class="border-white/15 text-white hover:bg-white/10"
                                @click="
                                    testConnection(
                                        source.provider_connection!.id,
                                    )
                                "
                            >
                                <PlugZap class="size-3.5" /> Probar conexión
                            </Button>
                            <Button
                                v-if="source.active"
                                size="sm"
                                variant="outline"
                                class="border-red-500/30 text-red-400 hover:bg-red-500/10"
                                @click="deactivate(source.id)"
                            >
                                Desactivar
                            </Button>
                        </div>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
