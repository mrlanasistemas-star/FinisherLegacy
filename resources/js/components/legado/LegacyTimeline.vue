<script setup lang="ts">
/**
 * "Cada carrera suma una página." — the athlete's races as a timeline
 * grouped by season, newest first. Real participations only (replaces the
 * old demo-data components/public/LegacyTimeline.vue for the athlete area).
 * Cards reveal softly as they enter the viewport; content is visible by
 * default if motion never runs.
 */
import { computed } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import type { LegadoEntry } from '@/types/legado';
import EventStoryCard from './EventStoryCard.vue';

const props = defineProps<{ entries: LegadoEntry[] }>();

const seasons = computed(() => {
    const groups = new Map<string, LegadoEntry[]>();

    for (const entry of props.entries) {
        const key = entry.year ? String(entry.year) : 'Sin fecha';
        groups.set(key, [...(groups.get(key) ?? []), entry]);
    }

    return [...groups.entries()].map(([year, items]) => ({ year, items }));
});
</script>

<template>
    <div class="relative">
        <!-- The rail: a hairline that runs the length of the story -->
        <span
            class="absolute top-2 bottom-2 left-[7px] w-px bg-gradient-to-b from-fl-gold/70 via-border to-transparent md:left-[71px]"
            aria-hidden="true"
        />

        <section
            v-for="season in seasons"
            :key="season.year"
            class="relative pb-10 last:pb-0"
            :aria-label="`Temporada ${season.year}`"
        >
            <div class="relative flex items-center gap-4 md:gap-0">
                <span
                    class="relative z-10 size-[15px] shrink-0 rounded-full border-[3px] border-background bg-fl-gold ring-1 ring-fl-gold/60 md:order-2 md:ml-2"
                    aria-hidden="true"
                />
                <h3
                    class="legacy-numeric font-serif text-2xl font-medium text-foreground md:order-1 md:w-14 md:text-right md:text-xl"
                >
                    {{ season.year }}
                </h3>
                <span
                    class="ml-1 text-sm text-muted-foreground md:order-3 md:ml-4"
                >
                    {{ season.items.length }}
                    {{ season.items.length === 1 ? 'carrera' : 'carreras' }}
                </span>
            </div>

            <ol class="mt-5 space-y-4 pl-8 md:pl-[104px]">
                <li v-for="(entry, index) in season.items" :key="entry.id">
                    <Reveal :delay-ms="Math.min(index, 3) * 60">
                        <EventStoryCard :entry="entry" />
                    </Reveal>
                </li>
            </ol>
        </section>
    </div>
</template>
