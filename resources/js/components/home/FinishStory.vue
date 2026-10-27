<script setup lang="ts">
/**
 * Bloque A — "Tu carrera termina. Tu historia no." Real finish-line
 * photography in an asymmetric editorial composition: a tall portrait
 * that bleeds, a second frame offset against it, and the line set big.
 */
import Reveal from '@/components/motion/Reveal.vue';
import { MEDIA } from '@/config/media';

const portrait = MEDIA.photo.celebration;
const effort = MEDIA.photo.runner;
</script>

<template>
    <section
        class="relative overflow-hidden bg-fl-black py-20 text-white sm:py-28 lg:py-36"
        aria-labelledby="finish-story-title"
    >
        <div
            class="fl-container grid items-center gap-12 lg:grid-cols-12 lg:gap-10"
        >
            <div class="relative lg:col-span-6 lg:col-start-1">
                <Reveal
                    class="relative mx-auto aspect-[2/3] w-[78%] overflow-hidden rounded-media sm:w-[62%] lg:mx-0 lg:w-[78%]"
                >
                    <img
                        :src="portrait.src"
                        :srcset="portrait.srcset"
                        sizes="(min-width: 1024px) 34vw, 70vw"
                        :width="portrait.width"
                        :height="portrait.height"
                        :alt="portrait.alt"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover"
                    />
                </Reveal>
                <Reveal
                    :delay-ms="180"
                    class="absolute right-0 -bottom-10 aspect-[3/2] w-[52%] overflow-hidden rounded-media ring-8 ring-fl-black sm:right-[6%] lg:right-0 lg:-bottom-14 lg:w-[54%]"
                >
                    <img
                        :src="effort.src"
                        :srcset="effort.srcset"
                        sizes="(min-width: 1024px) 24vw, 50vw"
                        :width="1000"
                        :height="667"
                        :alt="effort.alt"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover object-[30%_30%]"
                    />
                </Reveal>
            </div>

            <div class="mt-10 lg:col-span-5 lg:col-start-8 lg:mt-0">
                <p
                    class="flex items-center gap-3 text-[11px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
                >
                    <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                    Después de la meta
                </p>
                <h2
                    id="finish-story-title"
                    class="mt-6 font-serif text-[clamp(2.25rem,5vw,4.25rem)] leading-[1.04] font-medium tracking-[-0.02em]"
                >
                    Tu carrera termina.
                    <span class="block text-white/45 italic"
                        >Tu historia no.</span
                    >
                </h2>
                <p
                    class="mt-7 max-w-[30rem] text-lg leading-relaxed text-white/70"
                >
                    Los kilómetros se acaban. El cansancio se va. Lo que
                    sentiste al cruzar la meta merece un lugar al que puedas
                    volver.
                </p>
                <p
                    class="mt-10 max-w-[26rem] border-l border-fl-gold/60 pl-5 font-serif text-xl leading-snug text-white/85 italic"
                >
                    Lo que viviste no cabe en un resultado.
                </p>
            </div>
        </div>
    </section>
</template>
