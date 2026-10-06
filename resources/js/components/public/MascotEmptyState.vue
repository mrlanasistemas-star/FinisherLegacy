<script setup lang="ts">
import FinisherMascot from '@/components/public/FinisherMascot.vue';

defineProps<{
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-border bg-card/30 px-6 py-14 text-center"
    >
        <FinisherMascot variant="empty" />

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
