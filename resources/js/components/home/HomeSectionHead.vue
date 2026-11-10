<script setup lang="ts">
/**
 * The shared heading of a Home section: gold eyebrow, big Montserrat title
 * and an optional lead paragraph. `tone="dark"` is for the carbon sections.
 */
import { vReveal } from '@/composables/useReveal';

withDefaults(
    defineProps<{
        id: string;
        eyebrow: string;
        title: string;
        lead?: string | null;
        align?: 'left' | 'center';
        tone?: 'light' | 'dark';
        size?: 'lg' | 'md';
    }>(),
    { lead: null, align: 'left', tone: 'light', size: 'lg' },
);
</script>

<template>
    <div
        v-reveal
        :class="
            align === 'center' ? 'mx-auto max-w-3xl text-center' : 'max-w-2xl'
        "
    >
        <p
            class="fl-eyebrow inline-flex items-center gap-2"
            :class="tone === 'dark' ? '!text-fl-gold' : ''"
        >
            <span
                class="inline-block h-px w-6"
                :class="tone === 'dark' ? 'bg-fl-gold/70' : 'bg-fl-gold-dim/70'"
                aria-hidden="true"
            />
            {{ eyebrow }}
        </p>
        <h2
            :id="id"
            class="fl-display mt-4 text-balance"
            :class="[
                size === 'lg'
                    ? 'text-[clamp(2rem,4.4vw,3.6rem)]'
                    : 'text-[clamp(1.8rem,3.4vw,2.75rem)]',
                tone === 'dark' ? '!text-white' : '',
            ]"
        >
            {{ title }}
        </h2>
        <p
            v-if="lead"
            class="mt-5 text-[1.0625rem] leading-relaxed text-pretty sm:text-lg"
            :class="[
                tone === 'dark' ? 'text-white/70' : 'text-muted-foreground',
                align === 'center' ? 'mx-auto max-w-2xl' : 'max-w-xl',
            ]"
        >
            {{ lead }}
        </p>
    </div>
</template>
