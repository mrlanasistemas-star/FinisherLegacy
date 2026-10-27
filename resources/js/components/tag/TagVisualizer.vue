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
 * - FRONT: the collection artwork (name, subtitle, year, accent contour
 *   lines) and a small NFC mark; BACK: an illustrative (non-scannable)
 *   QR, a masked printed-code placeholder and the NFC instruction.
 *
 * Nothing here is product data: dimensions/material come only from the
 * product record and are rendered by the pages, never by this drawing.
 * Pointer tilt is mouse-only and disabled under prefers-reduced-motion.
 */
import { computed, ref, useId } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

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

/**
 * The year is drawn as the large numeral, so a name that already ends with
 * it ("Cozumel 2026") would print it twice — drop the trailing copy.
 */
const displayName = computed(() => {
    const name = props.name.trim();
    const year =
        props.year === null || props.year === '' ? '' : String(props.year);
    const trimmed =
        year && name.endsWith(year) ? name.slice(0, -year.length).trim() : '';

    return trimmed || name;
});

/** Long names wrap onto two lines and shrink, never overflow the body. */
const nameLines = computed(() => {
    const name = displayName.value;
    const words = name.toUpperCase().split(/\s+/).filter(Boolean);

    if (words.length <= 1 || name.length <= 11) {
        return [name.toUpperCase()];
    }

    const half = Math.ceil(name.length / 2);
    let first = '';
    const rest: string[] = [];

    for (const word of words) {
        if (!rest.length && (first + ' ' + word).trim().length <= half + 3) {
            first = (first + ' ' + word).trim();
        } else {
            rest.push(word);
        }
    }

    return rest.length ? [first, rest.join(' ')] : [first];
});

const nameSize = computed(() => {
    const longest = Math.max(...nameLines.value.map((l) => l.length));

    return Math.max(15, Math.min(32, Math.floor(290 / Math.max(longest, 6))));
});

const yearText = computed(() =>
    props.year === null || props.year === '' ? null : String(props.year),
);

const ariaLabel = computed(
    () =>
        props.label ??
        `Ilustración del Finisher Legacy Tag${props.face === 'back' ? ' (reverso)' : ''}: colección ${props.name}${yearText.value ? ` ${yearText.value}` : ''}, con correa, ojal metálico${props.face === 'back' ? ', código QR y NFC' : ' y NFC'}.`,
);

/**
 * Illustrative 21×21 QR-like pattern (NOT scannable, no URL encoded),
 * deterministic per collection name so each collection looks its own.
 */
const qrCells = computed(() => {
    let seed = 0;

    for (const ch of props.name + (yearText.value ?? '')) {
        seed = (seed * 31 + ch.charCodeAt(0)) >>> 0;
    }

    const rand = () => {
        seed = (seed * 1664525 + 1013904223) >>> 0;

        return seed / 4294967296;
    };

    const inFinder = (x: number, y: number) =>
        (x < 8 && y < 8) || (x > 12 && y < 8) || (x < 8 && y > 12);
    const cells: { x: number; y: number }[] = [];

    for (let y = 0; y < 21; y++) {
        for (let x = 0; x < 21; x++) {
            if (!inFinder(x, y) && rand() > 0.52) {
                cells.push({ x, y });
            }
        }
    }

    return cells;
});

const finders = [
    [0, 0],
    [14, 0],
    [0, 14],
];
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
                                y="76"
                                text-anchor="middle"
                                class="tag-visualizer__brand"
                                style="fill: var(--fl-gold-soft)"
                            >
                                FINISHER LEGACY
                            </text>
                            <rect
                                x="104"
                                y="86"
                                width="32"
                                height="0.8"
                                style="fill: var(--fl-gold)"
                            />

                            <text
                                v-for="(line, index) in nameLines"
                                :key="line"
                                x="120"
                                :y="
                                    (nameLines.length === 1 ? 160 : 146) +
                                    index * (nameSize + 4)
                                "
                                text-anchor="middle"
                                class="tag-visualizer__name"
                                :font-size="nameSize"
                                style="fill: var(--fl-paper)"
                            >
                                {{ line }}
                            </text>

                            <text
                                v-if="subtitle"
                                x="120"
                                :y="
                                    nameLines.length === 1
                                        ? 184
                                        : 184 + nameSize / 2
                                "
                                text-anchor="middle"
                                class="tag-visualizer__sub"
                                fill="#fff"
                                fill-opacity="0.62"
                            >
                                {{ subtitle.toUpperCase() }}
                            </text>

                            <text
                                v-if="yearText"
                                x="120"
                                y="262"
                                text-anchor="middle"
                                class="tag-visualizer__year"
                                fill="none"
                                :style="{ stroke: accentColor }"
                                stroke-width="0.9"
                            >
                                {{ yearText }}
                            </text>

                            <!-- NFC mark -->
                            <g
                                transform="translate(40 306)"
                                fill="none"
                                style="stroke: var(--fl-gold-soft)"
                                stroke-width="1.2"
                                stroke-linecap="round"
                                opacity="0.85"
                            >
                                <path d="M0 -6 a 8 8 0 0 1 0 12" />
                                <path d="M4 -10 a 13 13 0 0 1 0 20" />
                                <path d="M8 -14 a 18 18 0 0 1 0 28" />
                            </g>
                            <text
                                x="62"
                                y="309"
                                class="tag-visualizer__micro"
                                style="fill: var(--fl-gold-soft)"
                                fill-opacity="0.8"
                            >
                                TOCA · TU HISTORIA
                            </text>
                        </g>

                        <!-- BACK -->
                        <g v-else>
                            <text
                                x="120"
                                y="84"
                                text-anchor="middle"
                                class="tag-visualizer__back-title"
                                style="fill: var(--fl-black)"
                            >
                                Tu historia vive dentro.
                            </text>
                            <rect
                                x="104"
                                y="96"
                                width="32"
                                height="0.8"
                                style="fill: var(--fl-gold-dim)"
                            />

                            <!-- Illustrative QR (not scannable) -->
                            <g transform="translate(70 118)">
                                <rect
                                    x="-8"
                                    y="-8"
                                    width="116"
                                    height="116"
                                    rx="10"
                                    fill="#fff"
                                />
                                <g
                                    transform="scale(4.76)"
                                    style="fill: var(--fl-black)"
                                >
                                    <template
                                        v-for="[fx, fy] in finders"
                                        :key="`${fx}-${fy}`"
                                    >
                                        <rect
                                            :x="fx"
                                            :y="fy"
                                            width="7"
                                            height="7"
                                        />
                                        <rect
                                            :x="fx + 1"
                                            :y="fy + 1"
                                            width="5"
                                            height="5"
                                            fill="#fff"
                                        />
                                        <rect
                                            :x="fx + 2"
                                            :y="fy + 2"
                                            width="3"
                                            height="3"
                                        />
                                    </template>
                                    <rect
                                        v-for="cell in qrCells"
                                        :key="`${cell.x}:${cell.y}`"
                                        :x="cell.x"
                                        :y="cell.y"
                                        width="1"
                                        height="1"
                                    />
                                </g>
                            </g>

                            <text
                                x="120"
                                y="258"
                                text-anchor="middle"
                                class="tag-visualizer__micro"
                                style="fill: var(--fl-stone)"
                            >
                                CÓDIGO
                            </text>
                            <text
                                x="120"
                                y="276"
                                text-anchor="middle"
                                class="tag-visualizer__code"
                                style="fill: var(--fl-black)"
                            >
                                •••• •••• ••••
                            </text>

                            <g
                                transform="translate(80 312)"
                                fill="none"
                                style="stroke: var(--fl-gold-ink)"
                                stroke-width="1.2"
                                stroke-linecap="round"
                            >
                                <path d="M0 -5 a 7 7 0 0 1 0 10" />
                                <path d="M4 -9 a 12 12 0 0 1 0 18" />
                            </g>
                            <text
                                x="96"
                                y="315"
                                class="tag-visualizer__micro"
                                style="fill: var(--fl-gold-ink)"
                            >
                                ACERCA TU TELÉFONO
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
    font-size: 8.5px;
    font-weight: 600;
    letter-spacing: 3.2px;
}

.tag-visualizer__name {
    font-family: var(--font-serif);
    font-weight: 500;
    letter-spacing: 0.5px;
}

.tag-visualizer__sub {
    font-family: var(--font-sans);
    font-size: 7.5px;
    font-weight: 600;
    letter-spacing: 2.2px;
}

.tag-visualizer__year {
    font-family: var(--font-serif);
    font-size: 58px;
    font-weight: 400;
    letter-spacing: 1px;
}

.tag-visualizer__micro {
    font-family: var(--font-sans);
    font-size: 6.5px;
    font-weight: 600;
    letter-spacing: 1.8px;
}

.tag-visualizer__back-title {
    font-family: var(--font-serif);
    font-size: 15px;
    font-style: italic;
}

.tag-visualizer__code {
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 11px;
    letter-spacing: 2px;
}

@media (prefers-reduced-motion: reduce) {
    .tag-visualizer__tilt {
        transition: none;
        transform: none !important;
    }
}
</style>
