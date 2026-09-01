<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Package } from '@lucide/vue';
import AppContainer from '@/components/shared/AppContainer.vue';
import OwnedProductCard from '@/components/shared/OwnedProductCard.vue';

type OwnedItem = {
    uuid: string;
    product: string;
    variant: string | null;
    image_url: string | null;
    status: string;
    acquired_at: string | null;
    asset_code: string | null;
};

defineProps<{ items: OwnedItem[] }>();
</script>

<template>
    <Head title="Mi equipo" />

    <AppContainer class="py-4 md:py-6">
        <h1 class="text-xl font-bold text-white">Mi equipo</h1>
        <p class="mt-1 text-sm text-white/50">
            Tu clóset digital — el equipo que te acompaña en cada meta.
        </p>

        <div
            v-if="items.length"
            class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <OwnedProductCard
                v-for="item in items"
                :key="item.uuid"
                :product="item.product"
                :variant="item.variant"
                :image-url="item.image_url"
                :status="item.status"
                :acquired-at="item.acquired_at"
                :asset-code="item.asset_code"
            />
        </div>

        <div
            v-else
            class="mt-16 flex flex-col items-center gap-3 py-16 text-center text-white/30"
        >
            <Package class="size-10" />
            <p>Todavía no tienes productos en tu equipo.</p>
            <Link href="/tienda" class="text-fl-gold-soft hover:underline"
                >Ir a la tienda</Link
            >
        </div>
    </AppContainer>
</template>
