<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Download,
    History,
    RefreshCw,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { reprint as reprintAction } from '@/actions/App/Http/Controllers/Admin/PlateController';
import DownloadPlateDialog from '@/components/plates/DownloadPlateDialog.vue';
import PlatePreviewCard from '@/components/plates/PlatePreviewCard.vue';
import LegacyPlateViewer from '@/components/shared/LegacyPlateViewer.vue';
import type {
    LegacyPlateModelData,
    LegacyPlatePersonalization,
} from '@/components/shared/LegacyPlateViewer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    plateStatus,
    productionJobStatus,
    reprintStatus,
    statusClass,
    statusLabel,
} from '@/lib/statusLabels';
import type { PlateFace, PlateRenderMode } from '@/types/plate-studio';

const {
    plate,
    legacyPlateModel,
    personalization,
    resultComparison,
    splits,
    reprints,
    activities,
} = defineProps<{
    plate: {
        id: number;
        serial_number: string;
        athlete_name: string;
        bib_number: string | null;
        event_name: string | null;
        race_name: string | null;
        official_time: string | null;
        pace: string | null;
        status: string;
        generation_mode: string;
        legacy_code: string | null;
        qr_url: string | null;
        owner: string | null;
        template: { name: string; version: number | null } | null;
        production_job: { status: string; queued_at: string | null } | null;
        can_reprint: boolean;
    };
    legacyPlateModel: LegacyPlateModelData | null;
    personalization: LegacyPlatePersonalization;
    resultComparison: {
        original: { official_time: string | null; pace: string | null };
        current: { official_time: string | null; pace: string | null };
    } | null;
    splits: {
        label: string;
        distance: string | null;
        segment_time: string | null;
        elapsed_time: string | null;
        pace: string | null;
    }[];
    reprints: {
        id: number;
        reason: string;
        status: string;
        requested_by: string | null;
        created_at: string | null;
    }[];
    activities: {
        id: number;
        event: string | null;
        causer: string;
        created_at: string | null;
    }[];
}>();

const generationModeLabel: Record<string, string> = {
    integrated: 'Integrada',
    quick: 'Rápida',
};

const face = ref<PlateFace>('front');
const mode = ref<PlateRenderMode>('product');
const svg = ref<string | null>(null);
const loading = ref(false);
const downloadOpen = ref(false);
const reprintOpen = ref(false);
const reprintReason = ref('');
const useOriginal = ref(true);
const submittingReprint = ref(false);

async function fetchPreview() {
    // Inertia SSR renders this page's first paint on the server, where
    // `fetch()` can't resolve a relative URL (no browser location to
    // resolve against) — skip there and let the client fetch it after
    // hydration instead of crashing the SSR render.
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

function submitReprint() {
    submittingReprint.value = true;
    router.post(
        reprintAction.url(plate.id),
        { reason: reprintReason.value, use_original: useOriginal.value },
        {
            preserveScroll: true,
            onFinish: () => {
                submittingReprint.value = false;
                reprintOpen.value = false;
                reprintReason.value = '';
            },
        },
    );
}
</script>

<template>
    <Head :title="`Placa ${plate.serial_number}`" />

    <div class="space-y-6 p-4 md:p-8">
        <Link
            href="/admin/plates"
            class="inline-flex items-center gap-1.5 text-sm text-white/50 hover:text-white"
        >
            <ArrowLeft class="size-4" /> Volver a placas
        </Link>

        <div class="grid gap-8 lg:grid-cols-[3fr_2fr]">
            <div>
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-white">
                            {{ plate.athlete_name }}
                        </h1>
                        <p class="mt-1 font-mono text-xs text-white/40">
                            {{ plate.serial_number }}
                        </p>
                    </div>
                    <Badge
                        variant="outline"
                        :class="statusClass(plateStatus, plate.status)"
                        >{{ statusLabel(plateStatus, plate.status) }}</Badge
                    >
                </div>

                <div
                    class="mt-6 grid grid-cols-2 gap-4 rounded-xl border border-white/10 bg-fl-graphite/40 p-5 text-sm lg:grid-cols-4"
                >
                    <div>
                        <p class="text-xs text-white/40 uppercase">Evento</p>
                        <p class="text-white">{{ plate.event_name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-white/40 uppercase">Carrera</p>
                        <p class="text-white">{{ plate.race_name ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-white/40 uppercase">Tiempo</p>
                        <p class="font-mono text-fl-gold">
                            {{ plate.official_time ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-white/40 uppercase">Modo</p>
                        <p class="text-white">
                            {{
                                generationModeLabel[plate.generation_mode] ??
                                plate.generation_mode
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-white/40 uppercase">
                            Legacy Code
                        </p>
                        <p class="font-mono text-white">
                            {{ plate.legacy_code ?? '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-white/40 uppercase">Dueño</p>
                        <p class="text-white">
                            {{ plate.owner ?? 'Sin reclamar' }}
                        </p>
                    </div>
                    <div v-if="plate.template">
                        <p class="text-xs text-white/40 uppercase">Molde</p>
                        <p class="text-white">
                            {{ plate.template.name }} V{{
                                plate.template.version
                            }}
                        </p>
                    </div>
                    <div v-if="plate.production_job">
                        <p class="text-xs text-white/40 uppercase">
                            Producción
                        </p>
                        <p class="text-white">
                            {{
                                statusLabel(
                                    productionJobStatus,
                                    plate.production_job.status,
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="resultComparison"
                    class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 text-sm"
                >
                    <p
                        class="mb-2 flex items-center gap-2 font-medium text-amber-400"
                    >
                        <AlertTriangle class="size-4" />
                        Resultado actualizado
                    </p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-white/40 uppercase">
                                Grabado en la placa
                            </p>
                            <p class="font-mono text-white">
                                {{ resultComparison.original.official_time }} ·
                                {{ resultComparison.original.pace }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-white/40 uppercase">
                                Resultado actual
                            </p>
                            <p class="font-mono text-white">
                                {{ resultComparison.current.official_time }} ·
                                {{ resultComparison.current.pace }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-white/50">
                        Lo grabado en la placa no se altera automáticamente. Si
                        reimprimes, puedes elegir mantener los datos originales
                        o actualizar con el resultado actual.
                    </p>
                </div>

                <div v-if="splits.length" class="mt-4 space-y-2">
                    <h2 class="text-sm font-semibold text-white">Parciales</h2>
                    <div
                        class="overflow-x-auto rounded-xl border border-white/10 bg-fl-graphite/40"
                    >
                        <table class="w-full min-w-[420px] text-left text-sm">
                            <thead>
                                <tr class="text-xs text-white/40 uppercase">
                                    <th class="px-4 py-2 font-normal">
                                        Parcial
                                    </th>
                                    <th class="px-4 py-2 font-normal">
                                        Distancia
                                    </th>
                                    <th class="px-4 py-2 font-normal">
                                        Tiempo
                                    </th>
                                    <th class="px-4 py-2 font-normal">Ritmo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="split in splits"
                                    :key="split.label"
                                    class="border-t border-white/5"
                                >
                                    <td class="px-4 py-2 text-white">
                                        {{ split.label }}
                                    </td>
                                    <td class="px-4 py-2 text-white/60">
                                        {{ split.distance ?? '—' }}
                                    </td>
                                    <td
                                        class="px-4 py-2 font-mono text-fl-gold"
                                    >
                                        {{
                                            split.segment_time ??
                                            split.elapsed_time ??
                                            '—'
                                        }}
                                    </td>
                                    <td class="px-4 py-2 text-white/60">
                                        {{ split.pace ?? '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-2">
                    <Button
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        @click="downloadOpen = true"
                    >
                        <Download class="size-4" />
                        Descargar / regenerar archivos
                    </Button>
                    <Button
                        v-if="plate.can_reprint"
                        variant="outline"
                        class="border-white/15 text-white hover:bg-white/10"
                        @click="reprintOpen = true"
                    >
                        <RefreshCw class="size-4" />
                        Reimprimir
                    </Button>
                </div>
                <p v-if="!plate.can_reprint" class="mt-2 text-xs text-white/40">
                    Solo se pueden reimprimir placas en estado "lista" o
                    "entregada".
                </p>

                <Separator class="my-6 bg-white/10" />

                <div v-if="reprints.length" class="mb-6 space-y-2">
                    <h2
                        class="flex items-center gap-2 text-sm font-semibold text-white"
                    >
                        <History class="size-4" />
                        Reimpresiones
                    </h2>
                    <div
                        v-for="r in reprints"
                        :key="r.id"
                        class="rounded-lg border border-white/10 p-3 text-sm"
                    >
                        <p class="text-white">{{ r.reason }}</p>
                        <p class="text-xs text-white/40">
                            {{ r.requested_by }} — {{ r.created_at }} —
                            {{ statusLabel(reprintStatus, r.status) }}
                        </p>
                    </div>
                </div>

                <div v-if="activities.length" class="space-y-2">
                    <h2 class="text-sm font-semibold text-white">Auditoría</h2>
                    <ul class="space-y-1 text-xs text-white/50">
                        <li v-for="a in activities" :key="a.id">
                            {{ a.created_at }} — {{ a.causer }} —
                            {{ a.event ?? 'actualización' }}
                        </li>
                    </ul>
                </div>
            </div>

            <div class="space-y-6 lg:sticky lg:top-8 lg:self-start">
                <LegacyPlateViewer
                    v-if="legacyPlateModel"
                    :model="legacyPlateModel"
                    :personalization="personalization"
                    mode="admin"
                />
                <div class="flex flex-col items-center">
                    <h2
                        class="mb-3 self-start text-sm font-semibold text-white/70"
                    >
                        Grabado real (export de producción)
                    </h2>
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

        <DownloadPlateDialog v-model:open="downloadOpen" :plate-id="plate.id" />

        <Dialog v-model:open="reprintOpen">
            <DialogContent
                class="dark border-white/10 bg-fl-graphite text-white"
            >
                <DialogHeader>
                    <DialogTitle>Reimprimir placa</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitReprint">
                    <div class="grid gap-2">
                        <Label>Motivo</Label>
                        <Textarea
                            v-model="reprintReason"
                            required
                            class="bg-fl-black"
                        />
                    </div>
                    <div class="flex items-center gap-2">
                        <Checkbox
                            :model-value="useOriginal"
                            @update:model-value="(v) => (useOriginal = !!v)"
                        />
                        <Label class="text-sm text-white/80"
                            >Mantener los datos originales grabados</Label
                        >
                    </div>
                    <p class="text-xs text-white/40">
                        La reimpresión siempre usa el mismo Legacy Code — nunca
                        se genera uno nuevo.
                    </p>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                            :disabled="submittingReprint"
                        >
                            <Spinner v-if="submittingReprint" />
                            Confirmar reimpresión
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
