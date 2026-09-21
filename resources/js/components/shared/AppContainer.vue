<script setup lang="ts">
/**
 * The standard width for operational/listing screens — no max-width at
 * all (product consolidation brief §114: "todo ancho disponible... NO
 * global max-w-3xl/4xl/6xl/1600"), just consistent horizontal padding in
 * one place instead of hand-copied onto every dashboard/grid page.
 * Forms/reading content still pick their own narrower max-width where
 * that's the right call (brief §114: "Forms internos pueden usar grids o
 * width apropiado") — this is only for pages whose job is to show a grid
 * or list of things.
 */
withDefaults(
    defineProps<{
        as?: string;
    }>(),
    { as: 'div' },
);
</script>

<template>
    <component :is="as" class="w-full px-4 sm:px-6 lg:px-8">
        <slot />
    </component>
</template>
