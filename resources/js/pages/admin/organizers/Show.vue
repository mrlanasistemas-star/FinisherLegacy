<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Building2, PlugZap } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

type ProviderConnection = {
    id: number;
    name: string;
    provider_key: string;
    status: string;
    last_tested_at: string | null;
    last_successful_sync_at: string | null;
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
    dataSource: {
        type: string;
        active: boolean;
        provider_connection: ProviderConnection | null;
    } | null;
    providerConnections: { id: number; name: string; provider_key: string }[];
}>();

const dataSourceLabels: Record<string, string> = {
    manual: 'Manual',
    file: 'Archivo',
    api: 'API',
};

const form = useForm({
    type: props.dataSource?.type ?? 'manual',
    provider_connection_id:
        props.dataSource?.provider_connection?.id ?? ('' as number | ''),
});

function saveDataSource() {
    form.put(`/admin/organizers/${props.organizer.id}/data-source`, {
        preserveScroll: true,
    });
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

            <TabsContent value="data" class="mt-6">
                <div
                    class="rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
                >
                    <h3
                        class="mb-1 text-sm font-semibold text-white/70 uppercase"
                    >
                        ¿Cómo recibiremos los datos?
                    </h3>
                    <p class="mb-4 text-xs text-white/40">
                        Este será el valor por defecto para todos los eventos de
                        este organizador — cada evento puede sobrescribirlo
                        individualmente.
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Select v-model="form.type">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                >
                                    <SelectValue />
                                </SelectTrigger>
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
                        <div v-if="form.type === 'api'" class="grid gap-2">
                            <Select v-model="form.provider_connection_id">
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
                                    >
                                        {{ connection.name }} ({{
                                            connection.provider_key
                                        }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <Button
                        class="mt-4 bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="form.processing"
                        @click="saveDataSource"
                    >
                        Guardar
                    </Button>

                    <div
                        v-if="dataSource?.provider_connection"
                        class="mt-6 rounded-lg border border-white/10 bg-fl-black/40 p-4 text-sm"
                    >
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-white">
                                    {{ dataSource.provider_connection.name }}
                                </p>
                                <p class="text-xs text-white/40">
                                    {{ dataSourceLabels[dataSource.type] }} ·
                                    estado:
                                    {{ dataSource.provider_connection.status }}
                                </p>
                                <p class="text-xs text-white/30">
                                    Última prueba:
                                    {{
                                        dataSource.provider_connection
                                            .last_tested_at ?? 'nunca'
                                    }}
                                    · Último sync:
                                    {{
                                        dataSource.provider_connection
                                            .last_successful_sync_at ?? 'nunca'
                                    }}
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                class="border-white/15 text-white hover:bg-white/10"
                                @click="
                                    testConnection(
                                        dataSource.provider_connection!.id,
                                    )
                                "
                            >
                                <PlugZap class="size-3.5" />
                                Probar conexión
                            </Button>
                        </div>
                    </div>
                </div>
            </TabsContent>
        </Tabs>
    </div>
</template>
