<script setup lang="ts">
/**
 * Home told as a story (owner spec §38-§44): who you are → what this is →
 * the journey → the NFC moment → the store → your next event → the
 * community → start. Every product, event, post and profile is real data;
 * illustrative UI is labelled "Ejemplo".
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import EventCard from '@/components/public/EventCard.vue';
import CommunityStory from '@/components/public/home/CommunityStory.vue';
import type { HomeAthlete } from '@/components/public/home/CommunityStory.vue';
import FinalCta from '@/components/public/home/FinalCta.vue';
import LegacyJourney from '@/components/public/home/LegacyJourney.vue';
import NfcMoment from '@/components/public/home/NfcMoment.vue';
import StoreStory from '@/components/public/home/StoreStory.vue';
import StoryHero from '@/components/public/home/StoryHero.vue';
import WhatIsIt from '@/components/public/home/WhatIsIt.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { index as eventsIndex } from '@/routes/events';
import type {
    CommunityPost,
    EventEditionCard,
    PublicProductCard,
} from '@/types';

defineProps<{
    featuredEditions: EventEditionCard[];
    featuredProducts: PublicProductCard[];
    communityPosts: CommunityPost[];
    communityAthletes: HomeAthlete[];
    legacyPlateSlug: string | null;
}>();
</script>

<template>
    <SeoHead
        title="Finisher Legacy — Tu historia. Tu legado."
        description="Corres, terminas y lo guardas. Finisher Legacy conecta tus carreras, tus recuerdos, tu Legacy Plate con NFC y tu comunidad en un solo lugar."
    />

    <StoryHero />

    <WhatIsIt />

    <LegacyJourney />

    <NfcMoment />

    <StoreStory :products="featuredProducts" />

    <section
        class="border-t border-border py-20 sm:py-24"
        aria-labelledby="eventos-title"
    >
        <div class="fl-container">
            <div class="flex flex-wrap items-end justify-between gap-6">
                <div>
                    <p class="fl-eyebrow">Eventos</p>
                    <h2
                        id="eventos-title"
                        class="fl-display mt-4 text-4xl sm:text-5xl"
                    >
                        Tu próxima salida.
                    </h2>
                </div>
                <Link
                    :href="eventsIndex()"
                    class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                >
                    Explorar eventos <ArrowRight class="size-4" />
                </Link>
            </div>

            <div
                v-if="featuredEditions.length"
                class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
            >
                <EventCard
                    v-for="edition in featuredEditions"
                    :key="edition.id"
                    :edition="edition"
                />
            </div>
            <p
                v-else
                class="mt-10 rounded-2xl border border-dashed border-foreground/15 bg-card p-8 text-muted-foreground"
            >
                Estamos preparando los próximos eventos. Mientras tanto, explora
                el calendario completo en
                <Link
                    :href="eventsIndex()"
                    class="font-medium text-foreground underline underline-offset-4"
                    >Eventos</Link
                >.
            </p>
        </div>
    </section>

    <CommunityStory :posts="communityPosts" :athletes="communityAthletes" />

    <FinalCta />
</template>
