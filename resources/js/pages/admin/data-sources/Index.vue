<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Database } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { providerConnectionStatus, statusLabel } from '@/lib/statusLabels';

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
            <h1
                class="flex items-center gap-2 text-xl font-bold text-foreground"
            >
                <Database class="size-5 text-fl-gold-ink" />
                Fuentes de datos
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Cómo recibe información cada organizador — un organizador puede
                tener varias. Agrega/edita desde su pestaña "Datos /
                Integración".
            </p>
        </div>

        <div class="space-y-4">
            <div
                v-for="organizer in organizers"
                :key="organizer.id"
                class="rounded-xl border border-border bg-card/20 p-5"
            >
                <Link
                    :href="`/admin/organizers/${organizer.id}`"
                    class="font-medium text-foreground hover:text-fl-gold-ink"
                    >{{ organizer.name }}</Link
                >

                <div
                    v-if="organizer.sources.length"
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <div
                        v-for="source in organizer.sources"
                        :key="source.id"
                        class="flex items-center gap-2 rounded-lg border border-border bg-background/30 px-3 py-2 text-xs"
                        :class="!source.active ? 'opacity-40' : ''"
                    >
                        <Badge
                            variant="outline"
                            class="border-border text-muted-foreground"
                            >{{ typeLabels[source.type] }}</Badge
                        >
                        <span class="text-muted-foreground">{{
                            source.name ?? purposeLabels[source.purpose]
                        }}</span>
                        <span class="text-muted-foreground/80"
                            >· {{ purposeLabels[source.purpose] }}</span
                        >
                        <Badge
                            v-if="source.is_default"
                            variant="outline"
                            class="border-fl-gold/30 text-fl-gold-ink"
                            >Predeterminada</Badge
                        >
                        <span
                            v-if="source.provider_connection"
                            class="text-muted-foreground/80"
                            >{{
                                statusLabel(
                                    providerConnectionStatus,
                                    source.provider_connection.status,
                                )
                            }}</span
                        >
                    </div>
                </div>
                <p v-else class="mt-3 text-xs text-muted-foreground/80">
                    Sin fuentes de datos configuradas.
                </p>
            </div>

            <div
                v-if="!organizers.length"
                class="rounded-xl border border-dashed border-border bg-card/20 p-8 text-center text-sm text-muted-foreground/80"
            >
                Sin organizadores todavía.
            </div>
        </div>
    </div>
</template>
