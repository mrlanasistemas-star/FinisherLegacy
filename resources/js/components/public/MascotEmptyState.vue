<script setup lang="ts">
import Mascot3D from '@/components/mascot/Mascot3D.vue';

defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-fl-line bg-fl-paper/60 px-6 py-12 text-center"
    >
        <Mascot3D mood="happy" class="h-36" alt="" :fps="40" />

        <div class="max-w-sm">
            <h3 class="text-base font-semibold text-foreground">{{ title }}</h3>
            <p v-if="description" class="mt-1.5 text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>

        <div v-if="$slots.default">
            <slot />
        </div>
    </div>
</template>
