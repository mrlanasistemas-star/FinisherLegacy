<script setup lang="ts">
/**
 * The Legacy Plate V3 drawn as the real product — the interactive
 * fallback wherever a product photograph isn't available. The drawing is
 * resources/js/lib/plate-art.ts, the same module the renders use:
 *
 *  - 70 × 45 mm, Zamak niquelado (lightly brushed), resin on the front
 *  - FRONT: the sports design + black FL panel; the NFC inlay sits under
 *    that panel on a ferrite layer
 *  - BACK: clean metal and a stamped stainless-steel money clip that holds
 *    the medal ribbon — no print, no QR, no data on the back
 *
 * Interaction: tilt + glare follow the pointer (off under reduced motion),
 * a "Ver frente / Ver broche" switch (buttons, keyboard-friendly, works on
 * touch) and hotspots that open on click/tap — never hover-only.
 */
import { computed, ref, useId, useTemplateRef } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { DEFAULT_SPEC, plateSvg } from '@/lib/plate-art';
import type { PlateSpec } from '@/lib/plate-art';

const props = withDefaults(
    defineProps<{
        eventName?: string;
        athleteName?: string;
        distance?: string;
        time?: string;
        date?: string;
        pace?: string;
        /** Off inside compact/decorative placements (e.g. the Hero). */
        showHotspots?: boolean;
        showCaption?: boolean;
        size?: 'sm' | 'md' | 'lg';
        /** Start showing the clip side. */
        initialFace?: 'front' | 'back';
        spec?: Partial<PlateSpec>;
    }>(),
    {
        eventName: 'Tu evento',
        athleteName: 'Tu nombre',
        distance: '21.1 km',
        time: '01:44:51',
        date: '22 May 2026',
        pace: '4:58 min/km',
        showHotspots: true,
        showCaption: true,
        size: 'md',
        initialFace: 'front',
        spec: () => ({}),
    },
);

const uid = useId().replace(/[^a-zA-Z0-9-]/g, '');
const fullSpec = computed(() => ({ ...DEFAULT_SPEC, ...props.spec }));

const frontSvg = computed(() =>
    plateSvg(
        'front',
        `${uid}f`,
        {
            name: props.athleteName,
            event: props.eventName,
            date: props.date,
            time: props.time,
            distance: props.distance,
            pace: props.pace,
        },
        fullSpec.value,
        '',
        'class="block size-full" aria-hidden="true"',
    ),
);
const backSvg = computed(() =>
    plateSvg(
        'back',
        `${uid}b`,
        {},
        fullSpec.value,
        '',
        'class="block size-full" aria-hidden="true"',
    ),
);

const prefersReducedMotion = useReducedMotion();
const stage = useTemplateRef<HTMLElement>('stage');
const showingBack = ref(props.initialFace === 'back');

function onPointerMove(event: PointerEvent) {
    if (
        prefersReducedMotion.value ||
        !stage.value ||
        event.pointerType === 'touch'
    ) {
        return;
    }

    const rect = stage.value.getBoundingClientRect();
    const x = (event.clientX - rect.left) / rect.width;
    const y = (event.clientY - rect.top) / rect.height;
    stage.value.style.setProperty('--tilt-x', `${(y - 0.5) * -10}deg`);
    stage.value.style.setProperty('--tilt-y', `${(x - 0.5) * 12}deg`);
    stage.value.style.setProperty('--glare-x', `${x * 100}%`);
    stage.value.style.setProperty('--glare-y', `${y * 100}%`);
}

function resetTilt() {
    stage.value?.style.setProperty('--tilt-x', '0deg');
    stage.value?.style.setProperty('--tilt-y', '0deg');
}

type Hotspot = {
    key: string;
    label: string;
    text: string;
    face: 'front' | 'back';
    top: string;
    left: string;
};

const hotspots: Hotspot[] = [
    {
        key: 'zamak',
        label: 'Zamak niquelado',
        text: 'Cuerpo metálico de Zamak con acabado niquelado ligeramente cepillado: peso real y sensación de pieza premium.',
        face: 'front',
        top: '4%',
        left: '30%',
    },
    {
        key: 'custom',
        label: 'Personalización deportiva',
        text: 'Tu nombre, fecha, tiempo, distancia y ritmo en uno de los tres diseños frontales.',
        face: 'front',
        top: '24%',
        left: '22%',
    },
    {
        key: 'resin',
        label: 'Acabado en resina',
        text: 'Una capa de resina protege el diseño frontal con brillo controlado y profundidad.',
        face: 'front',
        top: '62%',
        left: '58%',
    },
    {
        key: 'nfc',
        label: 'NFC integrado',
        text: 'Antena NFC pasiva bajo el panel negro: acerca un teléfono compatible al frente y se abre tu Legacy. Sin batería, sin QR.',
        face: 'front',
        top: '80%',
        left: '85%',
    },
    {
        key: 'ferrite',
        label: 'Ferrita anti-metal',
        text: 'Debajo del inlay NFC, una lámina de ferrita lo aísla del metal para que la lectura funcione.',
        face: 'front',
        top: '30%',
        left: '85%',
    },
    {
        key: 'clip',
        label: 'Clip de acero inoxidable',
        text: 'Broche estampado tipo money clip, de una sola pieza: menos partes, sin mecanismos.',
        face: 'back',
        top: '50%',
        left: '52%',
    },
    {
        key: 'waves',
        label: 'Puntos de presión',
        text: 'Ondulaciones suaves generan presión sobre el listón de la medalla para que la placa quede firme.',
        face: 'back',
        top: '34%',
        left: '70%',
    },
    {
        key: 'fixed',
        label: 'Extremo fijo',
        text: 'El clip va fijado al cuerpo en un extremo; el otro queda libre para introducir el listón.',
        face: 'back',
        top: '50%',
        left: '19%',
    },
    {
        key: 'clean',
        label: 'Reverso limpio',
        text: 'El reverso es funcional: metal limpio, sin impresión, sin datos y sin QR.',
        face: 'back',
        top: '10%',
        left: '50%',
    },
];

const activeHotspot = ref<string | null>(null);
const visibleHotspots = computed(() =>
    hotspots.filter((h) => h.face === (showingBack.value ? 'back' : 'front')),
);
const activeText = computed(
    () => hotspots.find((h) => h.key === activeHotspot.value) ?? null,
);

function flip(back: boolean) {
    showingBack.value = back;
    activeHotspot.value = null;
}

const widths = { sm: 'max-w-[320px]', md: 'max-w-lg', lg: 'max-w-xl' };
const ratio = computed(
    () => `${fullSpec.value.width_mm} / ${fullSpec.value.height_mm}`,
);
</script>

<template>
    <figure class="mx-auto w-full" :class="widths[size]">
        <div
            ref="stage"
            class="relative select-none"
            style="perspective: 1400px; --tilt-x: 0deg; --tilt-y: 0deg"
            @pointermove="onPointerMove"
            @pointerleave="resetTilt"
        >
            <!-- contact shadow -->
            <div
                aria-hidden="true"
                class="absolute inset-x-[8%] -bottom-[5%] h-[18%] rounded-[50%] bg-[rgb(30_30_26/0.3)] blur-xl"
            />
            <div
                class="relative transition-transform duration-700 ease-out motion-reduce:transition-none"
                role="img"
                :aria-label="
                    showingBack
                        ? 'Reverso de la Legacy Plate: metal limpio con clip de acero inoxidable tipo money clip para sujetarla al listón de la medalla'
                        : `Frente de la Legacy Plate: ${athleteName}, ${distance} en ${time}, con panel negro FL y NFC integrado`
                "
                :style="{
                    aspectRatio: ratio,
                    transformStyle: 'preserve-3d',
                    transform: `rotateX(var(--tilt-x)) rotateY(calc(var(--tilt-y) + ${showingBack ? 180 : 0}deg))`,
                }"
            >
                <div
                    class="absolute inset-0"
                    style="backface-visibility: hidden"
                    v-html="frontSvg"
                />
                <div
                    class="absolute inset-0"
                    style="
                        backface-visibility: hidden;
                        transform: rotateY(180deg);
                    "
                    v-html="backSvg"
                />

                <!-- moving glare -->
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0 rounded-[6.5%/10%] opacity-50 mix-blend-soft-light motion-reduce:hidden"
                    style="
                        background: radial-gradient(
                            circle at var(--glare-x, 30%) var(--glare-y, 20%),
                            rgb(255 255 255 / 0.85),
                            transparent 45%
                        );
                    "
                />
            </div>

            <!-- hotspots (click/tap/keyboard) -->
            <template v-if="showHotspots">
                <button
                    v-for="spot in visibleHotspots"
                    :key="spot.key"
                    type="button"
                    class="absolute z-10 flex size-7 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                    :style="{ top: spot.top, left: spot.left }"
                    :aria-label="spot.label"
                    :aria-expanded="activeHotspot === spot.key"
                    @click="
                        activeHotspot =
                            activeHotspot === spot.key ? null : spot.key
                    "
                >
                    <span
                        class="absolute size-full animate-ping rounded-full bg-fl-gold/40 motion-reduce:hidden"
                        :class="activeHotspot === spot.key ? 'hidden' : ''"
                    />
                    <span
                        class="relative size-3 rounded-full border-2 border-white shadow"
                        :class="
                            activeHotspot === spot.key
                                ? 'bg-foreground'
                                : 'bg-fl-gold'
                        "
                    />
                </button>
            </template>
        </div>

        <figcaption
            v-if="showCaption || showHotspots"
            class="mt-8 space-y-4 text-center"
        >
            <div
                class="inline-flex rounded-full border border-border bg-card p-1"
                role="group"
                aria-label="Vista de la placa"
            >
                <button
                    type="button"
                    class="rounded-full px-4 py-1.5 text-xs font-semibold transition-colors"
                    :class="
                        !showingBack
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :aria-pressed="!showingBack"
                    @click="flip(false)"
                >
                    Ver frente
                </button>
                <button
                    type="button"
                    class="rounded-full px-4 py-1.5 text-xs font-semibold transition-colors"
                    :class="
                        showingBack
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :aria-pressed="showingBack"
                    @click="flip(true)"
                >
                    Ver broche
                </button>
            </div>
            <p
                v-if="activeText"
                class="mx-auto max-w-md rounded-xl border border-fl-gold/30 bg-fl-cream/60 px-4 py-3 text-left text-sm"
                aria-live="polite"
            >
                <span class="font-semibold">{{ activeText.label }}.</span>
                {{ activeText.text }}
            </p>
            <p
                v-else-if="showCaption"
                class="text-xs tracking-wide text-muted-foreground"
            >
                <template v-if="showingBack">
                    Clip de acero inoxidable · sujeción firme al listón
                </template>
                <template v-else>
                    Zamak niquelado · resina · NFC integrado ·
                    {{ fullSpec.width_mm }} × {{ fullSpec.height_mm }} mm
                </template>
                <template v-if="showHotspots">
                    — toca los puntos para conocer cada detalle</template
                >
            </p>
        </figcaption>
    </figure>
</template>
