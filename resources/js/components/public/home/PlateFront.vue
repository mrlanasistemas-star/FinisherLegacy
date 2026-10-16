<script setup lang="ts">
/**
 * The Legacy Plate V3 front (70 × 45 mm) as a crisp inline SVG — the same
 * drawing as the product renders (resources/js/lib/plate-art.ts) — with
 * an optional slow light sweep across the metal. Decorative: the caller
 * provides the accessible description.
 */
import { computed, useId } from 'vue';
import { SAMPLE_FRONT, plateSvg } from '@/lib/plate-art';
import type { PlateFrontData } from '@/lib/plate-art';

const props = withDefaults(
    defineProps<{ data?: PlateFrontData; sweep?: boolean }>(),
    { data: () => SAMPLE_FRONT, sweep: true },
);

const uid = `pf${useId().replace(/[^a-zA-Z0-9-]/g, '')}`;
const svg = computed(() =>
    plateSvg(
        'front',
        uid,
        props.data,
        {},
        '',
        'class="block h-auto w-full" aria-hidden="true" focusable="false"',
    ),
);
</script>

<template>
    <div
        class="fl-plate relative"
        :class="sweep ? 'fl-plate--sweep' : ''"
        aria-hidden="true"
    >
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div v-html="svg" />
    </div>
</template>

<style scoped>
.fl-plate {
    filter: drop-shadow(0 28px 30px rgb(23 23 20 / 0.28))
        drop-shadow(0 4px 6px rgb(23 23 20 / 0.18));
}
/* "Light sweep over metal" (owner spec §46): one slow pass, then rest. */
.fl-plate--sweep::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 7%;
    background: linear-gradient(
        110deg,
        transparent 35%,
        rgb(255 255 255 / 0.55) 48%,
        transparent 60%
    );
    background-size: 250% 100%;
    background-position: 130% 0;
    mix-blend-mode: soft-light;
    pointer-events: none;
    animation: fl-plate-sweep 6s cubic-bezier(0.45, 0, 0.2, 1) 1.2s infinite;
}
@keyframes fl-plate-sweep {
    0% {
        background-position: 130% 0;
    }
    35%,
    100% {
        background-position: -40% 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .fl-plate--sweep::after {
        animation: none;
        opacity: 0;
    }
}
</style>
