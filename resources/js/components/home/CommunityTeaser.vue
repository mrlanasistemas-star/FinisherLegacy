<script setup lang="ts">
/**
 * Community teaser — one light band that points to /comunidad. No
 * numbers or member counts: only what the community actually offers.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Camera, Heart, Users } from '@lucide/vue';
import Reveal from '@/components/motion/Reveal.vue';
import { MEDIA } from '@/config/media';
import { index as communityIndex } from '@/routes/community';

const photo = MEDIA.photo.triumph;

const points = [
    { icon: Camera, label: 'Resultados, fotos y entrenamientos' },
    { icon: Heart, label: 'Celebra y comenta los logros de otros' },
    { icon: Users, label: 'Sigue a quienes corrieron contigo' },
];
</script>

<template>
    <section
        class="bg-white py-20 sm:py-24 lg:py-28"
        aria-labelledby="community-teaser-title"
    >
        <div class="fl-container">
            <Reveal
                class="grid items-center gap-10 overflow-hidden rounded-hero border border-fl-line bg-fl-paper lg:grid-cols-2"
            >
                <div class="p-8 sm:p-12">
                    <p class="fl-eyebrow">Comunidad</p>
                    <h2
                        id="community-teaser-title"
                        class="fl-display mt-4 text-[clamp(1.9rem,3.6vw,2.75rem)]"
                    >
                        Tu historia no se corre sola.
                    </h2>
                    <p
                        class="mt-4 max-w-md text-lg leading-relaxed text-muted-foreground"
                    >
                        Comparte lo que vives en cada carrera con otros atletas
                        de Finisher Legacy.
                    </p>
                    <ul class="mt-7 space-y-3">
                        <li
                            v-for="point in points"
                            :key="point.label"
                            class="flex items-center gap-3 text-[15px] text-foreground"
                        >
                            <span
                                class="grid size-8 place-items-center rounded-full bg-white text-fl-gold-ink shadow-fl-micro"
                                aria-hidden="true"
                            >
                                <component :is="point.icon" class="size-4" />
                            </span>
                            {{ point.label }}
                        </li>
                    </ul>
                    <Link
                        :href="communityIndex()"
                        class="mt-8 inline-flex h-11 items-center gap-2 rounded-full border border-foreground/15 bg-white px-6 text-sm font-semibold text-foreground transition-colors hover:border-foreground/40"
                    >
                        Ir a la comunidad
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>
                <div class="relative h-64 sm:h-80 lg:h-full lg:min-h-[26rem]">
                    <img
                        :src="photo.src"
                        :srcset="photo.srcset"
                        sizes="(min-width: 1024px) 50vw, 100vw"
                        :alt="photo.alt"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 size-full object-cover"
                    />
                </div>
            </Reveal>
        </div>
    </section>
</template>
