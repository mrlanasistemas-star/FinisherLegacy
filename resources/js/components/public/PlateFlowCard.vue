<script setup lang="ts">
import { ChevronRight } from '@lucide/vue';

withDefaults(
    defineProps<{
        eyebrow: string;
        title: string;
        description: string;
        chain: string[];
        highlighted?: boolean;
    }>(),
    {
        highlighted: false,
    },
);
</script>

<template>
    <div
        class="flex flex-col gap-6 rounded-2xl border p-8 transition-colors"
        :class="
            highlighted
                ? 'border-fl-gold/40 bg-gradient-to-b from-fl-gold/10 to-card/40'
                : 'border-border bg-card/40'
        "
    >
        <span
            class="text-xs font-semibold tracking-[0.25em] uppercase"
            :class="highlighted ? 'text-fl-gold-ink' : 'text-muted-foreground'"
        >
            {{ eyebrow }}
        </span>

        <h3 class="text-2xl font-bold text-foreground">{{ title }}</h3>

        <div class="flex flex-wrap items-center gap-2">
            <template v-for="(step, index) in chain" :key="step">
                <span
                    class="rounded-full border px-3 py-1.5 text-sm font-medium"
                    :class="
                        highlighted
                            ? 'border-fl-gold/40 bg-background text-fl-gold-ink'
                            : 'border-border bg-background text-muted-foreground'
                    "
                >
                    {{ step }}
                </span>
                <ChevronRight
                    v-if="index < chain.length - 1"
                    class="size-4 shrink-0 text-muted-foreground/80"
                />
            </template>
        </div>

        <p class="text-sm leading-relaxed text-muted-foreground">
            {{ description }}
        </p>
    </div>
</template>
