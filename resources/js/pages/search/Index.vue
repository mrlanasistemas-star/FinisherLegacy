<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import EventCard from '@/components/public/EventCard.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import { Button } from '@/components/ui/button';
import { search as searchRoute } from '@/routes';
import type {
    CommunityAthlete,
    EventEditionCard,
    PublicProductCard,
} from '@/types';

const props = defineProps<{
    query: string;
    searched: boolean;
    athletes: CommunityAthlete[];
    events: EventEditionCard[];
    products: PublicProductCard[];
}>();

const term = ref(props.query);

function submit() {
    router.get(
        searchRoute().url,
        { q: term.value.trim() },
        { preserveState: true },
    );
}
</script>

<template>
    <SeoHead
        title="Buscar"
        description="Busca atletas, eventos y productos en Finisher Legacy."
        noindex
    />

    <section class="fl-container max-w-5xl py-12 sm:py-16">
        <h1 class="fl-display text-4xl sm:text-5xl">Buscar</h1>
        <form class="mt-6 flex gap-2" role="search" @submit.prevent="submit">
            <label for="search-input" class="sr-only"
                >Buscar atletas, eventos o productos</label
            >
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    id="search-input"
                    v-model="term"
                    type="search"
                    autofocus
                    placeholder="Atletas, eventos, productos…"
                    class="h-12 w-full rounded-full border border-input bg-card pr-4 pl-11 text-[15px] focus:border-foreground/30 focus:ring-2 focus:ring-ring/30 focus:outline-none"
                />
            </div>
            <Button type="submit" class="h-12 rounded-full px-6">Buscar</Button>
        </form>

        <p v-if="!searched" class="mt-6 text-sm text-muted-foreground">
            Escribe al menos 2 letras para buscar.
        </p>

        <template v-else>
            <p
                v-if="!athletes.length && !events.length && !products.length"
                class="mt-10 rounded-xl border border-dashed border-foreground/15 bg-card p-8 text-center text-muted-foreground"
            >
                No encontramos resultados para “{{ query }}”.
            </p>

            <section v-if="athletes.length" class="mt-12">
                <h2 class="fl-eyebrow">Atletas</h2>
                <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                    <li
                        v-for="athlete in athletes"
                        :key="athlete.username ?? athlete.name"
                    >
                        <Link
                            :href="`/@${athlete.username}`"
                            class="fl-card flex items-center gap-3 p-4 transition-colors hover:border-foreground/20"
                        >
                            <AthleteAvatar
                                :name="athlete.name"
                                :photo-url="athlete.photo_url"
                            />
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{
                                    athlete.name
                                }}</span>
                                <span
                                    class="block truncate text-sm text-muted-foreground"
                                    >@{{ athlete.username
                                    }}<template v-if="athlete.sport">
                                        · {{ athlete.sport }}</template
                                    ></span
                                >
                            </span>
                        </Link>
                    </li>
                </ul>
            </section>

            <section v-if="events.length" class="mt-12">
                <h2 class="fl-eyebrow">Eventos</h2>
                <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <EventCard
                        v-for="edition in events"
                        :key="edition.id"
                        :edition="edition"
                        compact
                    />
                </div>
            </section>

            <section v-if="products.length" class="mt-12">
                <h2 class="fl-eyebrow">Tienda</h2>
                <div
                    class="mt-4 grid grid-cols-2 gap-x-4 gap-y-10 lg:grid-cols-4"
                >
                    <ProductCard
                        v-for="product in products"
                        :key="product.uuid"
                        :name="product.name"
                        :slug="product.slug"
                        :category="product.category"
                        :from-price-minor="product.from_price_minor"
                        :currency="product.currency"
                        :in-stock="product.in_stock"
                        :image-url="product.image_url"
                        :image-alt="product.image_alt"
                        :hover-image-url="product.hover_image_url"
                        :variant-count="product.variant_count"
                        :tagline="product.tagline"
                        :availability="product.availability"
                        :availability-label="product.availability_label"
                        :compare-at-minor="product.compare_at_minor"
                        :promotion-label="product.promotion_label"
                    />
                </div>
            </section>
        </template>
    </section>
</template>
