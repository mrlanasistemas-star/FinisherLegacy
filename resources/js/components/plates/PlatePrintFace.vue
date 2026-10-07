<script setup lang="ts">
/**
 * The printed FRONT of a Legacy Plate, drawn as SVG in the layout's real
 * millimetres — the same geometry the print uses, so what you see is what
 * gets printed (resin-coated, never laser-engraved). Legacy Plate V3 is
 * front-only: the back is the stainless money clip (see PlateClipBack).
 * Each layout has its own composition (`layout_style`):
 *   nucleo    — editorial: two-line serif name, big time, black FL panel
 *   distancia — the distance as a giant numeral, champagne FL strip
 *   trayecto  — technical cells with labels and hairlines
 * The NFC inlay sits under the FL panel (on ferrite); with `showGuides`
 * it is hinted there — it is never part of the print.
 *
 * face="back" is only drawn for HISTORICAL v2 snapshots (spec_version
 * 'v2', 90 × 34 mm), which really had a printed back.
 */
import { computed } from 'vue';
import type {
    LegacyPlateFace,
    LegacyPlateModelData,
    LegacyPlateModelField,
    LegacyPlatePersonalization,
} from '@/types/plates';

const props = withDefaults(
    defineProps<{
        model: LegacyPlateModelData;
        face: LegacyPlateFace;
        personalization?: LegacyPlatePersonalization;
        /** Editor mode: dashed printable area, safe margin and field boxes. */
        showGuides?: boolean;
        selectedKey?: string | null;
        /** Overrides model.fields (the editor passes its live draft). */
        fields?: LegacyPlateModelField[];
    }>(),
    {
        personalization: () => ({}),
        showGuides: false,
        selectedKey: null,
        fields: undefined,
    },
);

const emit = defineEmits<{
    fieldPointerDown: [
        event: PointerEvent,
        field: LegacyPlateModelField,
        handle: 'move' | 'resize',
    ];
    backgroundPointerDown: [];
}>();

const sampleValues: Record<string, string> = {
    athlete_name: 'JOSÉ ALBERTO CARLOS BUENO',
    race_label: '21K',
    official_time: '01:44:51',
    pace: '4:58 min/km',
    event_name: 'MEDIO MARATÓN',
    event_date: '22 MAY 2026',
    distance: '21.1 km',
    overall_position: '#128',
    bib_number: '#482',
};

const v3Labels: Record<string, string> = {
    distance: 'DISTANCE',
    pace: 'PACE',
};

const labels: Record<string, string> = {
    athlete_name: 'ATLETA',
    race_label: 'PRUEBA',
    official_time: 'TIEMPO',
    pace: 'RITMO',
    event_name: 'EVENTO',
    event_date: 'FECHA',
    distance: 'DISTANCIA',
    overall_position: 'POSICIÓN',
    bib_number: 'DORSAL',
};

const FL_PATH =
    'M52 618 L205 268 C252 160 300 66 452 52 L915 52 L863 167 L532 168 C470 172 442 196 418 244 L397 294 L752 294 C722 372 676 410 620 411 L348 412 L258 618 Z M956 52 L1157 52 L971 482 L1347 482 C1312 576 1270 612 1190 618 L707 618 Z';

const style = computed(() => props.model.layout_style ?? 'nucleo');
/** Historical v2 snapshot (printed back, 90 × 34). Everything else is V3. */
const legacy = computed(() => props.model.spec_version === 'v2');
const W = computed(() => props.model.width_mm);
const H = computed(() => props.model.height_mm);

const faceFields = computed(() =>
    (props.fields ?? props.model.fields).filter(
        (f) => (f.face ?? 'front') === props.face && f.visible !== false,
    ),
);

const area = computed(() =>
    props.face === 'front'
        ? props.model.engraving_area
        : (props.model.back_area ?? props.model.engraving_area),
);

const background = computed(() =>
    props.face === 'front'
        ? (props.model.front_background ?? '#F3F2EE')
        : (props.model.back_background ?? '#171714'),
);
const textColor = computed(() =>
    props.face === 'front'
        ? (props.model.front_text_color ?? '#171714')
        : (props.model.back_text_color ?? '#F4EEDF'),
);
const artwork = computed(() =>
    props.face === 'front'
        ? props.model.front_artwork_url
        : props.model.back_artwork_url,
);

/** Where the FL panel (and the NFC inlay under it) sits on the front. */
const panel = computed(() => {
    if (!legacy.value) {
        if (style.value === 'distancia') {
            return { x: W.value - 13, y: 0, width: 13, height: H.value, rx: 0 };
        }

        if (style.value === 'trayecto') {
            return {
                x: W.value - 20,
                y: H.value - 22,
                width: 16.5,
                height: 19,
                rx: 2,
            };
        }

        return { x: W.value - 16, y: 3, width: 13, height: H.value - 6, rx: 2 };
    }

    if (style.value === 'distancia') {
        return { x: W.value - 14, y: 0, width: 14, height: H.value, rx: 0 };
    }

    if (style.value === 'trayecto') {
        return { x: W.value - 26, y: 3, width: 23, height: H.value - 6, rx: 2 };
    }

    return { x: W.value - 28, y: 3, width: 25, height: H.value - 6, rx: 2.2 };
});

function flTransform(cx: number, cy: number, width: number) {
    const scale = width / 1400;

    return `translate(${cx - width / 2} ${cy - (671 * scale) / 2}) scale(${scale})`;
}

/** "21.0975 km" → "21.1 km": a plate shows one decimal at most. */
function plateDistance(value: string): string {
    const match = value.match(/^\s*(\d+(?:[.,]\d+)?)\s*(.*)$/);

    if (!match) {
        return value;
    }

    const n = Number(match[1].replace(',', '.'));
    const shown = Number.isInteger(n) ? String(n) : n.toFixed(1);

    return `${shown} ${match[2] || 'km'}`.trim();
}

function valueFor(field: LegacyPlateModelField): string {
    const real = props.personalization[field.field_key];
    const value =
        (real as string | null | undefined) ||
        sampleValues[field.field_key] ||
        '';

    return !legacy.value && field.field_key === 'distance'
        ? plateDistance(value)
        : value;
}

/** V3 names may take two balanced lines when the box is tall enough. */
function linesFor(field: LegacyPlateModelField): string[] {
    const value = valueFor(field).toUpperCase();

    if (
        legacy.value ||
        field.field_key !== 'athlete_name' ||
        field.height < (field.font_size ?? 0) * 1.9
    ) {
        return [value];
    }

    const words = value.split(/\s+/).filter(Boolean);

    if (words.length < 2 || charWidth(field) * value.length <= field.width) {
        return [value];
    }

    let best = 1;
    let bestDiff = Infinity;

    for (let i = 1; i < words.length; i++) {
        const diff = Math.abs(
            words.slice(0, i).join(' ').length -
                words.slice(i).join(' ').length,
        );

        if (diff < bestDiff) {
            bestDiff = diff;
            best = i;
        }
    }

    return [words.slice(0, best).join(' '), words.slice(best).join(' ')];
}

/** Rough uppercase advance per character at the field's font size. */
function charWidth(field: LegacyPlateModelField, size = fontSize(field)) {
    const mono = fontFamily(field).startsWith('ui-monospace');

    return size * (mono ? 0.62 : isSerif(field) ? 0.7 : 0.64);
}

/** Never overflow the box: shrink the font to the longest line. */
function fittedSize(field: LegacyPlateModelField, lines: string[]) {
    const base =
        lines.length > 1
            ? Math.min(fontSize(field), field.height / 2.15)
            : fontSize(field);

    if (legacy.value) {
        return base;
    }

    const longest = Math.max(...lines.map((l) => l.length), 1);
    const fit = field.width / (longest * (charWidth(field, base) / base));

    return Math.min(base, fit);
}

const isSerif = (field: LegacyPlateModelField) =>
    style.value === 'nucleo' && field.field_key === 'athlete_name';

/** Distancia V3: the distance numeral is the hero; its unit sits below. */
const isV3HeroDistance = (field: LegacyPlateModelField) =>
    !legacy.value &&
    style.value === 'distancia' &&
    field.field_key === 'distance';

function heroParts(field: LegacyPlateModelField) {
    const match = valueFor(field).match(/^\s*([\d.,]+)\s*(.*)$/);

    return match
        ? { number: match[1], unit: (match[2] || 'KM').toUpperCase() }
        : { number: valueFor(field), unit: '' };
}

function anchor(field: LegacyPlateModelField) {
    return field.alignment === 'left'
        ? 'start'
        : field.alignment === 'right'
          ? 'end'
          : 'middle';
}

function textX(field: LegacyPlateModelField) {
    return field.alignment === 'left'
        ? field.x + 0.6
        : field.alignment === 'right'
          ? field.x + field.width - 0.6
          : field.x + field.width / 2;
}

function fontSize(field: LegacyPlateModelField) {
    return Math.min(
        field.font_size ?? field.height * 0.62,
        field.height * 0.95,
    );
}

const isName = (field: LegacyPlateModelField) =>
    ['athlete_name', 'event_name'].includes(field.field_key);

const isHeroDistance = (field: LegacyPlateModelField) =>
    legacy.value &&
    style.value === 'distancia' &&
    field.field_key === 'race_label';

/** Small caption above a value: every cell in Trayecto, a few elsewhere. */
function showLabel(field: LegacyPlateModelField) {
    if (style.value === 'trayecto') {
        return field.field_key !== 'athlete_name';
    }

    if (!legacy.value) {
        return (
            ['distance', 'pace'].includes(field.field_key) &&
            !isV3HeroDistance(field)
        );
    }

    return (
        props.face === 'back' &&
        ['overall_position', 'bib_number'].includes(field.field_key)
    );
}

function labelFor(field: LegacyPlateModelField) {
    return (
        (!legacy.value &&
            style.value !== 'trayecto' &&
            v3Labels[field.field_key]) ||
        labels[field.field_key]
    );
}

function fontFamily(field: LegacyPlateModelField) {
    if (style.value === 'nucleo' && field.field_key === 'athlete_name') {
        return 'Fraunces, Georgia, serif';
    }

    if (style.value === 'trayecto' && field.field_key !== 'athlete_name') {
        return 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace';
    }

    return 'Instrument Sans, ui-sans-serif, system-ui, sans-serif';
}

function fontWeight(field: LegacyPlateModelField) {
    if (style.value === 'nucleo' && field.field_key === 'athlete_name') {
        return 500;
    }

    if (isHeroDistance(field)) {
        return 800;
    }

    return isName(field) ? 700 : 600;
}

function letterSpacing(field: LegacyPlateModelField) {
    if (isHeroDistance(field)) {
        return -0.6;
    }

    return isName(field) ? 0.25 : 0.1;
}

function fill(field: LegacyPlateModelField) {
    if (isHeroDistance(field)) {
        return '#C9A45C';
    }

    if (
        style.value === 'nucleo' &&
        ['event_name', 'event_date'].includes(field.field_key)
    ) {
        return '#85662B';
    }

    return textColor.value;
}

const clipId = `plate-clip-${Math.random().toString(36).slice(2, 9)}`;
const radius = computed(() => Math.min(props.model.height_mm * 0.12, 4));
</script>

<template>
    <svg
        :viewBox="`0 0 ${model.width_mm} ${model.height_mm}`"
        class="block h-auto w-full select-none"
        role="img"
        :aria-label="`${model.name} — ${face === 'front' ? 'frente' : 'reverso'}`"
        @pointerdown.self="emit('backgroundPointerDown')"
    >
        <defs>
            <clipPath :id="clipId">
                <rect
                    :width="model.width_mm"
                    :height="model.height_mm"
                    :rx="radius"
                />
            </clipPath>
        </defs>

        <g :clip-path="`url(#${clipId})`">
            <rect
                :width="model.width_mm"
                :height="model.height_mm"
                :fill="background"
                @pointerdown="emit('backgroundPointerDown')"
            />
            <image
                v-if="artwork"
                :href="artwork"
                :width="model.width_mm"
                :height="model.height_mm"
                preserveAspectRatio="xMidYMid slice"
                @pointerdown="emit('backgroundPointerDown')"
            />

            <!-- Layout composition (base design; an uploaded artwork replaces it) -->
            <g v-if="!artwork" pointer-events="none">
                <template v-if="face === 'front'">
                    <rect
                        :x="panel.x"
                        :y="panel.y"
                        :width="panel.width"
                        :height="panel.height"
                        :rx="panel.rx"
                        :fill="style === 'distancia' ? '#C9A45C' : '#141413'"
                    />
                    <path
                        :d="FL_PATH"
                        :transform="
                            flTransform(
                                panel.x + panel.width / 2,
                                panel.y + panel.height / 2,
                                style === 'distancia'
                                    ? legacy
                                        ? 9
                                        : 8.5
                                    : panel.width * (legacy ? 0.56 : 0.62),
                            )
                        "
                        :fill="style === 'distancia' ? '#141413' : '#C9A45C'"
                    />
                    <!-- V3 decorations (70 × 45) -->
                    <template v-if="!legacy">
                        <line
                            v-if="style === 'nucleo'"
                            x1="4"
                            y1="18.4"
                            x2="12"
                            y2="18.4"
                            stroke="#C9A45C"
                            stroke-width="0.5"
                        />
                        <line
                            v-if="style === 'distancia'"
                            x1="4"
                            y1="34.6"
                            :x2="W - 16"
                            y2="34.6"
                            :stroke="textColor"
                            stroke-opacity="0.25"
                            stroke-width="0.2"
                        />
                        <g
                            v-if="style === 'trayecto'"
                            :stroke="textColor"
                            stroke-opacity="0.22"
                            stroke-width="0.2"
                        >
                            <line x1="4" y1="11.6" :x2="W - 4" y2="11.6" />
                            <line x1="4" y1="21.4" :x2="W - 4" y2="21.4" />
                            <line x1="48.6" y1="11.6" x2="48.6" y2="21.4" />
                            <line x1="26.6" y1="21.4" x2="26.6" y2="32.6" />
                            <line x1="4" y1="32.6" x2="47.2" y2="32.6" />
                            <line x1="17.3" y1="32.6" x2="17.3" :y2="H - 3" />
                            <line x1="32.3" y1="32.6" x2="32.3" :y2="H - 3" />
                        </g>
                    </template>
                    <line
                        v-if="legacy && style === 'nucleo'"
                        x1="6"
                        y1="21.4"
                        x2="14"
                        y2="21.4"
                        stroke="#C9A45C"
                        stroke-width="0.5"
                    />
                    <line
                        v-if="legacy && style === 'distancia'"
                        x1="41"
                        y1="6"
                        x2="41"
                        :y2="H - 6"
                        :stroke="textColor"
                        stroke-opacity="0.25"
                        stroke-width="0.25"
                    />
                    <g
                        v-if="legacy && style === 'trayecto'"
                        :stroke="textColor"
                        stroke-opacity="0.22"
                        stroke-width="0.2"
                    >
                        <line x1="5" y1="11.3" x2="60" y2="11.3" />
                        <line x1="5" y1="20.6" x2="60" y2="20.6" />
                        <line x1="21.3" y1="20.6" x2="21.3" :y2="H - 3" />
                        <line x1="35.3" y1="20.6" x2="35.3" :y2="H - 3" />
                        <line x1="48.3" y1="20.6" x2="48.3" :y2="H - 3" />
                    </g>
                </template>
                <template v-else>
                    <rect
                        x="2.5"
                        y="2.5"
                        :width="W - 5"
                        :height="H - 5"
                        rx="2"
                        fill="none"
                        stroke="#C9A45C"
                        stroke-opacity="0.55"
                        stroke-width="0.25"
                    />
                    <path
                        v-if="style === 'trayecto'"
                        :d="`M6 ${H - 7} C20 ${H - 12} 30 ${H - 4} 45 ${H - 9} S70 ${H - 13} ${W - 6} ${H - 8}`"
                        fill="none"
                        stroke="#C9A45C"
                        stroke-opacity="0.45"
                        stroke-width="0.3"
                        stroke-dasharray="0.9 0.7"
                    />
                    <path
                        :d="FL_PATH"
                        :transform="flTransform(W / 2, 4.6, 6)"
                        fill="#C9A45C"
                    />
                </template>
            </g>

            <g v-for="field in faceFields" :key="field.field_key">
                <text
                    v-if="showLabel(field)"
                    :x="textX(field)"
                    :y="field.y - 0.5"
                    :text-anchor="anchor(field)"
                    font-size="1.45"
                    font-weight="600"
                    letter-spacing="0.3"
                    :fill="textColor"
                    fill-opacity="0.55"
                    font-family="Instrument Sans, ui-sans-serif, system-ui, sans-serif"
                >
                    {{ labelFor(field) }}
                </text>
                <!-- Distancia V3: giant numeral + unit underneath -->
                <template v-if="isV3HeroDistance(field)">
                    <text
                        :x="textX(field)"
                        :y="field.y + fontSize(field) * 0.82"
                        :text-anchor="anchor(field)"
                        :font-size="fontSize(field)"
                        font-weight="800"
                        letter-spacing="-0.6"
                        :fill="textColor"
                        font-family="Instrument Sans, ui-sans-serif, system-ui, sans-serif"
                        class="legacy-numeric"
                    >
                        {{ heroParts(field).number }}
                    </text>
                    <text
                        :x="textX(field) + 0.4"
                        :y="field.y + field.height - 0.6"
                        :text-anchor="anchor(field)"
                        :font-size="Math.max(fontSize(field) * 0.24, 2)"
                        font-weight="700"
                        letter-spacing="0.8"
                        fill="#C9A45C"
                        font-family="Instrument Sans, ui-sans-serif, system-ui, sans-serif"
                    >
                        {{ heroParts(field).unit }}
                    </text>
                </template>
                <text
                    v-for="(line, i) in isV3HeroDistance(field)
                        ? []
                        : linesFor(field)"
                    :key="i"
                    :x="textX(field)"
                    :y="
                        field.y +
                        field.height / 2 +
                        (i - (linesFor(field).length - 1) / 2) *
                            fittedSize(field, linesFor(field)) *
                            1.08
                    "
                    :text-anchor="anchor(field)"
                    dominant-baseline="central"
                    :font-size="fittedSize(field, linesFor(field))"
                    :font-weight="fontWeight(field)"
                    :letter-spacing="letterSpacing(field)"
                    :fill="fill(field)"
                    :font-family="fontFamily(field)"
                    class="legacy-numeric"
                    style="text-transform: uppercase"
                >
                    {{ line }}
                </text>
            </g>
        </g>

        <!-- Plate edge -->
        <rect
            :width="model.width_mm"
            :height="model.height_mm"
            :rx="radius"
            fill="none"
            stroke="rgb(23 23 20 / 0.18)"
            stroke-width="0.25"
        />

        <template v-if="showGuides">
            <rect
                :x="area.x"
                :y="area.y"
                :width="area.width"
                :height="area.height"
                fill="none"
                stroke="#c9a45c"
                stroke-width="0.25"
                stroke-dasharray="1 0.8"
                pointer-events="none"
            />
            <!-- NFC inlay position hint: under the front FL panel (not printed) -->
            <g v-if="face === 'front'" pointer-events="none" opacity="0.75">
                <circle
                    :cx="panel.x + panel.width / 2"
                    :cy="panel.y + panel.height / 2"
                    r="6"
                    fill="none"
                    :stroke="style === 'distancia' ? '#141413' : '#c9a45c'"
                    stroke-width="0.3"
                    stroke-dasharray="0.8 0.6"
                />
                <text
                    :x="panel.x + panel.width / 2"
                    :y="panel.y + panel.height - 1.6"
                    text-anchor="middle"
                    font-size="1.6"
                    font-weight="700"
                    :fill="style === 'distancia' ? '#141413' : '#c9a45c'"
                >
                    NFC
                </text>
            </g>

            <g
                v-for="field in faceFields"
                :key="`box-${field.field_key}`"
                class="cursor-move"
            >
                <rect
                    :x="field.x"
                    :y="field.y"
                    :width="field.width"
                    :height="field.height"
                    :fill="
                        selectedKey === field.field_key
                            ? 'rgb(201 164 92 / 0.18)'
                            : 'rgb(201 164 92 / 0.06)'
                    "
                    :stroke="
                        selectedKey === field.field_key
                            ? '#c9a45c'
                            : 'rgb(201 164 92 / 0.6)'
                    "
                    :stroke-width="selectedKey === field.field_key ? 0.35 : 0.2"
                    @pointerdown.stop="
                        emit('fieldPointerDown', $event, field, 'move')
                    "
                />
                <rect
                    v-if="selectedKey === field.field_key"
                    :x="field.x + field.width - 1.6"
                    :y="field.y + field.height - 1.6"
                    width="2.2"
                    height="2.2"
                    rx="0.4"
                    fill="#171714"
                    class="cursor-nwse-resize"
                    @pointerdown.stop="
                        emit('fieldPointerDown', $event, field, 'resize')
                    "
                />
            </g>
        </template>
    </svg>
</template>
