<script setup lang="ts">
/**
 * Grid of benefit cards (icon, title, text) with staggered reveal and the
 * shared card hover. `columns` is the desktop column count.
 */
import type { Component } from 'vue';
import { vReveal } from '@/composables/useReveal';

withDefaults(
    defineProps<{
        items: { icon?: Component; title: string; text: string }[];
        columns?: 2 | 3 | 4;
    }>(),
    { columns: 3 },
);
</script>

<template>
    <ul
        class="grid gap-4 sm:grid-cols-2 sm:gap-5"
        :class="{
            'lg:grid-cols-3': columns === 3,
            'lg:grid-cols-4': columns === 4,
        }"
    >
        <li
            v-for="(item, index) in items"
            :key="item.title"
            v-reveal="{ index }"
            class="fl-card fl-card-hover flex gap-4 p-5 sm:flex-col sm:gap-0 sm:p-7"
        >
            <span
                v-if="item.icon"
                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                aria-hidden="true"
            >
                <component :is="item.icon" class="size-5" :stroke-width="1.6" />
            </span>
            <div :class="item.icon ? 'sm:mt-5' : ''">
                <h3
                    class="font-serif text-[1.05rem] leading-snug font-semibold text-foreground sm:text-lg"
                >
                    {{ item.title }}
                </h3>
                <p
                    class="mt-1.5 text-sm leading-relaxed text-muted-foreground sm:mt-2"
                >
                    {{ item.text }}
                </p>
            </div>
        </li>
    </ul>
</template>
