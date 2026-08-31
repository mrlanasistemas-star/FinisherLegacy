<script setup lang="ts">
/**
 * One row of "MIS EVENTOS" (brief §39-§40) — the timeline entry point into
 * an event's full detail (medal/plate/result/media).
 */
import { Link } from '@inertiajs/vue3';
import { Boxes, Camera, Trophy } from '@lucide/vue';

defineProps<{
    id: number;
    event: string | null;
    edition: string | null;
    race: string | null;
    bibNumber: string | null;
    eventDate: string | null;
    officialTime: string | null;
    pace: string | null;
    position: number | null;
    hasPlate: boolean;
    hasMedal: boolean;
    mediaCount: number;
}>();
</script>

<template>
    <Link
        :href="`/dashboard/my-events/${id}`"
        class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-white/10 bg-fl-graphite/20 p-5 transition hover:border-fl-gold/30"
    >
        <div>
            <p class="font-semibold text-white">
                {{ event ?? edition ?? 'Evento' }}
            </p>
            <p class="text-sm text-white/50">
                <span v-if="race">{{ race }} · </span>
                <span v-if="eventDate">{{ eventDate }}</span>
                <span v-if="bibNumber"> · #{{ bibNumber }}</span>
            </p>
        </div>

        <div class="flex items-center gap-5 text-sm text-white/60">
            <div v-if="officialTime" class="text-right">
                <p class="text-white">{{ officialTime }}</p>
                <p v-if="pace" class="text-xs text-white/40">{{ pace }}</p>
            </div>
            <p v-if="position" class="text-right text-white/40">
                #{{ position }}
            </p>

            <div class="flex items-center gap-2 text-white/30">
                <Trophy v-if="hasMedal" class="size-4 text-fl-gold-soft" />
                <Boxes v-if="hasPlate" class="size-4 text-fl-gold-soft" />
                <Camera v-if="mediaCount > 0" class="size-4" />
            </div>
        </div>
    </Link>
</template>
