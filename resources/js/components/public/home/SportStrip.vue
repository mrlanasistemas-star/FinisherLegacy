<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index as eventsIndex } from '@/routes/events';
import type { PublicSport } from '@/types';

/** Disciplines straight from the `sports` table — each opens Eventos filtered. */
defineProps<{ sports: PublicSport[] }>();
</script>

<template>
    <section
        v-if="sports.length"
        class="border-y border-border bg-card"
        aria-labelledby="disciplinas-title"
    >
        <div
            class="fl-container flex flex-col gap-4 py-6 lg:flex-row lg:items-center lg:gap-10"
        >
            <h2
                id="disciplinas-title"
                class="fl-eyebrow shrink-0 text-muted-foreground"
            >
                Disciplinas
            </h2>
            <ul
                class="-mx-4 flex snap-x gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0 sm:pb-0"
            >
                <li
                    v-for="sport in sports"
                    :key="sport.slug"
                    class="shrink-0 snap-start"
                >
                    <Link
                        :href="eventsIndex({ query: { sport: sport.slug } })"
                        class="inline-flex items-center rounded-full border border-border bg-background px-4 py-2 font-serif text-[17px] text-foreground transition-colors hover:border-fl-gold hover:bg-fl-cream"
                    >
                        {{ sport.name }}
                    </Link>
                </li>
            </ul>
        </div>
    </section>
</template>
