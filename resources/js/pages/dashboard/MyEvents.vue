<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Trophy } from '@lucide/vue';
import EventResultCard from '@/components/shared/EventResultCard.vue';

type Participation = {
    id: number;
    event: string | null;
    edition: string | null;
    race: string | null;
    bib_number: string | null;
    event_date: string | null;
    official_time: string | null;
    pace: string | null;
    position: number | null;
    has_plate: boolean;
    has_medal: boolean;
    media_count: number;
};

defineProps<{ participations: Participation[] }>();
</script>

<template>
    <Head title="Mis eventos" />

    <div class="w-full px-4 py-4 sm:px-6 md:py-6 lg:px-8 xl:px-10">
        <h1 class="text-xl font-bold text-white">Mis eventos</h1>
        <p class="mt-1 text-sm text-white/50">
            Cada meta que cruzaste, en un solo lugar.
        </p>

        <div v-if="participations.length" class="mt-8 space-y-3">
            <EventResultCard
                v-for="p in participations"
                :key="p.id"
                :id="p.id"
                :event="p.event"
                :edition="p.edition"
                :race="p.race"
                :bib-number="p.bib_number"
                :event-date="p.event_date"
                :official-time="p.official_time"
                :pace="p.pace"
                :position="p.position"
                :has-plate="p.has_plate"
                :has-medal="p.has_medal"
                :media-count="p.media_count"
            />
        </div>

        <div
            v-else
            class="mt-16 flex flex-col items-center gap-3 py-16 text-center text-white/30"
        >
            <Trophy class="size-10" />
            <p>Todavía no tienes eventos registrados.</p>
            <Link href="/events" class="text-fl-gold-soft hover:underline"
                >Explorar eventos</Link
            >
        </div>
    </div>
</template>
