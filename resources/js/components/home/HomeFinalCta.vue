<script setup lang="ts">
/**
 * Bloque H — the emotional close over the real finish photograph. The
 * page's one gold CTA (commercial) + the activation path for people who
 * already have their tag.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { MEDIA } from '@/config/media';
import { ACTIVATE_URL } from '@/config/public-links';
import { index as collectionsIndex } from '@/routes/collections';

const photo = MEDIA.photo.triumph;
</script>

<template>
    <section
        class="relative isolate overflow-hidden bg-fl-black text-white"
        aria-labelledby="final-cta-title"
    >
        <img
            :src="photo.src"
            :srcset="photo.srcset"
            sizes="100vw"
            :width="photo.width"
            :height="photo.height"
            :alt="photo.alt"
            loading="lazy"
            decoding="async"
            class="absolute inset-0 -z-10 size-full object-cover object-[50%_30%] opacity-70"
        />
        <div
            class="absolute inset-0 -z-10 bg-gradient-to-t from-fl-black via-fl-black/60 to-fl-black/20"
            aria-hidden="true"
        />

        <div
            class="fl-container flex min-h-[80svh] flex-col items-center justify-end py-20 text-center sm:min-h-[88svh] sm:py-28"
        >
            <span
                class="h-12 w-px bg-gradient-to-b from-transparent to-fl-gold"
                aria-hidden="true"
            />
            <h2
                id="final-cta-title"
                class="mt-8 max-w-4xl font-serif text-[clamp(2.2rem,4.6vw,4rem)] leading-[1.02] font-medium tracking-[-0.025em]"
            >
                Cada logro merece algo más
                <span class="block text-fl-gold-soft italic"
                    >que una foto en el carrete.</span
                >
            </h2>
            <p class="mt-6 max-w-xl text-lg text-white/70">
                Elige la colección de tu carrera y llévate el recuerdo que se
                puede tocar.
            </p>
            <div
                class="mt-10 flex w-full flex-col items-center justify-center gap-3 sm:w-auto sm:flex-row"
            >
                <Link
                    :href="collectionsIndex()"
                    class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-fl-gold px-6 text-sm font-semibold tracking-wide text-fl-black transition-colors hover:bg-fl-gold-soft focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-fl-black focus-visible:outline-none"
                >
                    Explorar colecciones
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
                <a
                    :href="ACTIVATE_URL"
                    class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-white underline decoration-fl-gold decoration-1 underline-offset-[6px] transition-colors hover:decoration-2"
                >
                    Ya tengo mi tag: activarlo
                </a>
            </div>
        </div>
    </section>
</template>
