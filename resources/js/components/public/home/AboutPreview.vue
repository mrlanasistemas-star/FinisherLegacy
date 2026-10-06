<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { about } from '@/routes';
</script>

<template>
    <section class="border-t border-border bg-card">
        <div
            class="fl-container grid items-center gap-10 py-16 sm:py-24 lg:grid-cols-2 lg:gap-16"
        >
            <div
                class="relative aspect-[3/2] overflow-hidden rounded-2xl bg-fl-black"
            >
                <img
                    src="/media/home/final/legacy-final-poster-640.webp"
                    srcset="
                        /media/home/final/legacy-final-poster-640.webp   640w,
                        /media/home/final/legacy-final-poster-1000.webp 1000w
                    "
                    sizes="(min-width: 1024px) 45vw, 100vw"
                    width="1000"
                    height="667"
                    alt="Atleta celebrando al terminar una competencia"
                    loading="lazy"
                    decoding="async"
                    class="size-full object-cover"
                />
            </div>

            <div>
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-6 bg-fl-gold" aria-hidden="true" />
                    Nosotros
                </p>
                <h2 class="fl-display mt-4 text-[2rem] sm:text-5xl">
                    Conocemos la meta porque también hemos estado ahí.
                </h2>
                <p
                    class="mt-5 max-w-lg text-base leading-relaxed text-muted-foreground sm:text-lg"
                >
                    Finisher Legacy nace desde el deporte para preservar lo que
                    cada atleta construye: el esfuerzo, los recuerdos, la
                    comunidad y la identidad deportiva — en digital y en
                    productos físicos.
                </p>
                <Button
                    as-child
                    variant="outline"
                    class="mt-8 h-11 rounded-full border-foreground/20 px-6"
                >
                    <Link :href="about()">
                        Conoce Finisher Legacy
                        <ArrowRight class="size-4" />
                    </Link>
                </Button>
            </div>
        </div>
    </section>
</template>
