<script setup lang="ts">
/**
 * One printed face of a Legacy Plate, drawn as SVG in the layout's real
 * millimetres — the same geometry the print uses, so what you see is what
 * gets printed. Plates are printed (front + back), never laser-engraved,
 * and carry an NFC chip: the chip is only hinted on the back as a dashed
 * outline when `showGuides` is on — it is never part of the print.
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
    athlete_name: 'TU NOMBRE AQUÍ',
    race_label: '21K',
    official_time: '1:42:18',
    pace: '4:51 /km',
    event_name: 'MEDIO MARATÓN',
    event_date: '12/04/2026',
    distance: '21.0975 km',
    overall_position: '#128',
    bib_number: '#482',
};

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

function valueFor(field: LegacyPlateModelField): string {
    const real = props.personalization[field.field_key];

    return (
        (real as string | null | undefined) ||
        sampleValues[field.field_key] ||
        ''
    );
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

            <g v-for="field in faceFields" :key="field.field_key">
                <text
                    :x="textX(field)"
                    :y="field.y + field.height / 2"
                    :text-anchor="anchor(field)"
                    dominant-baseline="central"
                    :font-size="fontSize(field)"
                    :font-weight="isName(field) ? 800 : 600"
                    :letter-spacing="isName(field) ? 0.25 : 0.1"
                    :fill="textColor"
                    font-family="Instrument Sans, ui-sans-serif, system-ui, sans-serif"
                    class="legacy-numeric"
                    style="text-transform: uppercase"
                >
                    {{ valueFor(field) }}
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
            <!-- NFC chip position hint (not printed) -->
            <g v-if="face === 'back'" pointer-events="none" opacity="0.55">
                <circle
                    :cx="model.width_mm - 8"
                    :cy="model.height_mm - 8"
                    r="4.5"
                    fill="none"
                    stroke="#c9a45c"
                    stroke-width="0.3"
                    stroke-dasharray="0.8 0.6"
                />
                <text
                    :x="model.width_mm - 8"
                    :y="model.height_mm - 8"
                    text-anchor="middle"
                    dominant-baseline="central"
                    font-size="1.8"
                    font-weight="700"
                    fill="#c9a45c"
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
