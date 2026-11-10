<script setup lang="ts">
/**
 * Sports strip — a slow marquee of the disciplines Finisher Legacy is made
 * for. Decorative motion only: the list is read once by assistive tech and
 * the duplicate track is aria-hidden. Static under reduced motion.
 */
import { vStage } from './homeMotion';

const sports = [
    'Running',
    'Triatlón',
    'Ciclismo',
    'HYROX',
    'Trail',
    'Natación',
    'Duatlón',
];
</script>

<template>
    <section
        v-stage
        class="border-y border-fl-line bg-white py-6 sm:py-8"
        aria-label="Deportes para los que está hecho Finisher Legacy"
    >
        <p class="sr-only">
            Para running, triatlón, ciclismo, HYROX, trail, natación, duatlón y
            cualquier meta que cruces.
        </p>
        <div class="home-marquee" aria-hidden="true">
            <div v-for="copy in 2" :key="copy" class="home-marquee__track">
                <template v-for="(sport, i) in sports" :key="sport">
                    <span
                        class="font-serif text-[clamp(1.5rem,3.4vw,2.6rem)] leading-none font-semibold tracking-[-0.02em] whitespace-nowrap uppercase"
                        :class="
                            i % 2 === 0
                                ? 'text-fl-black'
                                : 'home-outline text-transparent'
                        "
                        >{{ sport }}</span
                    >
                    <img
                        src="/images/brand/logo/fl-monograma.png"
                        alt=""
                        width="56"
                        height="28"
                        loading="lazy"
                        decoding="async"
                        class="h-4 w-auto shrink-0 opacity-70 sm:h-5"
                    />
                </template>
            </div>
        </div>
    </section>
</template>

<style scoped>
.home-marquee {
    display: flex;
    overflow: hidden;
    mask-image: linear-gradient(
        90deg,
        transparent,
        #000 8%,
        #000 92%,
        transparent
    );
}
.home-marquee__track {
    display: flex;
    flex-shrink: 0;
    align-items: center;
    gap: clamp(1.5rem, 3.5vw, 3rem);
    padding-right: clamp(1.5rem, 3.5vw, 3rem);
    animation: home-marquee 38s linear infinite;
}
.home-outline {
    -webkit-text-stroke: 1px var(--fl-gold-dim);
}
@keyframes home-marquee {
    to {
        transform: translate3d(-100%, 0, 0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .home-marquee__track {
        animation: none;
    }
}
</style>
