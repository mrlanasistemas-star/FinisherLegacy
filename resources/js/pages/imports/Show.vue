<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted } from 'vue';
import { Progress } from '@/components/ui/progress';
import { importStatus, statusLabel } from '@/lib/statusLabels';

const { importData, errors } = defineProps<{
    importData: {
        id: number;
        event: string | null;
        edition: string | null;
        filename: string;
        status: string;
        total_rows: number | null;
        processed_rows: number;
        successful_rows: number;
        failed_rows: number;
    };
    errors: { row_number: number; error_message: string }[];
}>();

const progressPercent = importData.total_rows
    ? Math.round((importData.processed_rows / importData.total_rows) * 100)
    : 0;

let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (importData.status === 'pending' || importData.status === 'processing') {
        timer = setInterval(() => {
            router.reload({ only: ['importData', 'errors'] });
        }, 2000);
    }
});

onBeforeUnmount(() => {
    if (timer) {
        clearInterval(timer);
    }
});
</script>

<template>
    <Head :title="`Importación — ${importData.filename}`" />

    <div class="mx-auto max-w-2xl space-y-6 p-4 md:p-6">
        <div>
            <h1 class="text-xl font-bold text-foreground">
                {{ importData.filename }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ importData.event }} — {{ importData.edition }}
            </p>
        </div>

        <div class="rounded-xl border border-border bg-card/40 p-5">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="text-muted-foreground">{{
                    statusLabel(importStatus, importData.status)
                }}</span>
                <span class="text-muted-foreground/80"
                    >{{ importData.processed_rows }} /
                    {{ importData.total_rows ?? '…' }}</span
                >
            </div>
            <Progress :model-value="progressPercent" />

            <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-xs text-muted-foreground/80 uppercase">
                        Correctos
                    </p>
                    <p class="text-lg font-semibold text-emerald-700">
                        {{ importData.successful_rows }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground/80 uppercase">
                        Con errores
                    </p>
                    <p class="text-lg font-semibold text-red-700">
                        {{ importData.failed_rows }}
                    </p>
                </div>
            </div>
        </div>

        <div v-if="errors.length">
            <h2
                class="mb-2 text-sm font-semibold text-muted-foreground uppercase"
            >
                Errores ({{ errors.length }})
            </h2>
            <div class="max-h-80 space-y-1 overflow-y-auto">
                <div
                    v-for="error in errors"
                    :key="error.row_number"
                    class="rounded-md border border-red-500/20 bg-red-500/5 px-3 py-2 text-xs text-red-700"
                >
                    Fila {{ error.row_number }}: {{ error.error_message }}
                </div>
            </div>
        </div>
    </div>
</template>
