<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Zap } from '@lucide/vue';
import { ref, watch } from 'vue';
import { index } from '@/actions/App/Http/Controllers/OperatorController';
import PlateResultPanel from '@/components/operator/PlateResultPanel.vue';
import PlatePreviewCard from '@/components/plates/PlatePreviewCard.vue';
import type { PlateFace, PlateRenderMode } from '@/types/plate-studio';

const { plate } = defineProps<{
    plate: {
        id: number;
        serial_number: string;
        athlete_name: string;
        legacy_code: string | null;
        qr_url: string | null;
        status: string;
    };
}>();

const face = ref<PlateFace>('front');
const mode = ref<PlateRenderMode>('product');
const svg = ref<string | null>(null);
const loading = ref(false);

async function fetchPreview() {
    // See admin/plates/Show.vue — a relative-URL fetch() during Inertia SSR
    // has no browser location to resolve against and crashes the render.
    if (import.meta.env.SSR) {
        return;
    }

    loading.value = true;

    try {
        const response = await fetch(
            `/admin/plates/${plate.id}/export/${face.value}/svg?mode=${mode.value}`,
        );
        svg.value = response.ok ? await response.text() : null;
    } finally {
        loading.value = false;
    }
}

watch([face, mode], fetchPreview, { immediate: true });
</script>

<template>
    <Head title="Placa rápida generada" />

    <div class="min-h-svh p-4 md:p-8">
        <div class="mx-auto max-w-4xl">
            <Link
                :href="index().url"
                class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> Volver al operador
            </Link>

            <div class="mt-6 grid gap-8 sm:grid-cols-[1fr_360px]">
                <div>
                    <h1
                        class="flex items-center gap-2 text-2xl font-bold text-foreground"
                    >
                        <Zap class="size-5 text-fl-gold-ink" />
                        {{ plate.athlete_name }}
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Placa rápida — sin cuenta vinculada todavía.
                    </p>

                    <PlateResultPanel
                        class="mt-6"
                        :plate="plate"
                        :generate-another-href="index().url"
                    />
                </div>

                <div class="flex justify-center">
                    <PlatePreviewCard
                        v-model:face="face"
                        v-model:mode="mode"
                        :svg="svg"
                        :warnings="[]"
                        :loading="loading"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
