<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { importStatus, statusClass, statusLabel } from '@/lib/statusLabels';
import { create, show } from '@/routes/imports';

const { imports } = defineProps<{
    imports: {
        id: number;
        event: string | null;
        edition: string | null;
        filename: string;
        status: string;
        total_rows: number | null;
        successful_rows: number;
        failed_rows: number;
        created_at: string | null;
    }[];
}>();
</script>

<template>
    <Head title="Importaciones" />

    <div class="p-4 md:p-6">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-bold text-foreground">Importaciones</h1>
            <Button
                as-child
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
            >
                <Link :href="create().url">
                    <Plus class="size-4" />
                    Nueva importación
                </Link>
            </Button>
        </div>

        <div class="space-y-2">
            <Link
                v-for="item in imports"
                :key="item.id"
                :href="show(item.id).url"
                class="fl-hover-glow flex items-center justify-between rounded-xl border border-border bg-card/40 p-4 transition-colors"
            >
                <div>
                    <p class="font-medium text-foreground">
                        {{ item.filename }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ item.event }} — {{ item.edition }} ·
                        {{ item.created_at }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-muted-foreground/80">
                        {{ item.successful_rows }} ok
                        <span v-if="item.failed_rows">
                            · {{ item.failed_rows }} con error</span
                        >
                    </span>
                    <Badge
                        variant="outline"
                        :class="statusClass(importStatus, item.status)"
                    >
                        {{ statusLabel(importStatus, item.status) }}
                    </Badge>
                </div>
            </Link>

            <p
                v-if="!imports.length"
                class="py-16 text-center text-sm text-muted-foreground/80"
            >
                Aún no has hecho ninguna importación.
            </p>
        </div>
    </div>
</template>
