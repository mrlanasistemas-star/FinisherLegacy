<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Database } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

type Source = {
    id: number;
    name: string | null;
    type: string;
    purpose: string;
    is_default: boolean;
    active: boolean;
    provider_connection: {
        id: number;
        name: string;
        provider_key: string;
        status: string;
    } | null;
};

type OrganizerRow = {
    id: number;
    name: string;
    sources: Source[];
};

defineProps<{ organizers: OrganizerRow[] }>();

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
</script>

<template>
    <Head title="Fuentes de datos" />

    <div class="p-4 md:p-8">
        <div class="mb-6">
            <h1 class="flex items-center gap-2 text-xl font-bold text-white">
                <Database class="size-5 text-fl-gold" />
                Fuentes de datos
            </h1>
            <p class="mt-1 text-sm text-white/50">
                Cómo recibe información cada organizador — un organizador puede
                tener varias. Agrega/edita desde su pestaña "Datos /
                Integración".
            </p>
        </div>

        <div class="space-y-4">
            <div
                v-for="organizer in organizers"
                :key="organizer.id"
                class="rounded-xl border border-white/10 bg-fl-graphite/20 p-5"
            >
                <Link
                    :href="`/admin/organizers/${organizer.id}`"
                    class="font-medium text-white hover:text-fl-gold"
                    >{{ organizer.name }}</Link
                >

                <div
                    v-if="organizer.sources.length"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <div
                        v-for="source in organizer.sources"
                        :key="source.id"
                        class="flex items-center gap-2 rounded-lg border border-white/10 bg-fl-black/30 px-3 py-2 text-xs"
                        :class="!source.active ? 'opacity-40' : ''"
                    >
                        <Badge
                            variant="outline"
                            class="border-white/15 text-white/60"
                            >{{ typeLabels[source.type] }}</Badge
                        >
                        <span class="text-white/70">{{
                            source.name ?? purposeLabels[source.purpose]
                        }}</span>
                        <span class="text-white/30"
                            >· {{ purposeLabels[source.purpose] }}</span
                        >
                        <Badge
                            v-if="source.is_default"
                            variant="outline"
                            class="border-fl-gold/30 text-fl-gold-soft"
                            >Default</Badge
                        >
                        <span
                            v-if="source.provider_connection"
                            class="text-white/40"
                            >{{ source.provider_connection.status }}</span
                        >
                    </div>
                </div>
                <p v-else class="mt-3 text-xs text-white/30">
                    Sin fuentes de datos configuradas.
                </p>
            </div>

            <div
                v-if="!organizers.length"
                class="rounded-xl border border-dashed border-white/15 bg-fl-graphite/20 p-8 text-center text-sm text-white/40"
            >
                Sin organizadores todavía.
            </div>
        </div>
    </div>
</template>
