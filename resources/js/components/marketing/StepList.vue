<script setup lang="ts">
/**
 * Numbered steps on a vertical gold rail (01, 02, 03…). Each step reveals
 * in order as it scrolls into view. `columns` lays them out as a row of
 * cards from lg up instead (use it for 3–4 short steps).
 */
import { vReveal } from '@/composables/useReveal';

withDefaults(
    defineProps<{
        items: { title: string; text: string }[];
        layout?: 'rail' | 'columns';
    }>(),
    { layout: 'rail' },
);

const number = (index: number) => String(index + 1).padStart(2, '0');
</script>

<template>
    <ol
        v-if="layout === 'rail'"
        class="relative space-y-8 border-l border-fl-gold/45 pl-7 sm:pl-9"
    >
        <li
            v-for="(item, index) in items"
            :key="item.title"
            v-reveal="{ index }"
            class="relative"
        >
            <span
                class="absolute top-0.5 -left-[calc(1.75rem+1rem)] flex size-8 items-center justify-center rounded-full border border-fl-gold bg-white text-[11px] font-semibold text-fl-gold-ink sm:-left-[calc(2.25rem+1rem)]"
                aria-hidden="true"
                >{{ number(index) }}</span
            >
            <h3
                class="font-serif text-lg leading-snug font-semibold text-foreground sm:text-xl"
            >
                {{ item.title }}
            </h3>
            <p
                class="mt-1.5 max-w-md text-[0.95rem] leading-relaxed text-muted-foreground"
            >
                {{ item.text }}
            </p>
        </li>
    </ol>

    <ol
        v-else
        class="grid gap-4 sm:grid-cols-2"
        :class="items.length >= 4 ? 'lg:grid-cols-4' : 'lg:grid-cols-3'"
    >
        <li
            v-for="(item, index) in items"
            :key="item.title"
            v-reveal="{ index }"
            class="fl-card fl-card-hover relative flex gap-4 overflow-hidden p-5 sm:flex-col sm:gap-0 sm:p-7"
        >
            <span
                class="w-10 shrink-0 font-serif text-[1.75rem] leading-none font-semibold text-fl-gold/70 sm:w-auto sm:text-[2.6rem]"
                aria-hidden="true"
                >{{ number(index) }}</span
            >
            <div class="sm:mt-4">
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
    </ol>
</template>
