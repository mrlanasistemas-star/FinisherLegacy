<script setup lang="ts">
/**
 * The standard width for operational/listing screens (brief §41-§44:
 * "no quiero módulos operativos en max-w-3xl dentro de una pantalla
 * 1920px") — one place to change instead of a max-width hand-copied onto
 * every dashboard/grid page. Forms/reading content still pick their own
 * narrower max-width where that's the right call (brief §50) — this is
 * only for pages whose job is to show a grid or list of things.
 */
withDefaults(
    defineProps<{
        as?: string;
    }>(),
    { as: 'div' },
);
</script>

<template>
    <component
        :is="as"
        class="mx-auto w-full max-w-[1600px] px-4 sm:px-6 lg:px-8"
    >
        <slot />
    </component>
</template>
