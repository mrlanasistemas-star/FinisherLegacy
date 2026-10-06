<script setup lang="ts">
/**
 * Interactive Legacy Plate viewer — drag to tilt, flip between the
 * printed FRONT and BACK, spin, reset. Both faces are drawn by
 * PlatePrintFace in real millimetres (the same geometry as the print),
 * with the athlete's real data. The plate carries an NFC chip (no printed
 * QR), shown as a badge, never as part of the print.
 */
import { Nfc, RefreshCw, RotateCcw } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import PlatePrintFace from '@/components/plates/PlatePrintFace.vue';
import type {
    LegacyPlateModelData as PlateModelData,
    LegacyPlatePersonalization as PlatePersonalization,
} from '@/types/plates';

export type LegacyPlateModelData = PlateModelData;
export type LegacyPlatePersonalization = PlatePersonalization;

const props = withDefaults(
    defineProps<{
        model: LegacyPlateModelData | null;
        personalization?: LegacyPlatePersonalization;
        /** Kept for older callers; the back is now always the printed back. */
        backImageUrl?: string | null;
        interactive?: boolean;
        mode?: 'athlete' | 'admin' | 'store';
    }>(),
    {
        personalization: () => ({}),
        backImageUrl: null,
        interactive: true,
        mode: 'athlete',
    },
);

const showingBack = ref(false);
const tilt = ref({ x: -6, y: 12 });
const spinning = ref(false);
let spinRaf: number | null = null;
let dragStart: { x: number; y: number; tx: number; ty: number } | null = null;

const transform = computed(
    () =>
        `rotateX(${tilt.value.x}deg) rotateY(${tilt.value.y + (showingBack.value ? 180 : 0)}deg)`,
);

function onPointerDown(event: PointerEvent) {
    if (!props.interactive) {
        return;
    }

    stopSpin();
    dragStart = {
        x: event.clientX,
        y: event.clientY,
        tx: tilt.value.x,
        ty: tilt.value.y,
    };
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}

function onPointerMove(event: PointerEvent) {
    if (!dragStart) {
        return;
    }

    tilt.value = {
        x: Math.max(
            -35,
            Math.min(35, dragStart.tx - (event.clientY - dragStart.y) * 0.4),
        ),
        y: dragStart.ty + (event.clientX - dragStart.x) * 0.5,
    };
}

function onPointerUp() {
    dragStart = null;
}

function stopSpin() {
    spinning.value = false;

    if (spinRaf !== null) {
        cancelAnimationFrame(spinRaf);
    }

    spinRaf = null;
}

function toggleSpin() {
    if (spinning.value) {
        stopSpin();

        return;
    }

    spinning.value = true;
    const step = () => {
        tilt.value = { ...tilt.value, y: tilt.value.y + 0.6 };
        spinRaf = requestAnimationFrame(step);
    };
    spinRaf = requestAnimationFrame(step);
}

function reset() {
    stopSpin();
    tilt.value = { x: -6, y: 12 };
    showingBack.value = false;
}

onBeforeUnmount(stopSpin);

const aspect = computed(() =>
    props.model
        ? `${props.model.width_mm} / ${props.model.height_mm}`
        : '5 / 2',
);
</script>

<template>
    <div class="select-none">
        <div
            class="relative overflow-hidden rounded-2xl border border-border bg-gradient-to-b from-fl-cream/60 to-card p-6 sm:p-10"
        >
            <template v-if="model">
                <div
                    class="relative mx-auto w-full max-w-md touch-none"
                    style="perspective: 1400px"
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="onPointerUp"
                    @pointercancel="onPointerUp"
                >
                    <div
                        class="relative w-full transition-transform duration-500 ease-out"
                        :style="{
                            aspectRatio: aspect,
                            transform,
                            transformStyle: 'preserve-3d',
                            transitionDuration: spinning ? '0ms' : undefined,
                        }"
                    >
                        <div
                            class="absolute inset-0 drop-shadow-[0_22px_30px_rgb(23_23_20/0.28)]"
                            style="backface-visibility: hidden"
                        >
                            <PlatePrintFace
                                :model="model"
                                face="front"
                                :personalization="personalization"
                            />
                        </div>
                        <div
                            class="absolute inset-0 drop-shadow-[0_22px_30px_rgb(23_23_20/0.28)]"
                            style="
                                backface-visibility: hidden;
                                transform: rotateY(180deg);
                            "
                        >
                            <PlatePrintFace
                                :model="model"
                                face="back"
                                :personalization="personalization"
                            />
                        </div>
                    </div>
                </div>

                <div
                    class="mt-8 flex flex-wrap items-center justify-center gap-2"
                >
                    <div
                        class="inline-flex rounded-full border border-border bg-card p-1"
                        role="group"
                        aria-label="Cara de la placa"
                    >
                        <button
                            type="button"
                            class="rounded-full px-4 py-1.5 text-xs font-semibold transition-colors"
                            :class="
                                !showingBack
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground'
                            "
                            :aria-pressed="!showingBack"
                            @click="showingBack = false"
                        >
                            Frente
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-4 py-1.5 text-xs font-semibold transition-colors"
                            :class="
                                showingBack
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground'
                            "
                            :aria-pressed="showingBack"
                            @click="showingBack = true"
                        >
                            Reverso
                        </button>
                    </div>
                    <button
                        v-if="interactive"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3.5 py-2 text-xs font-medium hover:border-foreground/25"
                        :aria-pressed="spinning"
                        @click="toggleSpin"
                    >
                        <RefreshCw
                            class="size-3.5"
                            :class="spinning ? 'animate-spin' : ''"
                        />
                        Girar
                    </button>
                    <button
                        v-if="interactive"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3.5 py-2 text-xs font-medium hover:border-foreground/25"
                        @click="reset"
                    >
                        <RotateCcw class="size-3.5" />
                        Restablecer
                    </button>
                </div>

                <p
                    class="mt-4 flex items-center justify-center gap-2 text-xs text-muted-foreground"
                >
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-fl-cream px-2.5 py-1 font-semibold text-fl-gold-ink"
                    >
                        <Nfc class="size-3.5" />
                        Chip NFC
                    </span>
                    <span v-if="personalization.nfc_code" class="font-mono">{{
                        personalization.nfc_code
                    }}</span>
                    <span v-else>Acerca tu teléfono para abrir tu legado</span>
                </p>
                <p
                    v-if="interactive"
                    class="mt-2 text-center text-[11px] text-muted-foreground/80"
                >
                    Arrastra para inclinar · {{ model.name }} ·
                    {{ model.width_mm }}×{{ model.height_mm }} mm
                </p>
            </template>
            <p v-else class="py-10 text-center text-sm text-muted-foreground">
                Esta placa aún no tiene layout asignado.
            </p>
        </div>
    </div>
</template>
