<script setup lang="ts">
/**
 * Section header for marketing pages: gold eyebrow with a hairline, a
 * Montserrat title with an optional gold-italic `accent` line and a lede.
 * Reveals on scroll. Use `as="h1"` only when the page has no PageHero.
 */
import { vReveal } from '@/composables/useReveal';

withDefaults(
    defineProps<{
        eyebrow?: string;
        title: string;
        accent?: string;
        text?: string;
        align?: 'left' | 'center';
        as?: 'h1' | 'h2' | 'h3';
    }>(),
    { align: 'left', as: 'h2' },
);
</script>

<template>
    <div
        v-reveal
        class="flex flex-col"
        :class="
            align === 'center'
                ? 'mx-auto max-w-2xl items-center text-center'
                : 'max-w-2xl items-start'
        "
    >
        <p v-if="eyebrow" class="fl-eyebrow inline-flex items-center gap-3">
            <span class="h-px w-6 bg-fl-gold" aria-hidden="true" />
            {{ eyebrow }}
        </p>
        <component
            :is="as"
            class="fl-display text-[1.9rem] leading-[1.1] sm:text-[2.6rem]"
            :class="eyebrow ? 'mt-4' : ''"
        >
            {{ title }}
            <span
                v-if="accent"
                class="block font-medium text-fl-gold-ink italic"
                >{{ accent }}</span
            >
        </component>
        <p
            v-if="text"
            class="mt-4 text-base leading-relaxed text-muted-foreground sm:text-[1.0625rem]"
        >
            {{ text }}
        </p>
    </div>
</template>
