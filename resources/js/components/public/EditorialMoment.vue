<script setup lang="ts">
/**
 * "NO ES SOLO UNA MEDALLA." — the editorial beat between the hero and the
 * product. Lines reveal one at a time as the section enters the viewport;
 * the closing line lands as the section's thesis (brand system §12).
 */
import Reveal from '@/components/motion/Reveal.vue';
import StoryMediaSequence from '@/components/public/media/StoryMediaSequence.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';

const lines = [
    'Es el entrenamiento que nadie vio.',
    'El despertador antes del amanecer.',
    'Los kilómetros difíciles.',
    'La persona que te esperó en la meta.',
    'El tiempo que buscabas.',
    'La carrera que pensaste que no terminarías.',
];
</script>

<template>
    <section
        class="relative overflow-hidden border-t border-border bg-card/40 py-24 sm:py-32"
    >
        <div
            class="pointer-events-none absolute inset-0 opacity-40"
            style="
                background: radial-gradient(
                    ellipse 50% 40% at 50% 0%,
                    color-mix(in srgb, var(--fl-gold) 10%, transparent),
                    transparent 70%
                );
            "
        />

        <div
            class="relative mx-auto grid max-w-6xl gap-12 px-4 sm:px-6 lg:grid-cols-2 lg:items-center lg:gap-16 lg:px-8"
        >
            <Reveal class="order-1 lg:order-2">
                <StoryMediaSequence />
            </Reveal>

            <div class="order-2 lg:order-1">
                <Reveal>
                    <SectionHeading
                        align="left"
                        eyebrow="El significado"
                        title="NO ES SOLO UNA MEDALLA."
                    />
                </Reveal>

                <div class="mt-12 space-y-2">
                    <Reveal
                        v-for="(line, index) in lines"
                        :key="line"
                        as="p"
                        :delay-ms="index * 90"
                        class="text-xl leading-snug text-muted-foreground sm:text-2xl"
                    >
                        {{ line }}
                    </Reveal>
                </div>

                <Reveal
                    :delay-ms="lines.length * 90 + 100"
                    class="mt-12 border-t border-border pt-10"
                >
                    <p
                        class="text-2xl font-semibold tracking-tight text-foreground sm:text-3xl"
                    >
                        Finisher Legacy preserva lo que hay detrás del metal.
                    </p>
                </Reveal>
            </div>
        </div>
    </section>
</template>
