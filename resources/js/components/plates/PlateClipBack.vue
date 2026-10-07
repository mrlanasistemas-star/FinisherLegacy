<script setup lang="ts">
/**
 * "Broche / Vista posterior" — the back of a Legacy Plate V3 as a PHYSICAL
 * reference: clean Zamak niquelado and the stamped stainless money clip.
 * Never editable and never printed. Drawn by resources/js/lib/plate-art.ts
 * with the layout's physical spec, so a supplier adjustment shows here.
 */
import { computed, useId } from 'vue';
import { DEFAULT_SPEC, plateProfileSvg, plateSvg } from '@/lib/plate-art';
import type { LegacyPlateModelData } from '@/types/plates';

const props = withDefaults(
    defineProps<{
        model?: LegacyPlateModelData | null;
        /** Also show the side profile with the ≈ total depth. */
        withProfile?: boolean;
    }>(),
    { model: null, withProfile: false },
);

const uid = useId().replace(/[^a-zA-Z0-9-]/g, '');

const spec = computed(() => ({
    ...DEFAULT_SPEC,
    ...(props.model
        ? { width_mm: props.model.width_mm, height_mm: props.model.height_mm }
        : {}),
    ...(props.model?.spec ?? {}),
}));

const back = computed(() =>
    plateSvg(
        'back',
        `${uid}cb`,
        {},
        spec.value,
        '',
        'class="block h-auto w-full" role="img" aria-label="Reverso de la Legacy Plate: metal limpio y clip de acero inoxidable tipo money clip"',
    ),
);
const profile = computed(() =>
    props.withProfile ? plateProfileSvg(`${uid}cp`, 'none', spec.value) : '',
);
</script>

<template>
    <div>
        <div v-html="back" />
        <div v-if="withProfile" class="mt-4 [&_svg]:w-full" v-html="profile" />
    </div>
</template>
