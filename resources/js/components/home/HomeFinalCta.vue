<script setup lang="ts">
/**
 * Final CTA — a warm cream band that closes the page: the brand signature
 * (official image, never typed), one clear commercial action and the
 * activation link for people who already have a tag. On the right, the
 * real tags of the published collections fan out like a hand of cards.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import type { TagArt } from '@/components/three/tagStageContext';
import { vReveal } from '@/composables/useReveal';
import { ACTIVATE_URL } from '@/config/public-links';
import { index as storeIndex } from '@/routes/store/products';
import { vStage } from './homeMotion';

const props = defineProps<{ arts: TagArt[] }>();

/** Up to three tags; a single design is simply shown alone. */
const fan = computed(() => props.arts.slice(0, 3));
</script>

<template>
    <section class="bg-white pb-16 sm:pb-24" aria-labelledby="final-cta-title">
        <div class="fl-container">
            <div
                v-reveal
                class="relative overflow-hidden rounded-hero bg-fl-cream px-6 py-14 sm:px-12 sm:py-20 lg:px-16"
            >
                <div
                    class="pointer-events-none absolute inset-0 bg-[radial-gradient(55%_80%_at_78%_45%,rgb(255_255_255/0.85),transparent_70%)]"
                    aria-hidden="true"
                />
                <div
                    class="home-grain pointer-events-none absolute inset-0 opacity-40"
                    aria-hidden="true"
                />
                <div class="relative grid items-center gap-10 lg:grid-cols-12">
                    <div class="text-center lg:col-span-7 lg:text-left">
                        <img
                            src="/images/brand/logo/fl-firma-horizontal-claro.png"
                            alt="Finisher Legacy — Make it last. Haz que perdure."
                            width="1400"
                            height="326"
                            loading="lazy"
                            decoding="async"
                            class="mx-auto h-auto w-[min(100%,15rem)] lg:mx-0"
                        />
                        <h2
                            id="final-cta-title"
                            class="fl-display mx-auto mt-8 max-w-3xl text-[clamp(2.1rem,5vw,4rem)] text-balance lg:mx-0"
                        >
                            Tu carrera termina. Tu historia no tiene por qué.
                        </h2>
                        <p
                            class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-muted-foreground lg:mx-0"
                        >
                            Llévala contigo en un objeto que puedes tocar,
                            compártela con tu gente y ábrela cada vez que
                            quieras volver a esa meta.
                        </p>
                        <div
                            class="mt-9 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start"
                        >
                            <Link
                                :href="storeIndex()"
                                class="fl-btn-primary group w-full sm:w-auto"
                            >
                                Elegir mi tag
                                <ArrowRight
                                    class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none"
                                    aria-hidden="true"
                                />
                            </Link>
                            <a
                                :href="ACTIVATE_URL"
                                class="fl-btn-secondary w-full sm:w-auto"
                            >
                                Ya tengo uno: activarlo
                            </a>
                        </div>
                    </div>

                    <div
                        v-stage
                        class="cta-fan relative order-first mx-auto h-64 w-full max-w-[20rem] sm:h-72 lg:order-none lg:col-span-5 lg:h-[24rem] lg:max-w-[24rem]"
                        aria-hidden="true"
                    >
                        <span
                            v-for="(art, i) in fan"
                            :key="`${art.name}-${i}`"
                            class="cta-fan__tag"
                            :class="`cta-fan__tag--${i}`"
                        >
                            <span
                                class="home-float block"
                                :style="{ animationDelay: `${i * -2.1}s` }"
                            >
                                <TagVisualizer
                                    v-bind="art"
                                    :tilt="false"
                                    label=""
                                />
                            </span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.cta-fan__tag {
    position: absolute;
    top: 4%;
    left: 50%;
    width: 34%;
    transform-origin: 50% 120%;
    filter: drop-shadow(0 22px 22px rgb(60 44 20 / 0.28));
    transition: transform 900ms var(--legacy-ease);
}
.cta-fan__tag--0 {
    z-index: 3;
    transform: translateX(-50%);
}
.cta-fan__tag--1 {
    z-index: 2;
    transform: translateX(-50%) rotate(-15deg) translateX(-30%);
}
.cta-fan__tag--2 {
    z-index: 1;
    transform: translateX(-50%) rotate(15deg) translateX(30%);
}
.cta-fan:hover .cta-fan__tag--1 {
    transform: translateX(-50%) rotate(-20deg) translateX(-36%);
}
.cta-fan:hover .cta-fan__tag--2 {
    transform: translateX(-50%) rotate(20deg) translateX(36%);
}
@media (prefers-reduced-motion: reduce) {
    .cta-fan__tag {
        transition: none;
    }
}
</style>
