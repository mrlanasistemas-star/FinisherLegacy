<script setup lang="ts">
export type HowItWorksStep = {
    number: string;
    title: string;
    description: string;
};

defineProps<{
    steps: HowItWorksStep[];
}>();
</script>

<template>
    <div
        class="grid gap-6 sm:grid-cols-2 lg:grid-cols-[repeat(auto-fit,minmax(14rem,1fr))]"
    >
        <div
            v-for="step in steps"
            :key="step.number"
            class="group relative flex flex-col gap-4 rounded-card border border-border bg-card/50 p-6 shadow-fl-soft transition-colors hover:border-fl-gold/30"
        >
            <span
                class="legacy-numeric text-4xl font-black text-muted-foreground/80 transition-colors group-hover:text-fl-gold-ink"
            >
                {{ step.number }}
            </span>
            <h3 class="text-lg font-semibold text-foreground">
                {{ step.title }}
            </h3>
            <p class="text-sm leading-relaxed text-muted-foreground">
                {{ step.description }}
            </p>
        </div>
    </div>
</template>
