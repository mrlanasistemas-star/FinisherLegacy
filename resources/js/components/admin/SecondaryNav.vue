<script setup lang="ts">
/**
 * @deprecated The admin shell renders the workspace tabs for every admin
 * URL (components/admin/WorkspaceTabs.vue, driven by `adminSections` in
 * config/navigation.ts), so this per-page bar would only duplicate them.
 * Kept as an empty component so existing pages keep compiling; remove the
 * import from a page whenever you touch it.
 */
defineProps<{
    items?: { label: string; href: string; permission?: string | null }[];
}>();
</script>

<template>
    <template v-if="false" />
</template>
