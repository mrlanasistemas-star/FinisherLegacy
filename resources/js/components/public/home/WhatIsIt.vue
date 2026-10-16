<script setup lang="ts">
/**
 * "Qué es" (owner spec §40) — one sentence, then the four things it
 * connects. Understandable in five seconds.
 */
import { Camera, Flag, Nfc, Users } from '@lucide/vue';
import Reveal from '@/components/motion/Reveal.vue';

const pillars = [
    {
        icon: Flag,
        title: 'Tus carreras',
        body: 'Eventos, resultados y tiempos oficiales.',
    },
    {
        icon: Camera,
        title: 'Tus recuerdos',
        body: 'Fotos, medallas y los momentos que valen la pena.',
    },
    {
        icon: Nfc,
        title: 'Tus productos',
        body: 'Legacy Plate y equipo que se conecta con NFC.',
    },
    {
        icon: Users,
        title: 'Tu comunidad',
        body: 'Atletas, amigos y familia que corren contigo.',
    },
];
</script>

<template>
    <section class="border-y border-border bg-card" aria-labelledby="que-es">
        <div class="fl-container py-20 sm:py-28">
            <div class="grid gap-10 lg:grid-cols-12">
                <p class="fl-eyebrow lg:col-span-3 lg:pt-4">Qué es</p>
                <Reveal class="lg:col-span-9">
                    <h2
                        id="que-es"
                        class="fl-display text-[2.1rem] leading-[1.12] sm:text-5xl lg:text-[3.6rem]"
                    >
                        Finisher Legacy conecta
                        <em class="text-fl-gold-ink">tus carreras</em>, tus
                        recuerdos, tus productos y tu comunidad
                        <span class="text-fl-stone">en un solo lugar.</span>
                    </h2>
                </Reveal>
            </div>

            <ul
                class="mt-16 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-border bg-border lg:mt-20 lg:grid-cols-4"
            >
                <li
                    v-for="(pillar, index) in pillars"
                    :key="pillar.title"
                    class="bg-card p-5 sm:p-7"
                >
                    <Reveal :delay-ms="index * 70">
                        <span
                            class="flex size-11 items-center justify-center rounded-full border border-border bg-background text-fl-gold-ink"
                            aria-hidden="true"
                        >
                            <component :is="pillar.icon" class="size-5" />
                        </span>
                        <h3
                            class="mt-6 text-base font-semibold text-foreground"
                        >
                            {{ pillar.title }}
                        </h3>
                        <p
                            class="mt-1.5 text-sm leading-relaxed text-muted-foreground"
                        >
                            {{ pillar.body }}
                        </p>
                    </Reveal>
                </li>
            </ul>
        </div>
    </section>
</template>
