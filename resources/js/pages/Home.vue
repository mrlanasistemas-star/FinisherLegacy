<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import EventCard from '@/components/public/EventCard.vue';
import AboutPreview from '@/components/public/home/AboutPreview.vue';
import ClosingCta from '@/components/public/home/ClosingCta.vue';
import CommunityPreview from '@/components/public/home/CommunityPreview.vue';
import FeatureTriptych from '@/components/public/home/FeatureTriptych.vue';
import HomeHero from '@/components/public/home/HomeHero.vue';
import SportStrip from '@/components/public/home/SportStrip.vue';
import StorePreview from '@/components/public/home/StorePreview.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { index as eventsIndex } from '@/routes/events';
import type {
    CommunityPost,
    EventEditionCard,
    HeroAthlete,
    LegacyProfilePreview as LegacyProfileType,
    PublicProductCard,
    PublicSport,
} from '@/types';

defineProps<{
    featuredEditions: EventEditionCard[];
    legacyProfile: LegacyProfileType | null;
    featuredProducts: PublicProductCard[];
    heroAthlete: HeroAthlete | null;
    sports: PublicSport[];
    communityPosts: CommunityPost[];
    legacyPlateSlug: string | null;
}>();
</script>

<template>
    <SeoHead
        title="Finisher Legacy — Tu esfuerzo merece una historia"
        description="La plataforma deportiva para guardar tus logros, encontrar tus fotos de evento, conectar con otros atletas y llevar tu legado en una Legacy Plate con NFC."
    />

    <HomeHero :athlete="heroAthlete" />

    <SportStrip :sports="sports" />

    <FeatureTriptych :legacy-plate-slug="legacyPlateSlug" />

    <CommunityPreview :posts="communityPosts" />

    <section class="fl-container py-16 sm:py-24">
        <SectionHeading
            eyebrow="Eventos"
            title="Tu próxima meta."
            description="Carreras, trails, triatlones y más. Encuentra el evento que quieres vivir."
        >
            <template #action>
                <Link
                    :href="eventsIndex()"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-foreground underline-offset-4 hover:underline"
                >
                    Ver todos los eventos
                    <ArrowRight class="size-4" />
                </Link>
            </template>
        </SectionHeading>

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
            class="mt-10 rounded-xl border border-dashed border-foreground/15 bg-card p-8 text-muted-foreground"
        >
            Estamos preparando los próximos eventos. Mientras tanto, explora el
            calendario completo en
            <Link
                :href="eventsIndex()"
                class="font-medium text-foreground underline underline-offset-4"
                >Eventos</Link
            >.
        </p>
    </section>

    <StorePreview :products="featuredProducts" />

    <AboutPreview />

    <ClosingCta />
</template>
