<script setup lang="ts">
/**
 * Page-level tips for the site guide. The mascot itself now lives once, in
 * PublicLayout (components/mascot/SiteGuide.vue); a page that wants a
 * step-by-step "recorrido" mounts this with its own `welcome` + `tips`
 * (same API as before) and the guide shows them in its speech card, with
 * Antes/Sigue buttons that scroll to each `[data-mascot-tip="<id>"]`
 * section. Renders nothing by itself.
 */
import { onBeforeUnmount, onMounted, watch } from 'vue';
import type { GuideTip } from '@/components/mascot/guideStore';
import { registerPageTour } from '@/components/mascot/guideStore';

const props = defineProps<{
    welcome: string;
    tips: GuideTip[];
}>();

let release: (() => void) | undefined;

function register() {
    release?.();
    release = registerPageTour({ welcome: props.welcome, tips: props.tips });
}

onMounted(register);
watch(() => [props.welcome, props.tips], register);
onBeforeUnmount(() => release?.());
</script>

<template>
    <span hidden aria-hidden="true" />
</template>
