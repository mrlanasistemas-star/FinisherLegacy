<script setup lang="ts">
/**
 * TagVisualizer — ILLUSTRATIVE RENDER of the Finisher Legacy Tag.
 *
 * There is no photograph or 3D render of the real tag yet (pending real
 * tag photography — docs/architecture/public-experience.md). Until it
 * exists, this SVG draws a physical collectible, luggage-style tag so the
 * public pages can show *the object* without inventing a product photo:
 *
 * - portrait body with real thickness (offset edge layer + rim highlight),
 *   so it never reads as a credit card or a floating plastic card;
 * - a punched eyelet with a metal grommet and a woven strap looped
 *   through it (the strap is drawn behind the body and shows through the
 *   hole);
 * - subtle material grain + a single sheen that follows the tilt;
 * - FRONT: the collection artwork — the name set as large as it fits
 *   (shared layout in ./tagLayout.ts, same as the 3D textures), subtitle,
 *   big outlined year, accent contour lines and the NFC mark; BACK (NFC
 *   only — the tag carries no QR): "Tu historia vive dentro.", a big NFC
 *   seal in engraved rings, "Acerca tu celular" and the masked unit code.
 *
 * Nothing here is product data: dimensions/material come only from the
 * product record and are rendered by the pages, never by this drawing.
 * Pointer tilt is mouse-only and disabled under prefers-reduced-motion.
 */
import { computed, ref, useId } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import {
    displayName as tagDisplayName,
    estimateSans,
    estimateSerif,
    fitLine,
    fitName,
    frontLayout,
    TAG_TEXT_W,
} from './tagLayout';

const props = withDefaults(
    defineProps<{
        /** Collection name, e.g. "Cozumel". */
        name?: string;
        /** Small line under the name: discipline · destination. */
        subtitle?: string | null;
        year?: string | number | null;
        /** Collection accent (validated hex from the server) — artwork lines only. */
        accent?: string | null;
        face?: 'front' | 'back';
        tilt?: boolean;
        /** Accessible description; defaults to a sentence built from the props. */
        label?: string | null;
    }>(),
    {
        name: 'Tu colección',
        subtitle: null,
        year: null,
        accent: null,
        face: 'front',
        tilt: true,
        label: null,
    },
);

const uid = useId().replace(/[^a-zA-Z0-9_-]/g, '');
const id = (key: string) => `tv-${uid}-${key}`;
const url = (key: string) => `url(#${id(key)})`;

const reducedMotion = useReducedMotion();
const rx = ref(0);
const ry = ref(0);

function onMove(event: PointerEvent) {
    if (!props.tilt || reducedMotion.value || event.pointerType !== 'mouse') {
        return;
    }

    const rect = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const px = (event.clientX - rect.left) / rect.width - 0.5;
    const py = (event.clientY - rect.top) / rect.height - 0.5;
    ry.value = px * 14;
    rx.value = -py * 10;
}

function onLeave() {
    rx.value = 0;
    ry.value = 0;
}

const tiltStyle = computed(() => ({
    transform: `rotateX(${rx.value.toFixed(2)}deg) rotateY(${ry.value.toFixed(2)}deg)`,
}));

/** Sheen + edge parallax follow the tilt (in viewBox units). */
const sheenShift = computed(() => ry.value * 6);
const edgeShift = computed(() => -ry.value * 0.35);

const accentColor = computed(() => props.accent || 'var(--fl-gold)');

const yearText = computed(() =>
    props.year === null || props.year === '' ? null : String(props.year),
);

/** Name as large as it fits (1–2 lines), same layout as the 3D artwork. */
const name = computed(() =>
    fitName(tagDisplayName(props.name, yearText.value), estimateSerif),
);

const subtitleText = computed(() => props.subtitle?.toUpperCase() ?? '');
const subtitleSize = computed(() =>
    fitLine(subtitleText.value, estimateSans, { max: 11.5, min: 7.5 }),
);

const yearSize = computed(() =>
    yearText.value
        ? Math.min(80, TAG_TEXT_W / estimateSerif(yearText.value))
        : 0,
);

const layout = computed(() =>
    frontLayout(name.value, !!props.subtitle, !!yearText.value),
);

/** Engraved rings around the NFC seal (back). */
const rings = Array.from({ length: 30 }, (_, i) => 62 + i * 3.2);

const ariaLabel = computed(
    () =>
        props.label ??
        `Ilustración del Finisher Legacy Tag${props.face === 'back' ? ' (reverso)' : ''}: colección ${props.name}${yearText.value ? ` ${yearText.value}` : ''}, con correa, ojal metálico${props.face === 'back' ? ' y sello NFC para acercar el celular' : ' y NFC'}.`,
);
</script>

<template>
    <div
        class="tag-visualizer relative select-none"
        data-illustrative="true"
        @pointermove="onMove"
        @pointerleave="onLeave"
    >
        <div
            class="tag-visualizer__tilt"
            :class="{ 'is-still': reducedMotion }"
            :style="tiltStyle"
        >
            <svg
                viewBox="0 -118 240 482"
                class="block h-auto w-full overflow-visible"
                role="img"
                :aria-label="ariaLabel"
            >
                <defs>
                    <!-- Body: deep graphite (front) / warm paper (back) -->
                    <linearGradient
                        :id="id('front')"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="1"
                    >
                        <stop
                            offset="0"
                            style="stop-color: var(--fl-graphite-light)"
                        />
                        <stop
                            offset="0.45"
                            style="stop-color: var(--fl-graphite)"
                        />
                        <stop offset="1" style="stop-color: var(--fl-black)" />
                    </linearGradient>
                    <linearGradient
                        :id="id('back')"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="1"
                    >
                        <stop offset="0" style="stop-color: var(--fl-paper)" />
                        <stop offset="1" style="stop-color: var(--fl-cream)" />
                    </linearGradient>
                    <!-- Rim highlight: light catches the top-left bevel -->
                    <linearGradient :id="id('rim')" x1="0" y1="0" x2="1" y2="1">
                        <stop
                            offset="0"
                            stop-color="#fff"
                            stop-opacity="0.55"
                        />
                        <stop
                            offset="0.35"
                            stop-color="#fff"
                            stop-opacity="0.08"
                        />
                        <stop offset="1" stop-color="#fff" stop-opacity="0" />
                    </linearGradient>
                    <!-- Metal (grommet): the brand's gold sheen stops -->
                    <linearGradient
                        :id="id('metal')"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="1"
                    >
                        <stop
                            offset="0"
                            style="stop-color: var(--fl-gold-soft)"
                        />
                        <stop offset="0.5" style="stop-color: var(--fl-gold)" />
                        <stop
                            offset="1"
                            style="stop-color: var(--fl-gold-dim)"
                        />
                    </linearGradient>
                    <linearGradient
                        :id="id('sheen')"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="0.4"
                        gradientUnits="objectBoundingBox"
                        :gradientTransform="`translate(${(sheenShift / 240).toFixed(3)} 0)`"
                    >
                        <stop offset="0.3" stop-color="#fff" stop-opacity="0" />
                        <stop
                            offset="0.5"
                            stop-color="#fff"
                            stop-opacity="0.09"
                        />
                        <stop offset="0.7" stop-color="#fff" stop-opacity="0" />
                    </linearGradient>
                    <!-- Woven strap texture -->
                    <pattern
                        :id="id('weave')"
                        width="5"
                        height="5"
                        patternUnits="userSpaceOnUse"
                        patternTransform="rotate(35)"
                    >
                        <rect
                            width="5"
                            height="5"
                            style="fill: var(--fl-graphite)"
                        />
                        <rect
                            width="2.4"
                            height="5"
                            style="fill: var(--fl-black)"
                            opacity="0.7"
                        />
                    </pattern>
                    <!-- Material grain -->
                    <filter
                        :id="id('grain')"
                        x="0"
                        y="0"
                        width="100%"
                        height="100%"
                    >
                        <feTurbulence
                            type="fractalNoise"
                            baseFrequency="0.9"
                            numOctaves="2"
                            stitchTiles="stitch"
                        />
                        <feColorMatrix type="saturate" values="0" />
                    </filter>
                    <filter
                        :id="id('shadow')"
                        x="-30%"
                        y="-30%"
                        width="160%"
                        height="160%"
                    >
                        <feGaussianBlur stdDeviation="9" />
                    </filter>
                    <!-- The eyelet is a real hole: the body is masked there -->
                    <mask :id="id('hole')">
                        <rect
                            x="0"
                            y="-10"
                            width="240"
                            height="380"
                            fill="#fff"
                        />
                        <circle cx="120" cy="34" r="9" fill="#000" />
                    </mask>
                    <clipPath :id="id('clip')">
                        <rect x="20" y="0" width="200" height="340" rx="26" />
                    </clipPath>
                </defs>

                <!-- Contact shadow -->
                <ellipse
                    cx="124"
                    cy="356"
                    rx="86"
                    ry="10"
                    fill="#000"
                    opacity="0.38"
                    :filter="url('shadow')"
                />

                <!-- Strap loop (behind the body; seen through the eyelet) -->
                <g class="tag-visualizer__strap">
                    <path
                        d="M120 34 C 86 4, 80 -78, 120 -106 C 160 -78, 154 4, 120 34"
                        fill="none"
                        :stroke="url('weave')"
                        stroke-width="15"
                        stroke-linecap="round"
                    />
                    <path
                        d="M120 34 C 86 4, 80 -78, 120 -106 C 160 -78, 154 4, 120 34"
                        fill="none"
                        style="stroke: var(--fl-gold)"
                        stroke-width="0.8"
                        stroke-dasharray="3 3"
                        opacity="0.75"
                    />
                </g>

                <!-- Thickness: the edge layer under the face -->
                <g :mask="url('hole')">
                    <rect
                        :x="20 + edgeShift"
                        y="7"
                        width="200"
                        height="340"
                        rx="26"
                        :style="{
                            fill:
                                face === 'front'
                                    ? 'var(--fl-black)'
                                    : 'var(--fl-stone)',
                        }"
                    />

                    <!-- Face -->
                    <rect
                        x="20"
                        y="0"
                        width="200"
                        height="340"
                        rx="26"
                        :fill="url(face === 'front' ? 'front' : 'back')"
                    />

                    <g :clip-path="url('clip')">
                        <rect
                            x="20"
                            y="0"
                            width="200"
                            height="340"
                            :filter="url('grain')"
                            :opacity="face === 'front' ? 0.07 : 0.05"
                            style="mix-blend-mode: overlay"
                        />

                        <!-- FRONT artwork -->
                        <g v-if="face === 'front'">
                            <!-- Topographic contour lines in the collection accent -->
                            <g
                                fill="none"
                                :style="{ stroke: accentColor }"
                                stroke-width="0.9"
                                opacity="0.38"
                            >
                                <path
                                    d="M20 300 C 70 262, 120 300, 170 258 S 230 236, 240 220"
                                />
                                <path
                                    d="M20 316 C 76 280, 126 318, 178 274 S 232 254, 240 242"
                                />
                                <path
                                    d="M20 332 C 82 298, 132 336, 186 290 S 234 272, 240 264"
                                />
                                <path
                                    d="M40 350 C 92 316, 140 352, 194 306 S 236 290, 250 286"
                                />
                            </g>

                            <text
                                x="120"
                                :y="layout.brandY"
                                text-anchor="middle"
                                class="tag-visualizer__brand"
                                style="fill: var(--fl-gold-soft)"
                            >
                                FINISHER LEGACY
                            </text>
                            <rect
                                x="106"
                                :y="layout.ruleY"
                                width="28"
                                height="1"
                                style="fill: var(--fl-gold)"
                            />

                            <text
                                v-for="(line, index) in name.lines"
                                :key="line"
                                x="120"
                                :y="layout.baselines[index]"
                                text-anchor="middle"
                                class="tag-visualizer__name"
                                :font-size="name.size"
                                :textLength="
                                    name.squeeze ? TAG_TEXT_W : undefined
                                "
                                :lengthAdjust="
                                    name.squeeze
                                        ? 'spacingAndGlyphs'
                                        : undefined
                                "
                                style="fill: #fbfaf6"
                            >
                                {{ line }}
                            </text>

                            <text
                                v-if="subtitle"
                                x="120"
                                :y="layout.subtitleY"
                                text-anchor="middle"
                                class="tag-visualizer__sub"
                                :font-size="subtitleSize"
                                :letter-spacing="subtitleSize * 0.18"
                                fill="#fff"
                                fill-opacity="0.8"
                            >
                                {{ subtitleText }}
                            </text>

                            <text
                                v-if="yearText"
                                x="120"
                                :y="layout.yearY"
                                text-anchor="middle"
                                class="tag-visualizer__year"
                                :font-size="yearSize"
                                :style="{
                                    stroke: accentColor,
                                    fill: accentColor,
                                }"
                                fill-opacity="0.15"
                                stroke-width="1.2"
                            >
                                {{ yearText }}
                            </text>

                            <!-- NFC mark -->
                            <g
                                :transform="`translate(26 ${layout.footerY - 3.5})`"
                                fill="none"
                                style="stroke: var(--fl-gold-soft)"
                                stroke-width="1.4"
                                stroke-linecap="round"
                            >
                                <path d="M0 -5 a 7 7 0 0 1 0 10" />
                                <path d="M4 -9.5 a 12 12 0 0 1 0 19" />
                                <path d="M8 -14 a 17 17 0 0 1 0 28" />
                            </g>
                            <text
                                x="66"
                                :y="layout.footerY"
                                class="tag-visualizer__micro"
                                style="fill: var(--fl-gold-soft)"
                            >
                                ACERCA TU CELULAR
                            </text>
                        </g>

                        <!-- BACK: NFC only (the tag carries no QR) -->
                        <g v-else>
                            <!-- Engraved guilloche rings around the seal -->
                            <g
                                fill="none"
                                style="stroke: var(--fl-stone)"
                                stroke-opacity="0.16"
                                stroke-width="0.4"
                            >
                                <circle
                                    v-for="r in rings"
                                    :key="r"
                                    cx="120"
                                    cy="168"
                                    :r="r"
                                />
                            </g>
                            <rect
                                x="29"
                                y="9"
                                width="182"
                                height="322"
                                rx="18"
                                fill="none"
                                style="stroke: var(--fl-gold-ink)"
                                stroke-opacity="0.4"
                                stroke-width="0.7"
                            />
                            <text
                                x="120"
                                y="78"
                                text-anchor="middle"
                                class="tag-visualizer__back-title"
                                style="fill: var(--fl-black)"
                            >
                                <tspan x="120">Tu historia</tspan>
                                <tspan x="120" dy="22">vive dentro.</tspan>
                            </text>
                            <rect
                                x="106"
                                y="110"
                                width="28"
                                height="1"
                                style="fill: var(--fl-gold)"
                            />

                            <!-- NFC seal -->
                            <circle
                                cx="120"
                                cy="168"
                                r="50"
                                fill="#fff"
                                fill-opacity="0.85"
                            />
                            <circle
                                cx="120"
                                cy="168"
                                r="50"
                                fill="none"
                                style="stroke: var(--fl-gold-ink)"
                                stroke-width="1.6"
                            />
                            <circle
                                cx="120"
                                cy="168"
                                r="55"
                                fill="none"
                                style="stroke: var(--fl-gold-ink)"
                                stroke-width="0.5"
                            />
                            <g
                                transform="translate(112 168)"
                                fill="none"
                                style="stroke: var(--fl-gold-ink)"
                                stroke-width="3"
                                stroke-linecap="round"
                            >
                                <path d="M0 -12 a 16 16 0 0 1 0 24" />
                                <path d="M9 -21 a 27 27 0 0 1 0 42" />
                                <path d="M18 -30 a 38 38 0 0 1 0 60" />
                            </g>
                            <circle
                                cx="108"
                                cy="168"
                                r="4"
                                style="fill: var(--fl-gold-ink)"
                            />

                            <text
                                x="120"
                                y="250"
                                text-anchor="middle"
                                class="tag-visualizer__back-cta"
                                style="fill: var(--fl-black)"
                            >
                                Acerca tu celular
                            </text>
                            <text
                                x="120"
                                y="268"
                                text-anchor="middle"
                                class="tag-visualizer__micro"
                                style="
                                    fill: var(--fl-stone);
                                    font-size: 9.5px;
                                    letter-spacing: 1.4px;
                                "
                            >
                                SIN APPS · SE ABRE SOLO
                            </text>
                            <text
                                x="120"
                                y="297"
                                text-anchor="middle"
                                class="tag-visualizer__code"
                                style="fill: var(--fl-gold-ink)"
                            >
                                N.º •••• ••••
                            </text>
                            <text
                                x="120"
                                y="320"
                                text-anchor="middle"
                                class="tag-visualizer__micro"
                                style="
                                    fill: var(--fl-stone);
                                    font-size: 8.5px;
                                    letter-spacing: 2.8px;
                                "
                            >
                                FINISHER LEGACY
                            </text>
                        </g>

                        <!-- Single sheen -->
                        <rect
                            x="20"
                            y="0"
                            width="200"
                            height="340"
                            :fill="url('sheen')"
                        />
                    </g>

                    <!-- Rim highlight -->
                    <rect
                        x="20.6"
                        y="0.6"
                        width="198.8"
                        height="338.8"
                        rx="25.5"
                        fill="none"
                        :stroke="url('rim')"
                        stroke-width="1.2"
                    />
                </g>

                <!-- Grommet (metal ring around the hole) -->
                <circle
                    cx="120"
                    cy="34"
                    r="13.5"
                    fill="none"
                    :stroke="url('metal')"
                    stroke-width="6"
                />
                <circle
                    cx="120"
                    cy="34"
                    r="16.4"
                    fill="none"
                    stroke="#000"
                    stroke-opacity="0.35"
                    stroke-width="0.8"
                />
                <circle
                    cx="120"
                    cy="34"
                    r="10.6"
                    fill="none"
                    stroke="#000"
                    stroke-opacity="0.45"
                    stroke-width="0.8"
                />
                <path
                    d="M110 27 a 13.5 13.5 0 0 1 13 -6"
                    fill="none"
                    stroke="#fff"
                    stroke-opacity="0.7"
                    stroke-width="1.4"
                    stroke-linecap="round"
                />

                <!-- Strap strand in front of the body, entering the hole -->
                <path
                    d="M120 34 C 112 26, 106 16, 102 6"
                    fill="none"
                    :stroke="url('weave')"
                    stroke-width="12"
                    stroke-linecap="round"
                    opacity="0.95"
                />
            </svg>
        </div>
    </div>
</template>

<style scoped>
.tag-visualizer {
    perspective: 1100px;
}

.tag-visualizer__tilt {
    transform-style: preserve-3d;
    transition: transform 420ms cubic-bezier(0.16, 1, 0.3, 1);
    will-change: transform;
}

.tag-visualizer__tilt.is-still {
    transform: none !important;
    transition: none;
}

.tag-visualizer__brand {
    font-family: var(--font-sans);
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 3.4px;
}

.tag-visualizer__name {
    font-family: var(--font-serif);
    font-weight: 600;
    letter-spacing: -0.01em;
}

.tag-visualizer__sub {
    font-family: var(--font-sans);
    font-weight: 600;
}

.tag-visualizer__year {
    font-family: var(--font-serif);
    font-weight: 400;
}

.tag-visualizer__micro {
    font-family: var(--font-sans);
    font-size: 8px;
    font-weight: 600;
    letter-spacing: 1.5px;
}

.tag-visualizer__back-title {
    font-family: var(--font-serif);
    font-size: 20px;
    font-style: italic;
}

.tag-visualizer__back-cta {
    font-family: var(--font-serif);
    font-size: 21px;
    font-weight: 600;
}

.tag-visualizer__code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 1.4px;
}

@media (prefers-reduced-motion: reduce) {
    .tag-visualizer__tilt {
        transition: none;
        transform: none !important;
    }
}
</style>
