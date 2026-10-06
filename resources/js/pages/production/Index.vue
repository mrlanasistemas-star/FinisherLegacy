<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Ban, Download, PackageOpen, QrCode } from '@lucide/vue';
import { ref } from 'vue';
import { exportMethod as exportFace } from '@/actions/App/Http/Controllers/Admin/PlateController';
import {
    backComplete,
    backStart,
    cancel,
    deliver,
    flipConfirm,
    frontComplete,
    frontStart,
    prepare,
    qrVerify,
} from '@/actions/App/Http/Controllers/ProductionController';
import HelpPopover from '@/components/HelpPopover.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';

type ManualAction =
    | 'prepare'
    | 'start_front'
    | 'complete_front'
    | 'confirm_flip'
    | 'start_back'
    | 'complete_back'
    | 'verify_qr'
    | 'deliver'
    | null;

type Card = {
    id: number;
    status: string;
    manual_action: ManualAction;
    serial_number: string;
    athlete_name: string;
    bib_number: string | null;
    event_name: string | null;
    legacy_code: string | null;
    generation_mode: string;
    updated_at: string | null;
    download_format: string;
    download_dpi: number;
    device: { name: string; online: boolean } | null;
    checklist: Record<'front' | 'back' | 'qr', boolean>;
    error_message: string | null;
};

const statusLabels: Record<string, string> = {
    queued: 'Pendiente',
    assigned: 'Asignada',
    preparing: 'Preparando',
    engraving_front: 'Imprimiendo frente',
    awaiting_flip: 'VOLTEA LA PLACA',
    engraving_back: 'Imprimiendo reverso',
    verifying_qr: 'Verificando NFC',
    ready: 'Lista',
    delivered: 'Entregada',
    failed: 'Falló',
    cancelled: 'Cancelada',
};

const actionMeta: Record<
    Exclude<ManualAction, null>,
    { fn: (job: number) => { url: string; method: string }; label: string }
> = {
    prepare: { fn: prepare, label: 'Iniciar preparación' },
    start_front: { fn: frontStart, label: 'Iniciar frente' },
    complete_front: { fn: frontComplete, label: 'Confirmar frente impreso' },
    confirm_flip: { fn: flipConfirm, label: 'Confirmar placa volteada' },
    start_back: { fn: backStart, label: 'Iniciar reverso' },
    complete_back: { fn: backComplete, label: 'Confirmar reverso impreso' },
    verify_qr: { fn: qrVerify, label: 'Verificar NFC' },
    deliver: { fn: deliver, label: 'Marcar entregada' },
};

function quickDownloadUrl(card: Card): string {
    return exportFace.url([card.id, 'front', card.download_format], {
        query:
            card.download_format === 'png'
                ? { dpi: String(card.download_dpi) }
                : {},
    });
}

const { columns } = defineProps<{
    columns: Record<
        'pending' | 'processing' | 'ready' | 'delivered' | 'issue',
        Card[]
    >;
}>();

const columnTitles: Record<string, string> = {
    pending: 'Pendiente',
    processing: 'En proceso',
    ready: 'Lista',
    delivered: 'Entregada',
    issue: 'Incidencia',
};

const pendingIds = ref(new Set<number>());
const qrDialogOpen = ref(false);
const qrDialogCard = ref<Card | null>(null);
const qrValue = ref('');

function runAction(card: Card) {
    if (!card.manual_action) {
        return;
    }

    if (card.manual_action === 'verify_qr') {
        qrDialogCard.value = card;
        qrValue.value = '';
        qrDialogOpen.value = true;

        return;
    }

    const { fn } = actionMeta[card.manual_action];
    const { url, method } = fn(card.id);
    pendingIds.value.add(card.id);
    router.visit(url, {
        method: method as 'patch' | 'post',
        preserveScroll: true,
        onFinish: () => pendingIds.value.delete(card.id),
    });
}

function submitQr() {
    if (!qrDialogCard.value) {
        return;
    }

    const jobId = qrDialogCard.value.id;
    pendingIds.value.add(jobId);

    router.post(
        qrVerify(jobId).url,
        { decoded_value: qrValue.value },
        {
            preserveScroll: true,
            onSuccess: () => (qrDialogOpen.value = false),
            onFinish: () => pendingIds.value.delete(jobId),
        },
    );
}

function cancelJob(jobId: number) {
    pendingIds.value.add(jobId);
    router.patch(
        cancel(jobId).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => pendingIds.value.delete(jobId),
        },
    );
}
</script>

<template>
    <Head title="Producción" />

    <div class="min-h-svh p-4 md:p-6">
        <h1
            class="mb-6 flex items-center gap-1.5 text-xl font-bold text-foreground"
        >
            Producción
            <HelpPopover
                title="Flujo físico de producción"
                text="Cada tarjeta es un trabajo de producción — Asignada → Preparando → Imprimiendo frente → Voltea la placa → Imprimiendo reverso → Verificando NFC → Lista → Entregada. Una estación (Device API) o un operador manual pueden avanzarlo, con las mismas reglas."
            />
        </h1>

        <div
            class="grid grid-cols-1 gap-4 overflow-x-auto sm:grid-cols-2 lg:grid-cols-5"
        >
            <div
                v-for="key in [
                    'pending',
                    'processing',
                    'ready',
                    'delivered',
                    'issue',
                ] as const"
                :key="key"
                class="rounded-xl border border-border bg-card/30 p-3"
            >
                <p
                    class="mb-3 flex items-center gap-1.5 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ columnTitles[key] }}
                    <span class="text-muted-foreground/80"
                        >({{ columns[key]?.length ?? 0 }})</span
                    >
                </p>

                <div class="relative space-y-2">
                    <div
                        v-for="card in columns[key]"
                        :key="card.id"
                        class="fl-hover-lift rounded-lg border border-border bg-background/60 p-3 transition-colors hover:border-fl-gold/25"
                        :class="{ 'opacity-50': pendingIds.has(card.id) }"
                    >
                        <p class="truncate text-sm font-medium text-foreground">
                            {{ card.athlete_name }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground/80">
                            {{
                                [
                                    card.bib_number
                                        ? `#${card.bib_number}`
                                        : null,
                                    card.event_name,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </p>
                        <div class="mt-1 flex items-center justify-between">
                            <p
                                class="font-mono text-[10px] text-muted-foreground/80"
                            >
                                {{ card.serial_number }}
                            </p>
                            <a
                                :href="quickDownloadUrl(card)"
                                :title="`Descargar ${card.download_format.toUpperCase()} (frente) — respaldo manual`"
                                class="text-muted-foreground/80 hover:text-fl-gold-ink"
                                target="_blank"
                            >
                                <Download class="size-3" />
                            </a>
                        </div>

                        <p
                            v-if="key === 'processing' || key === 'issue'"
                            class="mt-2 text-center text-sm font-bold text-fl-gold-ink"
                        >
                            {{ statusLabels[card.status] ?? card.status }}
                        </p>

                        <p
                            v-if="card.device"
                            class="mt-1 flex items-center justify-center gap-1 text-[10px] text-muted-foreground/80"
                        >
                            <span
                                class="size-1.5 rounded-full"
                                :class="
                                    card.device.online
                                        ? 'bg-emerald-400'
                                        : 'bg-foreground/10'
                                "
                            />
                            Estación: {{ card.device.name }}
                        </p>

                        <p
                            v-if="card.error_message"
                            class="mt-1 text-[10px] text-red-700"
                        >
                            {{ card.error_message }}
                        </p>

                        <div class="mt-2 flex gap-1.5">
                            <button
                                v-if="card.manual_action"
                                type="button"
                                class="fl-focus-glow flex flex-1 items-center justify-center gap-1 rounded-md bg-fl-gold px-2 py-1.5 text-[11px] font-medium text-fl-black transition-transform active:scale-95 disabled:pointer-events-none disabled:opacity-60"
                                :disabled="pendingIds.has(card.id)"
                                @click="runAction(card)"
                            >
                                <Spinner
                                    v-if="pendingIds.has(card.id)"
                                    class="size-3"
                                />
                                <template v-else>
                                    <QrCode
                                        v-if="
                                            card.manual_action === 'verify_qr'
                                        "
                                        class="size-3"
                                    />
                                    {{ actionMeta[card.manual_action].label }}
                                </template>
                            </button>
                            <button
                                v-if="key === 'pending' || key === 'processing'"
                                type="button"
                                class="fl-focus-glow flex items-center justify-center rounded-md border border-border px-2 py-1.5 text-muted-foreground/80 transition-colors hover:border-red-400/30 hover:text-red-700 disabled:pointer-events-none disabled:opacity-60"
                                :disabled="pendingIds.has(card.id)"
                                aria-label="Cancelar"
                                @click="cancelJob(card.id)"
                            >
                                <Ban class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!columns[key]?.length"
                    class="flex flex-col items-center gap-1.5 py-8 text-center"
                >
                    <PackageOpen class="size-5 text-muted-foreground/80" />
                    <p class="text-xs text-muted-foreground/80">
                        Sin placas aquí
                    </p>
                </div>
            </div>
        </div>

        <Dialog v-model:open="qrDialogOpen">
            <DialogContent class="border-border bg-card text-foreground">
                <DialogHeader>
                    <DialogTitle>Verificar NFC</DialogTitle>
                </DialogHeader>
                <div class="space-y-2">
                    <p class="text-xs text-muted-foreground">
                        Lee el chip NFC de
                        {{ qrDialogCard?.serial_number }} con un teléfono y pega
                        la dirección que abre.
                    </p>
                    <Input
                        v-model="qrValue"
                        placeholder="https://finisherlegacy.com/l/FL-XXXXXXX"
                        class="border-border bg-background text-foreground"
                        @keyup.enter="submitQr"
                    />
                </div>
                <DialogFooter>
                    <Button
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="!qrValue"
                        @click="submitQr"
                    >
                        Verificar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
