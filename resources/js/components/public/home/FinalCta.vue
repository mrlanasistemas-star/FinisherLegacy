<script setup lang="ts">
/**
 * Closing call to action — the single dark accent of the page, and it
 * lives inside a photograph with a scrim (brand rule: dark only inside
 * photos or one accent block).
 */
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed, useTemplateRef } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import { Button } from '@/components/ui/button';
import { useParallax } from '@/composables/useParallax';
import { MEDIA } from '@/config/media';
import { dashboard, register } from '@/routes';
import { index as eventsIndex } from '@/routes/events';

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
const photo = MEDIA.photo.triumph;

// Slow scroll parallax on the closing photograph (writes `--sp` on the block).
const blockEl = useTemplateRef<HTMLElement>('block');
useParallax(blockEl);
</script>

<template>
    <section class="fl-container fl-section">
        <div
            ref="block"
            class="relative isolate overflow-hidden rounded-hero bg-fl-black px-6 py-20 text-center shadow-fl-overlay sm:px-12 sm:py-28"
        >
            <img
                :src="photo.src"
                :srcset="photo.srcset"
                sizes="(min-width: 1280px) 1200px, 100vw"
                alt=""
                loading="lazy"
                decoding="async"
                class="fl-cta-img absolute inset-0 -z-10 size-full object-cover opacity-70"
            />
            <div
                class="absolute inset-0 -z-10 bg-[radial-gradient(60%_80%_at_50%_100%,rgb(0_0_0/0.85),rgb(0_0_0/0.35))]"
            />
            <Reveal>
                <p
                    class="text-[11px] font-semibold tracking-[0.24em] text-fl-gold-soft uppercase"
                >
                    Tu historia. Tu legado.
                </p>
                <h2
                    class="mx-auto mt-5 max-w-3xl font-serif text-[clamp(2.4rem,6vw,3.75rem)] leading-[1.02] font-medium tracking-[-0.02em] text-white"
                >
                    La meta se cruza una vez. Tu legado se queda para siempre.
                </h2>
                <p
                    class="mx-auto mt-5 max-w-xl text-base text-white/70 sm:text-lg"
                >
                    Elige tu próxima carrera, termínala y deja que cada tiempo,
                    cada foto y cada medalla vivan en un solo lugar.
                </p>
            </Reveal>
            <div class="mt-10 flex flex-wrap justify-center gap-3">
                <Button
                    as-child
                    size="lg"
                    class="h-12 rounded-full bg-white px-7 text-fl-black hover:bg-fl-paper"
                >
                    <Link :href="isGuest ? register() : dashboard()">
                        {{ isGuest ? 'Crear cuenta' : 'Ir a Mi legado' }}
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
                <Button
                    as-child
                    size="lg"
                    variant="outline"
                    class="h-12 rounded-full border-white/40 bg-transparent px-7 text-white hover:bg-white/10 hover:text-white"
                >
                    <Link :href="eventsIndex()">Explorar eventos</Link>
                </Button>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* Slow scroll parallax on the closing photograph. The image is oversized so
   the vertical drift never reveals the block edge; reduced motion keeps it
   still. */
.fl-cta-img {
    transform: none;
    will-change: transform;
}

@media (prefers-reduced-motion: no-preference) {
    .fl-cta-img {
        transform: translateY(calc(var(--sp, 0) * 26px)) scale(1.12);
    }
}
</style>
