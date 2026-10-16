<script setup lang="ts">
/** Gear highlight — a product from the athlete's Mi equipo. */
import { Link } from '@inertiajs/vue3';
import { ShoppingBag } from '@lucide/vue';
import { index as storeIndex } from '@/routes/store/products';
import type { CommunityPost } from '@/types';

defineProps<{ gear: NonNullable<CommunityPost['gear']> }>();
</script>

<template>
    <div
        class="flex items-center gap-4 rounded-xl border border-border bg-gradient-to-br from-white to-fl-paper p-4"
    >
        <span
            class="flex size-12 shrink-0 items-center justify-center rounded-full bg-fl-cream"
            aria-hidden="true"
        >
            <ShoppingBag class="size-5 text-fl-gold-ink" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="fl-eyebrow block">Mi equipo</span>
            <span class="block truncate font-serif text-lg leading-tight">
                {{ gear.product_name ?? 'Equipo Finisher' }}
            </span>
        </span>
        <Link
            :href="storeIndex()"
            class="shrink-0 rounded-full border border-border px-3 py-2 text-xs font-medium text-foreground transition-colors hover:border-foreground/30"
        >
            Ver tienda
        </Link>
    </div>
</template>
