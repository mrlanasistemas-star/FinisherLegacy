<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Boxes } from '@lucide/vue';
import LegacyPlateCard from '@/components/shared/LegacyPlateCard.vue';

type PlateRow = {
    id: number;
    serial_number: string | null;
    status: string;
    event_name: string | null;
    race_name: string | null;
    engraving_display_name: string | null;
    legacy_code: string | null;
    produced_at: string | null;
    delivered_at: string | null;
};

defineProps<{ plates: PlateRow[] }>();
</script>

<template>
    <Head title="Mis Legacy Plates" />

    <div class="w-full px-4 py-4 sm:px-6 md:py-6 lg:px-8 xl:px-10">
        <h1 class="text-xl font-bold text-white">Mis Legacy Plates</h1>
        <p class="mt-1 text-sm text-white/50">
            La pieza física que guarda tu historia deportiva.
        </p>

        <div
            v-if="plates.length"
            class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <LegacyPlateCard
                v-for="plate in plates"
                :key="plate.id"
                :serial-number="plate.serial_number"
                :status="plate.status"
                :event-name="plate.event_name"
                :race-name="plate.race_name"
                :engraving-display-name="plate.engraving_display_name"
                :legacy-code="plate.legacy_code"
            />
        </div>

        <div
            v-else
            class="mt-16 flex flex-col items-center gap-3 py-16 text-center text-white/30"
        >
            <Boxes class="size-10" />
            <p>Todavía no tienes una Legacy Plate.</p>
            <Link href="/events" class="text-fl-gold-soft hover:underline"
                >Explorar eventos</Link
            >
        </div>
    </div>
</template>
