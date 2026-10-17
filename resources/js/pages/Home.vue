<script setup lang="ts">
/**
 * Home told as a story (owner spec §38-§44): who you are → what this is →
 * the journey → the NFC moment → the store → your next event → the
 * community → start. Every product, event, post and profile is real data;
 * illustrative UI is labelled "Ejemplo".
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import EventCard from '@/components/public/EventCard.vue';
import CommunityStory from '@/components/public/home/CommunityStory.vue';
import type { HomeAthlete } from '@/components/public/home/CommunityStory.vue';
import FinalCta from '@/components/public/home/FinalCta.vue';
import LegacyJourney from '@/components/public/home/LegacyJourney.vue';
import NfcMoment from '@/components/public/home/NfcMoment.vue';
import StoreStory from '@/components/public/home/StoreStory.vue';
import StoryHero from '@/components/public/home/StoryHero.vue';
import WhatIsIt from '@/components/public/home/WhatIsIt.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
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

/**
 * Thin scroll-progress accent — a single gold hairline across the top that
 * fills as you move down the page, signalling "there's more below". Driven by
 * a rAF-throttled scroll listener writing a transform (scaleX) only, and only
 * mounted when motion is allowed (it's purely a progress affordance).
 */
const reducedMotion = useReducedMotion();
const progressEl = useTemplateRef<HTMLElement>('progress');
let progressFrame = 0;

function renderProgress() {
    progressFrame = 0;

    const el = progressEl.value;

    if (!el) {
        return;
    }

    const doc = document.documentElement;
    const max = doc.scrollHeight - doc.clientHeight;
    const ratio = max > 0 ? Math.min(1, Math.max(0, doc.scrollTop / max)) : 0;
    el.style.transform = `scaleX(${ratio.toFixed(4)})`;
}

function onProgressScroll() {
    if (progressFrame === 0) {
        progressFrame = requestAnimationFrame(renderProgress);
    }
}

onMounted(() => {
    if (reducedMotion.value) {
        return;
    }

    window.addEventListener('scroll', onProgressScroll, { passive: true });
    window.addEventListener('resize', onProgressScroll, { passive: true });
    renderProgress();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onProgressScroll);
    window.removeEventListener('resize', onProgressScroll);

    if (progressFrame !== 0) {
        cancelAnimationFrame(progressFrame);
    }
});
</script>

<template>
    <SeoHead
        title="Finisher Legacy — Tu historia. Tu legado."
        description="Corres, terminas y lo guardas. Finisher Legacy conecta tus carreras, tus recuerdos, tu Legacy Plate con NFC y tu comunidad en un solo lugar."
    />

    <!-- Thin scroll-progress accent (motion-only; a single gold hairline). -->
    <div
        v-if="!reducedMotion"
        class="pointer-events-none fixed inset-x-0 top-0 z-[60]"
        aria-hidden="true"
    >
        <div
            ref="progress"
            class="legacy-line origin-left"
            style="transform: scaleX(0)"
        />
    </div>

    <StoryHero />

    <WhatIsIt />

    <LegacyJourney />

    <NfcMoment />

    <StoreStory
        :products="featuredProducts"
        :legacy-plate-slug="legacyPlateSlug"
    />

    <section class="fl-section" aria-label="Eventos">
        <div class="fl-container">
            <SectionHeading eyebrow="Eventos" title="Tu próxima salida.">
                <template #action>
                    <Link
                        :href="eventsIndex()"
                        class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                    >
                        Explorar eventos <ArrowRight class="size-4" />
                    </Link>
                </template>
            </SectionHeading>

            <Reveal
                v-if="featuredEditions.length"
                class="mt-10 grid gap-5 lg:grid-cols-3"
            >
                <!-- First edition reads larger; the rest are compact. -->
                <div
                    :class="
                        featuredEditions.length > 1
                            ? 'lg:col-span-2'
                            : 'lg:col-span-3'
                    "
                >
                    <EventCard :edition="featuredEditions[0]" />
                </div>
                <div
                    v-if="featuredEditions.length > 1"
                    class="grid gap-5 sm:grid-cols-2 lg:grid-cols-1"
                >
                    <EventCard
                        v-for="edition in featuredEditions.slice(1)"
                        :key="edition.id"
                        :edition="edition"
                        compact
                    />
                </div>
            </Reveal>
            <p
                v-else
                class="mt-10 rounded-media border border-dashed border-foreground/15 bg-card p-8 text-muted-foreground"
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
