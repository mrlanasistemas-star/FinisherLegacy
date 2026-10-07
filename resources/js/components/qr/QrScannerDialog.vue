<script setup lang="ts">
/**
 * Generic camera QR scanner used both by "Agregar medalla" (scan a plate's
 * Legacy Code QR to skip typing event/time/pace) and, later, Event OS. It
 * only understands one payload shape: our stable public Legacy Code URL
 * (`/l/{code}`) — see LegacyCodeQrService on the backend. Anything else is
 * rejected with a friendly message rather than silently ignored.
 */
import { router } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import QrScanner from 'qr-scanner';
import QrScannerWorkerPath from 'qr-scanner/qr-scanner-worker.min.js?url';
import { nextTick, onBeforeUnmount, useTemplateRef, watch } from 'vue';
import { ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

QrScanner.WORKER_PATH = QrScannerWorkerPath;

const open = defineModel<boolean>('open', { default: false });

const videoRef = useTemplateRef<HTMLVideoElement>('video');
const error = ref<string | null>(null);
let scanner: QrScanner | null = null;

function extractLegacyCode(rawValue: string): string | null {
    try {
        const url = new URL(rawValue, window.location.origin);
        const match = url.pathname.match(/^\/l\/([A-Za-z0-9-]+)/);

        return match ? match[1] : null;
    } catch {
        return null;
    }
}

function handleDecode(result: { data: string }) {
    const code = extractLegacyCode(result.data);

    if (!code) {
        error.value =
            'Este código QR no pertenece a una placa Finisher Legacy.';

        return;
    }

    stop();
    open.value = false;
    router.visit(`/l/${code}`);
}

async function start() {
    error.value = null;
    await nextTick();

    if (!videoRef.value) {
        return;
    }

    scanner = new QrScanner(videoRef.value, handleDecode, {
        highlightScanRegion: true,
        highlightCodeOutline: true,
        maxScansPerSecond: 5,
    });

    try {
        await scanner.start();
    } catch {
        error.value =
            'No pudimos acceder a tu cámara. Revisa los permisos del navegador e inténtalo de nuevo.';
    }
}

function stop() {
    scanner?.stop();
    scanner?.destroy();
    scanner = null;
}

watch(open, (isOpen) => (isOpen ? start() : stop()));

onBeforeUnmount(stop);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Escanea tu Legacy Code</DialogTitle>
                <DialogDescription>
                    Apunta la cámara a un QR de Legacy Code (placas anteriores o
                    tarjetas impresas). Cargaremos el evento, el tiempo y el
                    ritmo por ti. Con la Legacy Plate actual basta acercar el
                    teléfono al frente (NFC).
                </DialogDescription>
            </DialogHeader>

            <div
                class="relative aspect-square overflow-hidden rounded-xl border border-border bg-black"
            >
                <video
                    ref="video"
                    class="size-full object-cover"
                    muted
                    playsinline
                />
            </div>

            <p
                v-if="error"
                class="fl-error-shake flex items-start gap-2 text-sm text-red-500"
            >
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                {{ error }}
            </p>
        </DialogContent>
    </Dialog>
</template>
