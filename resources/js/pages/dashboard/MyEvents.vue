<script setup lang="ts">
/**
 * "Mis eventos" (spec §7/§134): the races ahead — registrations and
 * pre-registrations — and every race already run, newest first, a page at
 * a time. Each past race opens its event story.
 */
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Flag } from '@lucide/vue';
import EventStoryCard from '@/components/legado/EventStoryCard.vue';
import UpcomingEventCard from '@/components/legado/UpcomingEventCard.vue';
import Reveal from '@/components/motion/Reveal.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { index as eventsIndex } from '@/routes/events';
import type { LegadoEntry, LegadoTotals, UpcomingEntry } from '@/types/legado';

defineProps<{
    upcoming: UpcomingEntry[];
    past: {
        data: LegadoEntry[];
        links: PaginationLink[];
        total: number;
        current_page: number;
    };
    totals: LegadoTotals;
}>();
</script>

<template>
    <Head title="Mis eventos" />

    <div class="fl-container py-6 md:py-10">
        <header>
            <p class="fl-eyebrow">Mi espacio</p>
            <h1 class="fl-display mt-2 text-4xl sm:text-5xl">Mis eventos</h1>
            <p class="mt-3 max-w-xl text-muted-foreground">
                Lo que viene y todo lo que ya corriste.
                <template v-if="totals.races">
                    {{ totals.races }}
                    {{ totals.races === 1 ? 'carrera' : 'carreras' }}
                    <template v-if="totals.km"
                        >y {{ totals.km.toLocaleString('es-MX') }} km</template
                    >
                    en tu historia.
                </template>
            </p>
        </header>

        <!-- Upcoming -->
        <section class="mt-10" aria-labelledby="upcoming-title">
            <h2
                id="upcoming-title"
                class="font-serif text-2xl font-medium sm:text-3xl"
            >
                Próximos
            </h2>
            <div
                v-if="upcoming.length"
                class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <UpcomingEventCard
                    v-for="(entry, index) in upcoming"
                    :key="`${entry.kind}-${entry.id ?? index}-${entry.event_slug}`"
                    :entry="entry"
                />
            </div>
            <div
                v-else
                class="mt-5 flex flex-col gap-4 rounded-2xl border border-dashed border-foreground/15 bg-card p-6 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-muted-foreground">
                    No tienes carreras próximas. Encuentra tu siguiente reto.
                </p>
                <Button as-child class="w-fit rounded-full">
                    <Link :href="eventsIndex()">
                        Explorar eventos
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
            </div>
        </section>

        <!-- Past -->
        <section class="mt-14" aria-labelledby="past-title">
            <div class="flex items-end justify-between gap-3">
                <h2
                    id="past-title"
                    class="font-serif text-2xl font-medium sm:text-3xl"
                >
                    Carreras corridas
                </h2>
                <span v-if="past.total" class="text-sm text-muted-foreground"
                    >{{ past.total }} en total</span
                >
            </div>

            <ol v-if="past.data.length" class="mt-5 grid gap-4 xl:grid-cols-2">
                <li v-for="(entry, index) in past.data" :key="entry.id">
                    <Reveal :delay-ms="Math.min(index, 3) * 50" class="h-full">
                        <EventStoryCard :entry="entry" class="h-full" />
                    </Reveal>
                </li>
            </ol>
            <div
                v-else
                class="mt-5 flex flex-col items-center gap-3 rounded-2xl border border-dashed border-foreground/15 bg-card px-6 py-14 text-center"
            >
                <Flag class="size-8 text-fl-gold-ink" />
                <p class="font-serif text-2xl">
                    Tu primera meta te está esperando.
                </p>
                <p class="max-w-md text-sm text-muted-foreground">
                    Cuando termines un evento, tu resultado, fotos y medalla
                    quedarán guardados aquí.
                </p>
            </div>

            <Pagination
                v-if="past.data.length"
                :links="past.links"
                class="mt-8"
            />
        </section>
    </div>
</template>
