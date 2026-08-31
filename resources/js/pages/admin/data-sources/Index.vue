<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Database } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';

type Row = {
    id: number;
    name: string;
    data_source: {
        type: string;
        provider_connection: string | null;
        status: string | null;
    } | null;
};

defineProps<{ organizers: Row[] }>();

const labels: Record<string, string> = {
    manual: 'Manual',
    file: 'Archivo',
    api: 'API',
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
                Cómo recibe información cada organizador — edítalo desde su
                pestaña "Datos / Integración".
            </p>
        </div>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-white/10 bg-fl-graphite/40 text-left text-xs text-white/50 uppercase"
                    >
                        <th class="px-4 py-3 font-medium">Organizador</th>
                        <th class="px-4 py-3 font-medium">Fuente</th>
                        <th class="px-4 py-3 font-medium">Conexión</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="organizer in organizers"
                        :key="organizer.id"
                        class="border-b border-white/5 text-white/80 last:border-0"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="`/admin/organizers/${organizer.id}`"
                                class="hover:text-fl-gold"
                                >{{ organizer.name }}</Link
                            >
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
                                class="border-white/15 text-white/60"
                            >
                                {{
                                    organizer.data_source
                                        ? labels[organizer.data_source.type]
                                        : 'Sin configurar'
                                }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-white/60">
                            {{
                                organizer.data_source?.provider_connection ??
                                '—'
                            }}
                        </td>
                        <td class="px-4 py-3 text-white/40">
                            {{ organizer.data_source?.status ?? '—' }}
                        </td>
                    </tr>
                    <tr v-if="!organizers.length">
                        <td
                            colspan="4"
                            class="px-4 py-10 text-center text-white/30"
                        >
                            Sin organizadores todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
