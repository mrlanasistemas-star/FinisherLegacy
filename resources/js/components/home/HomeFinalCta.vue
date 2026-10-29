<script setup lang="ts">
/**
 * Final CTA — a warm cream band (no dark section), the page's single gold
 * commercial CTA and the activation link for people who already have one.
 * The travelling tag settles here (its last stop, also on phones).
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import Reveal from '@/components/motion/Reveal.vue';
import TagAnchor from '@/components/three/TagAnchor.vue';
import { ACTIVATE_URL } from '@/config/public-links';
import { index as storeIndex } from '@/routes/store/products';

const pose = { face: 'front' as const, turn: -0.32, tilt: 0.05, roll: -0.05 };
</script>

<template>
    <section class="bg-white py-16 sm:py-24" aria-labelledby="final-cta-title">
        <div class="fl-container">
            <Reveal
                class="relative overflow-hidden rounded-hero bg-fl-cream px-6 py-12 sm:px-12 sm:py-16 lg:px-16"
            >
                <div
                    class="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_80%_at_75%_40%,rgb(255_255_255/0.75),transparent_70%)]"
                    aria-hidden="true"
                />
                <div
                    class="home-grain pointer-events-none absolute inset-0 opacity-40"
                    aria-hidden="true"
                />
                <div
                    class="relative grid items-center gap-8 lg:grid-cols-12 lg:gap-10"
                >
                    <div class="text-center lg:col-span-7 lg:text-left">
                        <p class="fl-eyebrow">Tu carrera termina</p>
                        <h2
                            id="final-cta-title"
                            class="fl-display mx-auto mt-4 max-w-3xl text-[clamp(2.2rem,5vw,4rem)] lg:mx-0"
                        >
                            Tu historia no tiene por qué terminar.
                        </h2>
                        <p
                            class="mx-auto mt-5 max-w-xl text-lg leading-relaxed text-muted-foreground lg:mx-0"
                        >
                            Llévala contigo en un objeto que puedes tocar, y
                            ábrela cada vez que quieras volver a esa meta.
                        </p>
                        <div
                            class="mt-9 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start"
                        >
                            <Link
                                :href="storeIndex()"
                                class="home-shine group inline-flex h-13 w-full items-center justify-center gap-2 rounded-full bg-fl-gold px-8 text-[15px] font-semibold text-fl-black shadow-fl-float transition-[background-color,transform] hover:-translate-y-0.5 hover:bg-fl-gold-soft focus-visible:ring-2 focus-visible:ring-fl-black focus-visible:ring-offset-2 focus-visible:outline-none active:translate-y-0 sm:w-auto"
                            >
                                Comprar mi tag
                                <ArrowRight
                                    class="size-4 transition-transform group-hover:translate-x-1"
                                    aria-hidden="true"
                                />
                            </Link>
                            <a
                                :href="ACTIVATE_URL"
                                class="inline-flex min-h-11 items-center gap-2 px-4 text-sm font-semibold text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] hover:decoration-2"
                            >
                                Ya tengo uno: activarlo
                            </a>
                        </div>
                    </div>
                    <div class="order-first lg:order-none lg:col-span-5">
                        <TagAnchor
                            class="relative mx-auto h-60 w-full max-w-[12rem] sm:h-72 lg:h-[26rem] lg:max-w-[20rem]"
                            :pose="pose"
                            mobile
                        />
                    </div>
                </div>
            </Reveal>
        </div>
    </section>
</template>
