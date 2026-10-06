<script setup lang="ts">
/**
 * The Legacy Plate drawn as the real product — the interactive fallback
 * wherever a product photograph isn't available, and the same geometry as
 * the renders in public/media/brand/plate (tools/renders/plate.mjs):
 *
 *  - compact horizontal plate, ~2.67:1, softly rounded corners
 *    (not a bank card, not a licence plate, no side rings, no ribbon)
 *  - ZAMAK niquelado body (warm champagne/nickel)
 *  - left: resin window with the athlete's printed data
 *  - right: black resin panel with the FL monogram + a discreet NFC mark
 *  - back: printed event data and the NFC zone (no QR, no laser)
 *
 * Interaction: tilt + glare follow the pointer (off under reduced motion),
 * a Frente/Reverso switch (buttons, keyboard-friendly, works on touch) and
 * hotspots that open on click/tap — never hover-only.
 */
import { computed, ref, useId, useTemplateRef } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

const props = withDefaults(
    defineProps<{
        eventName?: string;
        athleteName?: string;
        distance?: string;
        time?: string;
        serial?: string;
        /** Off inside compact/decorative placements (e.g. the Hero). */
        showHotspots?: boolean;
        showCaption?: boolean;
        size?: 'sm' | 'md' | 'lg';
        /** Start on the back face. */
        initialFace?: 'front' | 'back';
    }>(),
    {
        eventName: 'Tu evento',
        athleteName: 'Tu nombre',
        distance: '42 km',
        time: '00:00:00',
        serial: 'FL · 000000',
        showHotspots: true,
        showCaption: true,
        size: 'md',
        initialFace: 'front',
    },
);

const uid = useId().replace(/[^a-zA-Z0-9-]/g, '');
const id = (name: string) => `${uid}-${name}`;
const url = (name: string) => `url(#${id(name)})`;

const FL_PATH =
    'M52 618 L205 268 C252 160 300 66 452 52 L915 52 L863 167 L532 168 C470 172 442 196 418 244 L397 294 L752 294 C722 372 676 410 620 411 L348 412 L258 618 Z M956 52 L1157 52 L971 482 L1347 482 C1312 576 1270 612 1190 618 L707 618 Z';

const name = computed(() => props.athleteName.toUpperCase());
const event = computed(() => props.eventName.toUpperCase());
// Long names shrink to stay inside the resin window (~280 units wide).
const nameSize = computed(() =>
    Math.min(38, Math.round(280 / Math.max(name.value.length * 0.72, 1))),
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
        text: 'Cuerpo metálico de Zamak con acabado niquelado: peso real y sensación de pieza premium.',
        face: 'front',
        top: '6%',
        left: '22%',
    },
    {
        key: 'resin',
        label: 'Acabado en resina',
        text: 'Una capa de resina transparente protege la gráfica con brillo controlado y profundidad.',
        face: 'front',
        top: '30%',
        left: '58%',
    },
    {
        key: 'custom',
        label: 'Personalización',
        text: 'Tu nombre, evento, distancia y tiempo, impresos en uno de los tres layouts.',
        face: 'front',
        top: '72%',
        left: '30%',
    },
    {
        key: 'nfc',
        label: 'NFC integrado',
        text: 'Antena NFC pasiva bajo el panel: acerca un teléfono compatible y se abre tu Legacy. Sin batería, sin QR impreso.',
        face: 'front',
        top: '80%',
        left: '91%',
    },
    {
        key: 'ferrite',
        label: 'Ferrita anti-metal',
        text: 'Bajo el panel negro, una lámina de ferrita separa la antena del metal para que la lectura NFC funcione.',
        face: 'front',
        top: '22%',
        left: '78%',
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

const widths = { sm: 'max-w-[300px]', md: 'max-w-xl', lg: 'max-w-2xl' };
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
                class="absolute inset-x-[8%] -bottom-[6%] h-[22%] rounded-[50%] bg-[rgb(40_32_20/0.32)] blur-xl"
            />
            <div
                class="relative transition-transform duration-500 ease-out"
                :style="{
                    aspectRatio: '560 / 210',
                    transformStyle: 'preserve-3d',
                    transform: `rotateX(var(--tilt-x)) rotateY(calc(var(--tilt-y) + ${showingBack ? 180 : 0}deg))`,
                }"
            >
                <!-- FRONT -->
                <svg
                    viewBox="0 0 560 210"
                    class="absolute inset-0 size-full"
                    style="backface-visibility: hidden"
                    role="img"
                    :aria-label="`Frente de la Legacy Plate: ${athleteName}, ${eventName}, ${distance} en ${time}`"
                    :aria-hidden="showingBack"
                >
                    <defs>
                        <linearGradient
                            :id="id('edge')"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop offset="0" stop-color="#f6eedc" />
                            <stop offset=".18" stop-color="#cbb791" />
                            <stop offset=".55" stop-color="#8e7a56" />
                            <stop offset=".85" stop-color="#5f5038" />
                            <stop offset="1" stop-color="#3b3122" />
                        </linearGradient>
                        <linearGradient
                            :id="id('face')"
                            x1="0"
                            y1="0"
                            x2="1"
                            y2=".55"
                        >
                            <stop offset="0" stop-color="#efe3c8" />
                            <stop offset=".2" stop-color="#d6c39d" />
                            <stop offset=".38" stop-color="#f7efdd" />
                            <stop offset=".56" stop-color="#c9b48c" />
                            <stop offset=".78" stop-color="#e9dcbf" />
                            <stop offset="1" stop-color="#b59f76" />
                        </linearGradient>
                        <linearGradient
                            :id="id('bevel')"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0"
                                stop-color="#fffaf0"
                                stop-opacity=".95"
                            />
                            <stop
                                offset=".5"
                                stop-color="#fffaf0"
                                stop-opacity="0"
                            />
                            <stop
                                offset="1"
                                stop-color="#4a3d29"
                                stop-opacity=".55"
                            />
                        </linearGradient>
                        <linearGradient
                            :id="id('resin')"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop offset="0" stop-color="#fbf7ee" />
                            <stop offset="1" stop-color="#efe6d3" />
                        </linearGradient>
                        <linearGradient
                            :id="id('black')"
                            x1="0"
                            y1="0"
                            x2="1"
                            y2="1"
                        >
                            <stop offset="0" stop-color="#262522" />
                            <stop offset=".45" stop-color="#0d0d0c" />
                            <stop offset="1" stop-color="#1b1a18" />
                        </linearGradient>
                        <linearGradient
                            :id="id('gold')"
                            x1="0"
                            y1="0"
                            x2="1"
                            y2="1"
                        >
                            <stop offset="0" stop-color="#f3dfa6" />
                            <stop offset=".45" stop-color="#c9a45c" />
                            <stop offset="1" stop-color="#8f6f33" />
                        </linearGradient>
                        <linearGradient
                            :id="id('gloss')"
                            x1="0"
                            y1="0"
                            x2="1"
                            y2="1"
                        >
                            <stop
                                offset="0"
                                stop-color="#fff"
                                stop-opacity=".55"
                            />
                            <stop
                                offset=".32"
                                stop-color="#fff"
                                stop-opacity=".08"
                            />
                            <stop
                                offset=".33"
                                stop-color="#fff"
                                stop-opacity="0"
                            />
                        </linearGradient>
                    </defs>
                    <rect
                        width="560"
                        height="210"
                        rx="26"
                        :fill="url('edge')"
                    />
                    <rect
                        x="5"
                        y="4"
                        width="550"
                        height="199"
                        rx="22"
                        :fill="url('face')"
                    />
                    <rect
                        x="5"
                        y="4"
                        width="550"
                        height="199"
                        rx="22"
                        fill="none"
                        :stroke="url('bevel')"
                        stroke-width="3"
                    />

                    <rect
                        x="24"
                        y="24"
                        width="322"
                        height="158"
                        rx="13"
                        :fill="url('resin')"
                    />
                    <rect
                        x="24"
                        y="24"
                        width="322"
                        height="158"
                        rx="13"
                        fill="none"
                        stroke="#9d8762"
                        stroke-opacity=".55"
                        stroke-width="1.2"
                    />
                    <text
                        x="48"
                        y="64"
                        font-family="Instrument Sans, sans-serif"
                        font-size="11"
                        letter-spacing="3.2"
                        font-weight="600"
                        fill="#85662b"
                    >
                        {{ event }}
                    </text>
                    <text
                        x="46"
                        y="112"
                        font-family="Fraunces, Georgia, serif"
                        :font-size="nameSize"
                        font-weight="500"
                        fill="#171714"
                    >
                        {{ name }}
                    </text>
                    <line
                        x1="48"
                        y1="132"
                        x2="96"
                        y2="132"
                        stroke="#c9a45c"
                        stroke-width="2"
                    />
                    <text
                        x="48"
                        y="160"
                        font-family="Instrument Sans, sans-serif"
                        font-size="17"
                        font-weight="600"
                        letter-spacing="1.4"
                        fill="#171714"
                    >
                        {{ distance.toUpperCase() }} · {{ time }}
                    </text>
                    <rect
                        x="24"
                        y="24"
                        width="322"
                        height="158"
                        rx="13"
                        :fill="url('gloss')"
                        opacity=".75"
                    />

                    <rect
                        x="360"
                        y="24"
                        width="176"
                        height="158"
                        rx="13"
                        :fill="url('black')"
                    />
                    <g transform="translate(392 70) scale(0.08)">
                        <path :d="FL_PATH" :fill="url('gold')" />
                    </g>
                    <g
                        transform="translate(506 152) scale(0.75)"
                        fill="none"
                        stroke="#c9a45c"
                        stroke-width="1.6"
                        stroke-linecap="round"
                        opacity=".7"
                    >
                        <path d="M6 8.3a6 6 0 0 1 0 7.4" />
                        <path d="M9.6 6a10 10 0 0 1 0 12" />
                        <path d="M13.2 3.6a14 14 0 0 1 0 16.8" />
                        <circle
                            cx="3.2"
                            cy="12"
                            r="1.2"
                            fill="#c9a45c"
                            stroke="none"
                        />
                    </g>
                    <rect
                        x="360"
                        y="24"
                        width="176"
                        height="158"
                        rx="13"
                        :fill="url('gloss')"
                        opacity=".55"
                    />
                </svg>

                <!-- BACK -->
                <svg
                    viewBox="0 0 560 210"
                    class="absolute inset-0 size-full"
                    style="
                        backface-visibility: hidden;
                        transform: rotateY(180deg);
                    "
                    role="img"
                    aria-label="Reverso de la Legacy Plate: datos del evento"
                    :aria-hidden="!showingBack"
                >
                    <rect
                        width="560"
                        height="210"
                        rx="26"
                        :fill="url('edge')"
                    />
                    <rect
                        x="5"
                        y="4"
                        width="550"
                        height="199"
                        rx="22"
                        :fill="url('face')"
                    />
                    <rect
                        x="5"
                        y="4"
                        width="550"
                        height="199"
                        rx="22"
                        fill="none"
                        :stroke="url('bevel')"
                        stroke-width="3"
                    />
                    <rect
                        x="24"
                        y="24"
                        width="512"
                        height="158"
                        rx="13"
                        fill="#141413"
                    />
                    <g transform="translate(48 44) scale(0.03)">
                        <path :d="FL_PATH" :fill="url('gold')" />
                    </g>
                    <text
                        x="100"
                        y="60"
                        font-family="Instrument Sans, sans-serif"
                        font-size="10"
                        letter-spacing="3"
                        font-weight="600"
                        fill="#c9a45c"
                    >
                        FINISHER LEGACY
                    </text>
                    <g font-family="Instrument Sans, sans-serif">
                        <text
                            x="48"
                            y="96"
                            font-size="8.5"
                            letter-spacing="2"
                            fill="#8b867c"
                        >
                            EVENTO
                        </text>
                        <text
                            x="132"
                            y="96"
                            font-size="11"
                            font-weight="600"
                            letter-spacing="1"
                            fill="#f3efe6"
                        >
                            {{ event }}
                        </text>
                        <text
                            x="48"
                            y="114"
                            font-size="8.5"
                            letter-spacing="2"
                            fill="#8b867c"
                        >
                            DISTANCIA
                        </text>
                        <text
                            x="132"
                            y="114"
                            font-size="11"
                            font-weight="600"
                            letter-spacing="1"
                            fill="#f3efe6"
                        >
                            {{ distance.toUpperCase() }}
                        </text>
                        <text
                            x="48"
                            y="132"
                            font-size="8.5"
                            letter-spacing="2"
                            fill="#8b867c"
                        >
                            TIEMPO
                        </text>
                        <text
                            x="132"
                            y="132"
                            font-size="11"
                            font-weight="600"
                            letter-spacing="1"
                            fill="#f3efe6"
                        >
                            {{ time }}
                        </text>
                        <text
                            x="48"
                            y="150"
                            font-size="8.5"
                            letter-spacing="2"
                            fill="#8b867c"
                        >
                            ATLETA
                        </text>
                        <text
                            x="132"
                            y="150"
                            font-size="11"
                            font-weight="600"
                            letter-spacing="1"
                            fill="#f3efe6"
                        >
                            {{ name }}
                        </text>
                    </g>
                    <path
                        d="M340 150 C372 118 392 160 420 128 S470 88 512 112"
                        fill="none"
                        stroke="#c9a45c"
                        stroke-opacity=".55"
                        stroke-width="1.4"
                        stroke-dasharray="3 4"
                    />
                    <circle cx="340" cy="150" r="3" fill="#c9a45c" />
                    <circle
                        cx="512"
                        cy="112"
                        r="3"
                        fill="none"
                        stroke="#c9a45c"
                        stroke-width="1.4"
                    />
                    <text
                        x="512"
                        y="166"
                        text-anchor="end"
                        font-family="Instrument Sans, sans-serif"
                        font-size="8"
                        letter-spacing="2.4"
                        fill="#8b867c"
                    >
                        TU ESFUERZO · TU HISTORIA
                    </text>
                    <text
                        x="520"
                        y="56"
                        text-anchor="end"
                        font-family="Instrument Sans, sans-serif"
                        font-size="8"
                        letter-spacing="1.6"
                        fill="#6f6a60"
                    >
                        {{ serial }}
                    </text>
                    <rect
                        x="24"
                        y="24"
                        width="512"
                        height="158"
                        rx="13"
                        :fill="url('gloss')"
                        opacity=".45"
                    />
                </svg>

                <!-- moving glare -->
                <div
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0 rounded-[4.6%/12%] opacity-60 mix-blend-soft-light motion-reduce:hidden"
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
                aria-label="Cara de la placa"
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
                    Frente
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
                    Reverso
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
                Zamak niquelado · acabado en resina · NFC integrado
                <template v-if="showHotspots">
                    — toca los puntos para conocer cada detalle</template
                >
            </p>
        </figcaption>
    </figure>
</template>
