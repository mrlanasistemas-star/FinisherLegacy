<script setup lang="ts">
/**
 * TagVisualizer — illustrative SVG render of the Finisher Legacy Tag, drawn
 * like the collection designs of the strategy deck (pp. 7–8) with the
 * shared layout in ./tagLayout.ts (the 3D textures use the same one):
 *
 * - a vertical rounded card ≈ 1 : 2 with a horizontal slot near the top
 *   and a black silicone loop threaded through it;
 * - FRONT: the collection's destination artwork (`tag_design.front_art_url`,
 *   cover-fit below the slot, fading into carbon under the header) + the
 *   official FL monogram IMAGE, a thin gold rule, the name in spaced caps
 *   and the discipline line. No artwork → procedural fallback (carbon +
 *   gold contour lines + outlined year);
 * - BACK: carbon black, palm accents, monogram, "TU HISTORIA SIGUE VIVA"
 *   and the NFC mark. The tag carries no QR.
 *
 * Nothing here is product data beyond the design fields it is given.
 * Pointer tilt is mouse-only and disabled under prefers-reduced-motion.
 */
import { computed, onMounted, ref, useId, watch } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { useI18n } from '@/i18n';
import {
    BACK,
    BACK_PALMS,
    BACK_SPACING,
    CONTOUR_PATHS,
    DISCIPLINE_SPACING,
    FACE_H,
    FACE_W,
    FRONT,
    MONOGRAM_RATIO,
    MONOGRAM_SRC,
    NAME_SPACING,
    NFC_ARCS,
    TAG_COLORS,
    TAG_TEXT_W,
    artPlacement,
    disciplineSize,
    estimateSans,
    facePath,
    fitLine,
    nameSize,
    tagText,
} from './tagLayout';

const props = withDefaults(
    defineProps<{
        /** Collection name, e.g. "Cozumel". */
        name?: string;
        /** Discipline · destination (fallback for the discipline line). */
        subtitle?: string | null;
        year?: string | number | null;
        /** Collection accent (validated hex) — fallback glow only. */
        accent?: string | null;
        /** `tag_design` fields (deck pp. 7–8). */
        frontArt?: string | null;
        nameLine?: string | null;
        disciplineLine?: string | null;
        backLine?: string | null;
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
        frontArt: null,
        nameLine: null,
        disciplineLine: null,
        backLine: null,
        face: 'front',
        tilt: true,
        label: null,
    },
);

const uid = useId().replace(/[^a-zA-Z0-9_-]/g, '');
const id = (key: string) => `tv-${uid}-${key}`;
const url = (key: string) => `url(#${id(key)})`;

const { t } = useI18n();
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

const sheenShift = computed(() => ry.value * 6);
const edgeShift = computed(() => -ry.value * 0.35);

const yearText = computed(() =>
    props.year === null || props.year === '' ? null : String(props.year),
);

const text = computed(() => tagText(props));
const nameFont = computed(() => nameSize(text.value.name, estimateSans));
const disciplineFont = computed(() =>
    text.value.discipline
        ? disciplineSize(text.value.discipline, estimateSans)
        : 0,
);
const backFont = computed(() =>
    Math.min(
        ...text.value.back.map((line) =>
            fitLine(line, estimateSans, {
                max: 9.5,
                min: 6,
                spacing: BACK_SPACING,
                width: 150,
            }),
        ),
    ),
);
const yearFont = computed(() =>
    yearText.value
        ? Math.min(76, TAG_TEXT_W / (estimateSans(yearText.value) * 0.92))
        : 0,
);

// ---- Artwork: natural size → cover placement ---------------------------
/** Until the image reports its size, assume the deck crops' ≈ 0.69 ratio. */
const artSize = ref({ w: 900, h: 1310 });
const artFailed = ref(false);

function measureArt(src: string | null) {
    artFailed.value = false;

    if (!src || typeof window === 'undefined') {
        return;
    }

    const img = new Image();
    img.onload = () => {
        if (props.frontArt === src && img.naturalWidth) {
            artSize.value = { w: img.naturalWidth, h: img.naturalHeight };
        }
    };
    img.onerror = () => {
        if (props.frontArt === src) {
            artFailed.value = true;
        }
    };
    img.src = src;
}

onMounted(() => measureArt(props.frontArt));
watch(
    () => props.frontArt,
    (src) => measureArt(src),
);

const hasArt = computed(() => !!props.frontArt && !artFailed.value);
const art = computed(() => artPlacement(artSize.value.w, artSize.value.h));
const fadeTop = computed(() => Math.max(FRONT.artTop, art.value.y));

const monogramH = (width: number) => width * MONOGRAM_RATIO;
const accentColor = computed(() => props.accent || TAG_COLORS.gold);

const ariaLabel = computed(() => {
    if (props.label !== null && props.label !== undefined) {
        return props.label;
    }

    if (props.face === 'back') {
        return t('Reverso ilustrativo del Finisher Legacy Tag: negro carbono con el monograma FL, «{phrase}» y el símbolo NFC.', { phrase: text.value.back.join(' ') });
    }

    // What is printed on the tag (name · discipline) is never translated.
    const name = `${text.value.name}${text.value.discipline ? ` · ${text.value.discipline}` : ''}`;

    return hasArt.value
        ? t('Frente ilustrativo del Finisher Legacy Tag {name}, con el arte del destino, con correa de silicón negra.', { name })
        : t('Frente ilustrativo del Finisher Legacy Tag {name}, con correa de silicón negra.', { name });
});
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
                viewBox="-20 -128 240 548"
                class="block h-auto w-full overflow-visible"
                role="img"
                :aria-label="ariaLabel"
            >
                <defs>
                    <linearGradient
                        :id="id('carbon')"
                        x1="0"
                        y1="0"
                        x2="1"
                        y2="1"
                    >
                        <stop offset="0" :stop-color="TAG_COLORS.carbonLight" />
                        <stop offset="0.55" :stop-color="TAG_COLORS.carbon" />
                        <stop offset="1" stop-color="#050506" />
                    </linearGradient>
                    <linearGradient
                        :id="id('fade')"
                        x1="0"
                        y1="0"
                        x2="0"
                        y2="1"
                    >
                        <stop offset="0" :stop-color="TAG_COLORS.carbon" />
                        <stop
                            offset="0.55"
                            :stop-color="TAG_COLORS.carbon"
                            stop-opacity="0.45"
                        />
                        <stop
                            offset="1"
                            :stop-color="TAG_COLORS.carbon"
                            stop-opacity="0"
                        />
                    </linearGradient>
                    <radialGradient
                        :id="id('vignette')"
                        cx="100"
                        cy="250"
                        r="240"
                        gradientUnits="userSpaceOnUse"
                    >
                        <stop
                            offset="0.33"
                            stop-color="#000"
                            stop-opacity="0"
                        />
                        <stop
                            offset="1"
                            stop-color="#000"
                            stop-opacity="0.38"
                        />
                    </radialGradient>
                    <radialGradient
                        :id="id('glow')"
                        cx="100"
                        cy="300"
                        r="190"
                        gradientUnits="userSpaceOnUse"
                    >
                        <stop
                            offset="0"
                            :stop-color="accentColor"
                            stop-opacity="0.19"
                        />
                        <stop
                            offset="1"
                            :stop-color="accentColor"
                            stop-opacity="0"
                        />
                    </radialGradient>
                    <linearGradient :id="id('rim')" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#fff" stop-opacity="0.4" />
                        <stop
                            offset="0.35"
                            stop-color="#fff"
                            stop-opacity="0.06"
                        />
                        <stop offset="1" stop-color="#fff" stop-opacity="0" />
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
                            stop-opacity="0.08"
                        />
                        <stop offset="0.7" stop-color="#fff" stop-opacity="0" />
                    </linearGradient>
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
                        <feGaussianBlur stdDeviation="8" />
                    </filter>
                    <clipPath :id="id('clip')">
                        <path :d="facePath(true)" clip-rule="evenodd" />
                    </clipPath>
                </defs>

                <!-- Contact shadow -->
                <ellipse
                    cx="104"
                    cy="418"
                    rx="88"
                    ry="9"
                    fill="#000"
                    opacity="0.34"
                    :filter="url('shadow')"
                />

                <!-- Silicone loop (oval above the pinch) -->
                <g class="tag-visualizer__loop">
                    <path
                        d="M100 -10 C 62 -28, 64 -120, 100 -120 C 136 -120, 138 -28, 100 -10"
                        fill="none"
                        stroke="#121214"
                        stroke-width="9"
                        stroke-linecap="round"
                    />
                    <path
                        d="M93 -26 C 72 -44, 74 -112, 100 -114"
                        fill="none"
                        stroke="#fff"
                        stroke-opacity="0.14"
                        stroke-width="1.6"
                        stroke-linecap="round"
                    />
                    <rect
                        x="91"
                        y="-18"
                        width="18"
                        height="16"
                        rx="5"
                        fill="#18181a"
                    />
                </g>

                <!-- Thickness: the edge layer under the face -->
                <path
                    :d="facePath(true)"
                    fill-rule="evenodd"
                    fill="#030303"
                    :transform="`translate(${edgeShift.toFixed(2)} 6)`"
                />

                <g :clip-path="url('clip')">
                    <rect
                        x="0"
                        y="0"
                        :width="FACE_W"
                        :height="FACE_H"
                        :fill="url('carbon')"
                    />

                    <!-- FRONT -->
                    <g v-if="face === 'front'">
                        <template v-if="hasArt">
                            <image
                                :href="frontArt ?? undefined"
                                :x="art.x"
                                :y="art.y"
                                :width="art.w"
                                :height="art.h"
                                preserveAspectRatio="none"
                            />
                            <rect
                                x="0"
                                y="0"
                                :width="FACE_W"
                                :height="fadeTop"
                                :fill="TAG_COLORS.carbon"
                            />
                            <rect
                                x="0"
                                :y="fadeTop - 0.5"
                                :width="FACE_W"
                                :height="FRONT.artFade + 1"
                                :fill="url('fade')"
                            />
                            <rect
                                x="0"
                                y="0"
                                :width="FACE_W"
                                :height="FACE_H"
                                :fill="url('vignette')"
                            />
                        </template>
                        <template v-else>
                            <rect
                                x="0"
                                y="0"
                                :width="FACE_W"
                                :height="FACE_H"
                                :fill="url('glow')"
                            />
                            <g
                                fill="none"
                                :stroke="TAG_COLORS.gold"
                                stroke-width="0.8"
                                opacity="0.42"
                            >
                                <path
                                    v-for="d in CONTOUR_PATHS"
                                    :key="d"
                                    :d="d"
                                />
                            </g>
                            <text
                                v-if="yearText"
                                x="100"
                                y="236"
                                text-anchor="middle"
                                class="tag-visualizer__year"
                                :font-size="yearFont"
                                fill="none"
                                :stroke="TAG_COLORS.gold"
                                stroke-width="0.9"
                                stroke-opacity="0.85"
                            >
                                {{ yearText }}
                            </text>
                        </template>

                        <!-- Header: monogram, gold rule, name, discipline -->
                        <image
                            :href="MONOGRAM_SRC"
                            :x="100 - FRONT.monogramW / 2"
                            :y="
                                FRONT.monogramY - monogramH(FRONT.monogramW) / 2
                            "
                            :width="FRONT.monogramW"
                            :height="monogramH(FRONT.monogramW)"
                        />
                        <rect
                            :x="100 - FRONT.ruleW / 2"
                            :y="FRONT.ruleY"
                            :width="FRONT.ruleW"
                            height="0.8"
                            :fill="TAG_COLORS.gold"
                        />
                        <text
                            :x="100 + (nameFont * NAME_SPACING) / 2"
                            :y="FRONT.nameY"
                            text-anchor="middle"
                            class="tag-visualizer__caps"
                            font-weight="600"
                            :font-size="nameFont"
                            :letter-spacing="nameFont * NAME_SPACING"
                            fill="#f1e6d4"
                        >
                            {{ text.name }}
                        </text>
                        <text
                            v-if="text.discipline"
                            :x="100 + (disciplineFont * DISCIPLINE_SPACING) / 2"
                            :y="FRONT.disciplineY"
                            text-anchor="middle"
                            class="tag-visualizer__caps"
                            font-weight="500"
                            :font-size="disciplineFont"
                            :letter-spacing="
                                disciplineFont * DISCIPLINE_SPACING
                            "
                            :fill="TAG_COLORS.goldSoft"
                        >
                            {{ text.discipline }}
                        </text>
                    </g>

                    <!-- BACK: carbon, palms, monogram, phrase, NFC (no QR) -->
                    <g v-else>
                        <path
                            v-for="(palm, index) in BACK_PALMS"
                            :key="index"
                            :d="palm.d"
                            :fill="
                                palm.light
                                    ? TAG_COLORS.palmLight
                                    : TAG_COLORS.palm
                            "
                            :fill-opacity="palm.light ? 0.38 : 0.62"
                        />
                        <image
                            :href="MONOGRAM_SRC"
                            :x="100 - BACK.monogramW / 2"
                            :y="BACK.monogramY - monogramH(BACK.monogramW) / 2"
                            :width="BACK.monogramW"
                            :height="monogramH(BACK.monogramW)"
                        />
                        <rect
                            :x="100 - BACK.ruleW / 2"
                            :y="BACK.ruleY"
                            :width="BACK.ruleW"
                            height="0.8"
                            :fill="TAG_COLORS.gold"
                        />
                        <text
                            v-for="(line, index) in text.back"
                            :key="line"
                            :x="100 + (backFont * BACK_SPACING) / 2"
                            :y="BACK.lineY + index * BACK.lineGap"
                            text-anchor="middle"
                            class="tag-visualizer__caps"
                            font-weight="500"
                            :font-size="backFont"
                            :letter-spacing="backFont * BACK_SPACING"
                            :fill="TAG_COLORS.goldSoft"
                        >
                            {{ line }}
                        </text>
                        <g
                            :transform="`translate(102 ${BACK.nfcY}) scale(0.9)`"
                            fill="none"
                            :stroke="TAG_COLORS.gold"
                            stroke-width="2.2"
                            stroke-linecap="round"
                        >
                            <path v-for="d in NFC_ARCS" :key="d" :d="d" />
                            <circle
                                cx="-8"
                                cy="0"
                                r="2"
                                :fill="TAG_COLORS.gold"
                                stroke="none"
                            />
                        </g>
                    </g>

                    <rect
                        x="0"
                        y="0"
                        :width="FACE_W"
                        :height="FACE_H"
                        :filter="url('grain')"
                        opacity="0.06"
                        style="mix-blend-mode: overlay"
                    />
                    <rect
                        x="0"
                        y="0"
                        :width="FACE_W"
                        :height="FACE_H"
                        :fill="url('sheen')"
                    />
                </g>

                <!-- Rim highlight -->
                <path
                    :d="facePath(true)"
                    fill="none"
                    :stroke="url('rim')"
                    stroke-width="1.1"
                />

                <!-- Loop strands through the slot, over the top edge -->
                <g stroke-linecap="round">
                    <path
                        d="M96 -4 L96 22"
                        stroke="#121214"
                        stroke-width="6.5"
                    />
                    <path
                        d="M104 -4 L104 22"
                        stroke="#1a1a1c"
                        stroke-width="6.5"
                    />
                    <path
                        d="M94.6 -2 L94.6 18"
                        stroke="#fff"
                        stroke-opacity="0.12"
                        stroke-width="1"
                    />
                </g>
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

.tag-visualizer__caps,
.tag-visualizer__year {
    font-family: Montserrat, var(--font-sans);
}

.tag-visualizer__year {
    font-weight: 300;
}

@media (prefers-reduced-motion: reduce) {
    .tag-visualizer__tilt {
        transition: none;
        transform: none !important;
    }
}
</style>
