<script setup lang="ts">
/**
 * Interactive Legacy Plate viewer (brief §7-§18, §70-§73) — a "product
 * configurator" feel (drag to tilt, flip front/back, zoom, reset) without a
 * 3D engine. Deliberate choice, not a shortcut: we don't have real GLB/GLTF
 * models yet (brief §12-§13 — the physical shape/mechanism is
 * pre-manufactured, decided by a supplier we haven't received CAD from),
 * so a Three.js/TresJS scene today would either render a fake-looking
 * placeholder mesh or just wrap this exact image+overlay anyway. This
 * renders the model's real `preview_image_url` (or a plausible brushed-
 * metal fallback when no photo has been uploaded yet — never an invented
 * shape) with the engraving text placed via an SVG overlay using the
 * model's *real* mm coordinates (LegacyPlateModel.engraving_area +
 * LegacyPlateModelField, the same geometry production actually engraves
 * by), so what the athlete/admin/shopper sees is dimensionally honest, not
 * decorative.
 *
 * GLB readiness (brief §71): the front/back faces below are the only
 * thing that would change — swap the <image>/gradient fill on each face
 * for a <canvas>-mounted Three.js/TresJS renderer, keep the same
 * perspective/drag/flip shell and the same props contract. Nothing above
 * this component (Mi Legado, admin model preview, store product page)
 * would need to change.
 */
import { Maximize2, RotateCcw, RotateCw as SpinIcon } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';

export type LegacyPlateFieldKey =
    'athlete_name' | 'race_label' | 'official_time' | 'pace' | 'qr';

export type LegacyPlateModelField = {
    field_key: LegacyPlateFieldKey;
    x: number;
    y: number;
    width: number;
    height: number;
    font_size: number | null;
    alignment: 'left' | 'center' | 'right';
    visible?: boolean;
};

export type LegacyPlateModelData = {
    name: string;
    slug: string;
    width_mm: number;
    height_mm: number;
    engraving_area: { x: number; y: number; width: number; height: number };
    preview_image_url: string | null;
    fields: LegacyPlateModelField[];
};

export type LegacyPlatePersonalization = {
    athlete_name?: string | null;
    race_label?: string | null;
    official_time?: string | null;
    pace?: string | null;
    qr_url?: string | null;
};

const props = withDefaults(
    defineProps<{
        model: LegacyPlateModelData | null;
        personalization?: LegacyPlatePersonalization;
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

const values: Record<LegacyPlateFieldKey, () => string | null | undefined> = {
    athlete_name: () => props.personalization.athlete_name,
    race_label: () => props.personalization.race_label,
    official_time: () => props.personalization.official_time,
    pace: () => props.personalization.pace,
    qr: () => null,
};

const visibleFields = computed(
    () => props.model?.fields.filter((f) => f.visible !== false) ?? [],
);

function textAnchor(alignment: LegacyPlateModelField['alignment']) {
    return alignment === 'left'
        ? 'start'
        : alignment === 'right'
          ? 'end'
          : 'middle';
}

function textX(field: LegacyPlateModelField) {
    if (field.alignment === 'left') {
        return field.x;
    }

    if (field.alignment === 'right') {
        return field.x + field.width;
    }

    return field.x + field.width / 2;
}

// --- Flip / tilt / zoom state -------------------------------------------

const showingBack = ref(false);
const tilt = ref({ x: 0, y: 0 });
const zoom = ref(1);
const dragging = ref(false);
const spinning = ref(false);
let spinRaf: number | null = null;
let dragStart = { x: 0, y: 0, tiltX: 0, tiltY: 0 };

const rotation = computed(
    () =>
        `rotateX(${tilt.value.x}deg) rotateY(${showingBack.value ? 180 : 0}deg)`,
);

function stopSpin() {
    spinning.value = false;

    if (spinRaf !== null) {
        cancelAnimationFrame(spinRaf);
        spinRaf = null;
    }
}

function toggleSpin() {
    if (!props.interactive) {
        return;
    }

    if (spinning.value) {
        stopSpin();

        return;
    }

    spinning.value = true;
    const start = performance.now();
    const tick = (now: number) => {
        const elapsed = (now - start) / 1000;
        tilt.value = { x: 0, y: Math.sin(elapsed * 1.1) * 12 };
        spinRaf = requestAnimationFrame(tick);
    };
    spinRaf = requestAnimationFrame(tick);
}

function reset() {
    stopSpin();
    showingBack.value = false;
    tilt.value = { x: 0, y: 0 };
    zoom.value = 1;
}

function onPointerDown(event: PointerEvent) {
    if (!props.interactive) {
        return;
    }

    stopSpin();
    dragging.value = true;
    dragStart = {
        x: event.clientX,
        y: event.clientY,
        tiltX: tilt.value.x,
        tiltY: tilt.value.y,
    };
    (event.target as HTMLElement).setPointerCapture(event.pointerId);
}

function onPointerMove(event: PointerEvent) {
    if (!dragging.value) {
        return;
    }

    const dx = event.clientX - dragStart.x;
    const dy = event.clientY - dragStart.y;
    tilt.value = {
        x: clamp(dragStart.tiltX - dy * 0.3, -20, 20),
        y: clamp(dragStart.tiltY + dx * 0.3, -35, 35),
    };
}

function onPointerUp() {
    dragging.value = false;
}

function clamp(value: number, min: number, max: number) {
    return Math.min(max, Math.max(min, value));
}

function zoomIn() {
    zoom.value = clamp(zoom.value + 0.15, 1, 1.6);
}

onBeforeUnmount(stopSpin);
</script>

<template>
    <div class="select-none">
        <div
            class="relative overflow-hidden rounded-2xl border border-white/10 bg-gradient-to-b from-fl-black to-fl-graphite/60 p-6 sm:p-10"
            style="perspective: 1200px"
        >
            <div
                class="absolute inset-0 opacity-[0.08]"
                style="
                    background-image: radial-gradient(
                        circle at 50% 0%,
                        white,
                        transparent 60%
                    );
                "
            />

            <div
                class="relative mx-auto touch-none"
                :style="{
                    maxWidth: '420px',
                    aspectRatio: model
                        ? `${model.width_mm} / ${model.height_mm}`
                        : '5 / 2',
                    cursor: interactive
                        ? dragging
                            ? 'grabbing'
                            : 'grab'
                        : 'default',
                }"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerUp"
            >
                <div
                    class="relative h-full w-full transition-transform duration-300 ease-out"
                    style="transform-style: preserve-3d"
                    :style="{
                        transform: `${rotation} scale(${zoom})`,
                        transitionDuration:
                            dragging || spinning ? '0ms' : '300ms',
                    }"
                >
                    <!-- Front face -->
                    <div
                        class="absolute inset-0 overflow-hidden rounded-xl border border-white/15 shadow-[0_20px_60px_-15px_rgba(0,0,0,0.7)]"
                        style="backface-visibility: hidden"
                    >
                        <img
                            v-if="model?.preview_image_url"
                            :src="model.preview_image_url"
                            :alt="model.name"
                            class="absolute inset-0 h-full w-full object-cover"
                            draggable="false"
                        />
                        <div
                            v-else
                            class="absolute inset-0"
                            style="
                                background: linear-gradient(
                                    135deg,
                                    #6b6f76 0%,
                                    #9a9ea6 22%,
                                    #cfd3d9 38%,
                                    #7d818a 55%,
                                    #b4b8c0 72%,
                                    #61656c 100%
                                );
                            "
                        />
                        <div
                            class="pointer-events-none absolute inset-0"
                            style="
                                background: linear-gradient(
                                    to bottom,
                                    rgba(255, 255, 255, 0.25),
                                    transparent 30%,
                                    transparent 70%,
                                    rgba(0, 0, 0, 0.25)
                                );
                            "
                        />

                        <svg
                            v-if="model"
                            :viewBox="`0 0 ${model.width_mm} ${model.height_mm}`"
                            class="absolute inset-0 h-full w-full"
                            preserveAspectRatio="xMidYMid meet"
                        >
                            <rect
                                v-if="!model.preview_image_url"
                                :x="model.engraving_area.x"
                                :y="model.engraving_area.y"
                                :width="model.engraving_area.width"
                                :height="model.engraving_area.height"
                                fill="none"
                                stroke="rgba(0,0,0,0.15)"
                                stroke-dasharray="1.2 1"
                                :stroke-width="0.3"
                            />
                            <template
                                v-for="field in visibleFields"
                                :key="field.field_key"
                            >
                                <image
                                    v-if="
                                        field.field_key === 'qr' &&
                                        personalization.qr_url
                                    "
                                    :href="personalization.qr_url"
                                    :x="field.x"
                                    :y="field.y"
                                    :width="field.width"
                                    :height="field.height"
                                />
                                <text
                                    v-else-if="field.field_key !== 'qr'"
                                    :x="textX(field)"
                                    :y="field.y + field.height / 2"
                                    :text-anchor="textAnchor(field.alignment)"
                                    dominant-baseline="middle"
                                    :font-size="field.font_size ?? 4"
                                    font-family="var(--font-sans, sans-serif)"
                                    font-weight="600"
                                    fill="#17181a"
                                    fill-opacity="0.88"
                                >
                                    {{ values[field.field_key]?.() || '' }}
                                </text>
                            </template>
                        </svg>
                    </div>

                    <!-- Back face -->
                    <div
                        class="absolute inset-0 flex items-center justify-center overflow-hidden rounded-xl border border-white/15 shadow-[0_20px_60px_-15px_rgba(0,0,0,0.7)]"
                        style="
                            backface-visibility: hidden;
                            transform: rotateY(180deg);
                        "
                    >
                        <img
                            v-if="backImageUrl"
                            :src="backImageUrl"
                            alt=""
                            class="absolute inset-0 h-full w-full object-cover"
                            draggable="false"
                        />
                        <div
                            v-else
                            class="absolute inset-0"
                            style="
                                background: linear-gradient(
                                    135deg,
                                    #55585e 0%,
                                    #7d818a 30%,
                                    #4d5056 65%,
                                    #6b6f76 100%
                                );
                            "
                        />
                        <p
                            class="relative text-[10px] font-semibold tracking-[0.3em] text-white/40 uppercase"
                        >
                            Finisher Legacy
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="interactive"
                class="relative mt-6 flex flex-wrap items-center justify-center gap-2"
            >
                <button
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        !showingBack
                            ? 'border-fl-gold/40 bg-fl-gold/10 text-fl-gold-soft'
                            : 'border-white/10 text-white/60 hover:border-white/20 hover:text-white'
                    "
                    @click="
                        stopSpin();
                        showingBack = false;
                    "
                >
                    Frente
                </button>
                <button
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        showingBack
                            ? 'border-fl-gold/40 bg-fl-gold/10 text-fl-gold-soft'
                            : 'border-white/10 text-white/60 hover:border-white/20 hover:text-white'
                    "
                    @click="
                        stopSpin();
                        showingBack = true;
                    "
                >
                    Reverso
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        spinning
                            ? 'border-fl-gold/40 bg-fl-gold/10 text-fl-gold-soft'
                            : 'border-white/10 text-white/60 hover:border-white/20 hover:text-white'
                    "
                    @click="toggleSpin"
                >
                    <SpinIcon class="size-3.5" />
                    Girar
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-full border border-white/10 px-3 py-1.5 text-xs font-medium text-white/60 transition-colors hover:border-white/20 hover:text-white"
                    @click="zoomIn"
                >
                    <Maximize2 class="size-3.5" />
                    Zoom
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-full border border-white/10 px-3 py-1.5 text-xs font-medium text-white/60 transition-colors hover:border-white/20 hover:text-white"
                    @click="reset"
                >
                    <RotateCcw class="size-3.5" />
                    Reset
                </button>
            </div>

            <p
                v-if="interactive"
                class="relative mt-3 text-center text-[11px] text-white/25"
            >
                Arrastra para girar
            </p>
        </div>

        <p
            v-if="mode === 'admin' && model && !model.preview_image_url"
            class="mt-2 text-center text-xs text-amber-400/80"
        >
            Sin foto del modelo — mostrando acabado genérico. Sube
            "preview_image" para la vista real.
        </p>
    </div>
</template>
