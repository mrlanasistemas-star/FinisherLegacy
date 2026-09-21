<script setup lang="ts">
/**
 * Store product card — premium sport-tech, never stock counts, never a
 * dashboard-table look (brief §27-§28/§60/§64).
 */
import { Link } from '@inertiajs/vue3';
import { Package } from '@lucide/vue';
import Money from '@/components/shared/Money.vue';

defineProps<{
    name: string;
    slug: string;
    category?: string | null;
    fromPriceMinor: number | null;
    currency: string;
    inStock: boolean;
    imageUrl?: string | null;
    hoverImageUrl?: string | null;
    variantCount?: number;
}>();
</script>

<template>
    <Link
        :href="`/tienda/${slug}`"
        class="group block overflow-hidden rounded-2xl border border-white/10 bg-fl-graphite/30 transition hover:border-fl-gold/40"
    >
        <div class="relative aspect-square overflow-hidden bg-fl-black">
            <img
                v-if="imageUrl"
                :src="imageUrl"
                :alt="name"
                loading="lazy"
                class="size-full object-cover transition duration-500 group-hover:scale-105"
                :class="hoverImageUrl ? 'group-hover:opacity-0' : ''"
            />
            <div v-else class="flex size-full items-center justify-center">
                <Package class="size-10 text-white/10" />
            </div>
            <img
                v-if="hoverImageUrl"
                :src="hoverImageUrl"
                :alt="name"
                loading="lazy"
                class="absolute inset-0 size-full object-cover opacity-0 transition duration-500 group-hover:opacity-100"
            />
            <span
                v-if="!inStock"
                class="absolute top-3 left-3 rounded-full bg-fl-black/80 px-3 py-1 text-[10px] font-semibold tracking-wide text-white/60 uppercase"
            >
                Agotado
            </span>
        </div>
        <div class="p-4">
            <p
                v-if="category"
                class="text-[10px] tracking-[0.2em] text-fl-gold-soft/70 uppercase"
            >
                {{ category }}
            </p>
            <h3
                class="mt-1 font-semibold text-white group-hover:text-fl-gold-soft"
            >
                {{ name }}
            </h3>
            <p class="mt-2 text-sm text-white/60">
                <span v-if="fromPriceMinor !== null">
                    Desde <Money :minor="fromPriceMinor" :currency="currency" />
                </span>
                <span v-else>Precio no disponible</span>
            </p>
            <p
                v-if="variantCount && variantCount > 1"
                class="mt-1 text-xs text-white/30"
            >
                {{ variantCount }} opciones
            </p>
        </div>
    </Link>
</template>
