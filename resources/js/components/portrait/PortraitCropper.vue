<script setup lang="ts">
/**
 * Fixed-aspect crop of the chosen photo for the template's photo box.
 * Drag (mouse / touch / pen) or arrow keys to move, slider or +/- to
 * zoom. Emits a normalised rectangle; the server re-checks the ratio.
 */
import { Minus, Move, Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { PortraitCrop } from './types';

const props = defineProps<{
    src: string;
    /** Photo box width / height. */
    aspect: number;
    /** Oriented image width / height. */
    imageAspect: number;
}>();

const crop = defineModel<PortraitCrop | null>({ required: true });

const zoom = ref(1);
const center = ref({ x: 0.5, y: 0.5 });
const frame = ref<HTMLDivElement | null>(null);

const base = computed(() => {
    const w = Math.min(1, props.aspect / props.imageAspect);

    return { w, h: (w * props.imageAspect) / props.aspect };
});

function clamp(v: number, min: number, max: number) {
    return Math.min(max, Math.max(min, v));
}

function emit() {
    const w = base.value.w / zoom.value;
    const h = base.value.h / zoom.value;
    const x = clamp(center.value.x - w / 2, 0, 1 - w);
    const y = clamp(center.value.y - h / 2, 0, 1 - h);
    center.value = { x: x + w / 2, y: y + h / 2 };
    crop.value = { x, y, w, h };
}

// New photo → recentre. New template (another photo box ratio) → keep
// where the user was looking and only re-fit the window.
watch(
    () => [props.src, props.imageAspect],
    () => {
        zoom.value = 1;
        center.value = { x: 0.5, y: 0.5 };
        emit();
    },
    { immediate: true },
);

watch(() => props.aspect, emit);

watch(zoom, emit);

let dragging: { id: number; x: number; y: number } | null = null;

function onDown(event: PointerEvent) {
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    dragging = { id: event.pointerId, x: event.clientX, y: event.clientY };
}

function onMove(event: PointerEvent) {
    if (!dragging || dragging.id !== event.pointerId || !frame.value) {
        return;
    }

    const rect = frame.value.getBoundingClientRect();
    center.value = {
        x: center.value.x + (event.clientX - dragging.x) / rect.width,
        y: center.value.y + (event.clientY - dragging.y) / rect.height,
    };
    dragging = { ...dragging, x: event.clientX, y: event.clientY };
    emit();
}

function onUp() {
    dragging = null;
}

function onKey(event: KeyboardEvent) {
    const step = 0.02;
    const moves: Record<string, [number, number]> = {
        ArrowLeft: [-step, 0],
        ArrowRight: [step, 0],
        ArrowUp: [0, -step],
        ArrowDown: [0, step],
    };

    if (moves[event.key]) {
        event.preventDefault();
        center.value = {
            x: center.value.x + moves[event.key][0],
            y: center.value.y + moves[event.key][1],
        };
        emit();
    } else if (event.key === '+' || event.key === '=') {
        zoom.value = clamp(zoom.value + 0.1, 1, 4);
    } else if (event.key === '-') {
        zoom.value = clamp(zoom.value - 0.1, 1, 4);
    }
}
</script>

<template>
    <div>
        <div
            ref="frame"
            class="relative overflow-hidden rounded-xl bg-fl-black"
            :style="{ aspectRatio: String(imageAspect) }"
        >
            <img
                :src="src"
                alt="Foto a recortar"
                class="block size-full object-contain"
                draggable="false"
            />
            <div
                v-if="crop"
                class="crop-window absolute cursor-move touch-none rounded-sm outline-2 outline-white focus-visible:outline-fl-gold"
                :style="{
                    left: `${crop.x * 100}%`,
                    top: `${crop.y * 100}%`,
                    width: `${crop.w * 100}%`,
                    height: `${crop.h * 100}%`,
                }"
                tabindex="0"
                role="slider"
                aria-label="Encuadre de la foto. Usa las flechas para moverlo y + o - para acercar."
                :aria-valuenow="Math.round(zoom * 100)"
                aria-valuemin="100"
                aria-valuemax="400"
                @pointerdown="onDown"
                @pointermove="onMove"
                @pointerup="onUp"
                @pointercancel="onUp"
                @keydown="onKey"
            >
                <span
                    class="pointer-events-none absolute inset-0 grid grid-cols-3 grid-rows-3"
                    aria-hidden="true"
                >
                    <span
                        v-for="n in 9"
                        :key="n"
                        class="border-[0.5px] border-white/25"
                    />
                </span>
                <Move
                    class="pointer-events-none absolute top-1/2 left-1/2 size-6 -translate-1/2 text-white/80 drop-shadow"
                    aria-hidden="true"
                />
            </div>
        </div>

        <div class="mt-3 flex items-center gap-3">
            <button
                type="button"
                class="rounded-full p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                aria-label="Alejar"
                @click="zoom = clamp(zoom - 0.2, 1, 4)"
            >
                <Minus class="size-4" />
            </button>
            <input
                v-model.number="zoom"
                type="range"
                min="1"
                max="4"
                step="0.01"
                class="h-2 flex-1 accent-[var(--fl-gold-dim)]"
                aria-label="Acercar foto"
            />
            <button
                type="button"
                class="rounded-full p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                aria-label="Acercar"
                @click="zoom = clamp(zoom + 0.2, 1, 4)"
            >
                <Plus class="size-4" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.crop-window {
    box-shadow: 0 0 0 9999px rgb(11 11 12 / 0.6);
}
</style>
