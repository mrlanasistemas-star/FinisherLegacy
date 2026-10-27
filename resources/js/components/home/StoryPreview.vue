<script setup lang="ts">
/**
 * Bloque D — "Guarda lo que ocurrió detrás del resultado." What a Legacy
 * holds, shown as one EXAMPLE story (clearly labelled; "Tu nombre", never
 * a real athlete). The waveform and certificate are drawings, not
 * controls — nothing here pretends to play or download.
 */
import { Award, Play } from '@lucide/vue';
import Reveal from '@/components/motion/Reveal.vue';
import { MEDIA } from '@/config/media';

const photo = MEDIA.photo.dawn;

const holds = [
    'Tu foto',
    'La carrera',
    'Tu tiempo',
    'La fecha',
    'El lugar',
    'Una dedicatoria',
    'Un audio',
    'Tu certificado',
];

/** Deterministic bar heights for the illustrative waveform. */
const bars = Array.from({ length: 42 }, (_, i) =>
    Math.round(22 + 70 * Math.abs(Math.sin(i * 0.55) * Math.cos(i * 0.17))),
);
</script>

<template>
    <section
        class="relative overflow-hidden bg-fl-cream py-20 sm:py-28 lg:py-36"
        aria-labelledby="story-preview-title"
    >
        <div
            class="fl-container grid grid-cols-1 items-center gap-14 lg:grid-cols-12 lg:gap-12"
        >
            <div class="lg:col-span-5">
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                    Tu Legacy
                </p>
                <h2
                    id="story-preview-title"
                    class="fl-display mt-5 text-[clamp(2.1rem,4.4vw,3.75rem)]"
                >
                    Guarda lo que ocurrió detrás del resultado.
                </h2>
                <p
                    class="mt-6 max-w-[30rem] text-lg leading-relaxed text-muted-foreground"
                >
                    El tiempo dice cuánto tardaste. Tu historia dice todo lo
                    demás: quién te esperó, qué escuchabas, por qué no te
                    detuviste.
                </p>
                <ul
                    class="mt-9 grid max-w-md grid-cols-2 gap-x-6 gap-y-3 border-t border-foreground/10 pt-7"
                >
                    <li
                        v-for="item in holds"
                        :key="item"
                        class="flex items-center gap-3 text-[15px] text-foreground"
                    >
                        <span class="h-px w-4 bg-fl-gold" aria-hidden="true" />
                        {{ item }}
                    </li>
                </ul>
            </div>

            <div class="relative lg:col-span-7">
                <Reveal
                    class="relative mx-auto max-w-xl overflow-hidden rounded-hero bg-card shadow-fl-overlay"
                >
                    <!-- Photo -->
                    <div
                        class="relative aspect-[16/10] overflow-hidden bg-fl-black"
                    >
                        <img
                            :src="photo.src"
                            :srcset="photo.srcset"
                            sizes="(min-width: 1024px) 36rem, 92vw"
                            :width="photo.width"
                            :height="photo.height"
                            alt="Ejemplo de foto de una historia: corredora entrenando junto al mar al amanecer"
                            loading="lazy"
                            decoding="async"
                            class="size-full object-cover object-[50%_45%]"
                        />
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-fl-black/80 via-transparent to-transparent"
                        />
                        <span
                            class="absolute top-4 left-4 rounded-full bg-fl-black/70 px-2.5 py-1 text-[10px] font-semibold tracking-[0.18em] text-fl-gold-soft uppercase"
                        >
                            Ejemplo
                        </span>
                        <div
                            class="absolute inset-x-5 bottom-4 text-white sm:inset-x-7 sm:bottom-6"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.2em] text-fl-gold-soft uppercase"
                            >
                                Mi Legacy
                            </p>
                            <p
                                class="mt-1 font-serif text-3xl leading-tight sm:text-4xl"
                            >
                                Tu nombre
                            </p>
                        </div>
                    </div>

                    <!-- Facts -->
                    <dl
                        class="grid grid-cols-2 gap-px bg-border sm:grid-cols-4"
                    >
                        <div
                            v-for="fact in [
                                { k: 'Carrera', v: 'Tu carrera' },
                                { k: 'Tiempo', v: '—:—:—' },
                                { k: 'Fecha', v: 'El día' },
                                { k: 'Lugar', v: 'Tu ciudad' },
                            ]"
                            :key="fact.k"
                            class="bg-card px-5 py-4"
                        >
                            <dt
                                class="text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                            >
                                {{ fact.k }}
                            </dt>
                            <dd
                                class="legacy-numeric mt-1 text-[15px] font-medium text-foreground"
                            >
                                {{ fact.v }}
                            </dd>
                        </div>
                    </dl>

                    <div class="grid gap-6 p-5 sm:p-7">
                        <!-- Dedication -->
                        <blockquote
                            class="border-l border-fl-gold pl-4 font-serif text-xl leading-snug text-foreground italic"
                        >
                            “Para quien me esperó en el último kilómetro.”
                        </blockquote>

                        <!-- Audio (drawing) -->
                        <div
                            class="flex items-center gap-4 rounded-card bg-fl-paper px-4 py-3"
                            aria-label="Ejemplo de nota de voz"
                            role="img"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-foreground text-background"
                                aria-hidden="true"
                            >
                                <Play class="size-4 translate-x-px" />
                            </span>
                            <span
                                class="flex h-9 min-w-0 flex-1 items-center gap-[2px] sm:gap-[3px]"
                                aria-hidden="true"
                            >
                                <span
                                    v-for="(h, i) in bars"
                                    :key="i"
                                    class="max-w-[3px] min-w-px flex-1 rounded-full"
                                    :class="
                                        i < 15
                                            ? 'bg-fl-gold'
                                            : 'bg-foreground/20'
                                    "
                                    :style="{ height: `${h}%` }"
                                />
                            </span>
                            <span
                                class="legacy-numeric text-xs text-muted-foreground"
                                aria-hidden="true"
                                >0:42</span
                            >
                        </div>

                        <!-- Certificate (drawing) -->
                        <div
                            class="flex items-center gap-4 rounded-card border border-border px-4 py-3"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full border border-fl-gold/60 text-fl-gold-ink"
                                aria-hidden="true"
                            >
                                <Award class="size-4" />
                            </span>
                            <span class="min-w-0">
                                <span
                                    class="block text-sm font-semibold text-foreground"
                                    >Certificado de tu historia</span
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >Digital, para guardar o compartir</span
                                >
                            </span>
                        </div>
                    </div>
                </Reveal>
            </div>
        </div>
    </section>
</template>
