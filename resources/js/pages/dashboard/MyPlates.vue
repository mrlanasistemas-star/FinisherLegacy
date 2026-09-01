<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Boxes } from '@lucide/vue';
import AppContainer from '@/components/shared/AppContainer.vue';
import LegacyPlateCard from '@/components/shared/LegacyPlateCard.vue';

type PlateEntitlement = {
    id: number;
    presale_status: string;
    event_name: string | null;
    edition_name: string | null;
    race_name: string | null;
    model_name: string | null;
    price_type: string | null;
    paid_at: string | null;
    plate: {
        id: number;
        serial_number: string | null;
        engraving_display_name: string | null;
        legacy_code: string | null;
        produced_at: string | null;
        delivered_at: string | null;
    } | null;
};

defineProps<{ plates: PlateEntitlement[] }>();
</script>

<template>
    <Head title="Mis Legacy Plates" />

    <AppContainer class="py-4 md:py-6">
        <h1 class="text-xl font-bold text-white">Mis Legacy Plates</h1>
        <p class="mt-1 text-sm text-white/50">
            La pieza física que guarda tu historia deportiva — incluye tus
            preventas, aunque el evento todavía no haya pasado.
        </p>

        <div
            v-if="plates.length"
            class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <LegacyPlateCard
                v-for="entitlement in plates"
                :key="entitlement.id"
                :presale-status="entitlement.presale_status"
                :event-name="entitlement.event_name"
                :edition-name="entitlement.edition_name"
                :race-name="entitlement.race_name"
                :model-name="entitlement.model_name"
                :engraving-display-name="
                    entitlement.plate?.engraving_display_name ?? null
                "
                :serial-number="entitlement.plate?.serial_number ?? null"
                :legacy-code="entitlement.plate?.legacy_code ?? null"
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
    </AppContainer>
</template>
