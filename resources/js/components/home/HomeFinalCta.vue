<script setup lang="ts">
/**
 * Final CTA — a warm cream band (no dark section), the page's single gold
 * commercial CTA and the activation link for people who already have one.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import Reveal from '@/components/motion/Reveal.vue';
import { ACTIVATE_URL } from '@/config/public-links';
import { index as storeIndex } from '@/routes/store/products';
</script>

<template>
    <section class="bg-white pb-20 sm:pb-24" aria-labelledby="final-cta-title">
        <div class="fl-container">
            <Reveal
                class="relative overflow-hidden rounded-hero bg-fl-cream px-6 py-16 text-center sm:px-12 sm:py-20"
            >
                <div
                    class="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_80%_at_50%_0%,rgb(255_255_255/0.7),transparent_70%)]"
                    aria-hidden="true"
                />
                <div class="relative">
                    <p class="fl-eyebrow">Tu carrera termina</p>
                    <h2
                        id="final-cta-title"
                        class="fl-display mx-auto mt-4 max-w-3xl text-[clamp(2.1rem,4.8vw,3.75rem)]"
                    >
                        Tu historia no tiene por qué terminar.
                    </h2>
                    <p
                        class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-muted-foreground"
                    >
                        Llévala contigo en un objeto que puedes tocar, y ábrela
                        cada vez que quieras volver a esa meta.
                    </p>
                    <div
                        class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row"
                    >
                        <Link
                            :href="storeIndex()"
                            class="inline-flex h-12 w-full items-center justify-center gap-2 rounded-full bg-fl-gold px-8 text-sm font-semibold text-fl-black shadow-fl-float transition-[background-color,transform] hover:-translate-y-0.5 hover:bg-fl-gold-soft focus-visible:ring-2 focus-visible:ring-fl-black focus-visible:ring-offset-2 focus-visible:outline-none sm:w-auto"
                        >
                            Comprar mi tag
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                        <a
                            :href="ACTIVATE_URL"
                            class="inline-flex min-h-11 items-center gap-2 px-4 text-sm font-semibold text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] hover:decoration-2"
                        >
                            Ya tengo uno: activarlo
                        </a>
                    </div>
                </div>
            </Reveal>
        </div>
    </section>
</template>
