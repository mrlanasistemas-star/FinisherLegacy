<script setup lang="ts">
/**
 * "Así se ve tu historia" — a phone mockup of the story page that opens
 * when someone taps the tag (/t/{code}). It is ONE EXAMPLE, clearly
 * labelled ("Ejemplo", "Tu nombre"); the photo is project imagery, the
 * figures are illustrative and nothing in the mockup is a control.
 */
import { Heart, MapPin, Play, Share2 } from '@lucide/vue';
import Reveal from '@/components/motion/Reveal.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { MEDIA } from '@/config/media';

defineProps<{
    tag: {
        name: string;
        subtitle: string | null;
        year: number | null;
        accent: string | null;
    };
}>();

const photo = MEDIA.photo.dawn;

const holds = [
    {
        title: 'Tus fotos y tu tiempo',
        body: 'La carrera, la fecha, el lugar y tu resultado.',
    },
    {
        title: 'Dedicatorias y audio',
        body: 'Las palabras de quienes te esperaron en la meta.',
    },
    {
        title: 'Tú decides quién la ve',
        body: 'Pública, solo con enlace o privada.',
    },
];

/** Deterministic bar heights for the illustrative waveform. */
const bars = Array.from({ length: 28 }, (_, i) =>
    Math.round(25 + 70 * Math.abs(Math.sin(i * 0.55) * Math.cos(i * 0.17))),
);
</script>

<template>
    <section
        class="overflow-hidden bg-fl-cream/60 py-20 sm:py-24 lg:py-28"
        aria-labelledby="story-preview-title"
    >
        <div
            class="fl-container grid items-center gap-14 lg:grid-cols-12 lg:gap-12"
        >
            <Reveal class="lg:col-span-5">
                <p class="fl-eyebrow">Tu historia</p>
                <h2
                    id="story-preview-title"
                    class="fl-display mt-4 text-[clamp(2rem,4.2vw,3.25rem)]"
                >
                    Acércalo al celular y esto es lo que se abre.
                </h2>
                <p
                    class="mt-5 max-w-[30rem] text-lg leading-relaxed text-muted-foreground"
                >
                    Tu página personal, sin instalar nada: se abre en el
                    navegador desde el NFC, el QR o el código impreso.
                </p>
                <ul class="mt-9 space-y-5">
                    <li
                        v-for="(item, index) in holds"
                        :key="item.title"
                        class="flex gap-4"
                    >
                        <span
                            class="legacy-numeric grid size-9 shrink-0 place-items-center rounded-full bg-white text-xs font-semibold text-fl-gold-ink shadow-fl-micro"
                            aria-hidden="true"
                            >{{ index + 1 }}</span
                        >
                        <div>
                            <p class="font-medium text-foreground">
                                {{ item.title }}
                            </p>
                            <p
                                class="mt-0.5 text-[15px] leading-relaxed text-muted-foreground"
                            >
                                {{ item.body }}
                            </p>
                        </div>
                    </li>
                </ul>
            </Reveal>

            <div class="relative flex justify-center lg:col-span-7">
                <!-- The tag approaching the phone -->
                <div
                    class="tap-tag absolute top-[18%] left-[2%] z-10 hidden w-24 sm:block lg:left-[6%] lg:w-28"
                    aria-hidden="true"
                >
                    <TagVisualizer
                        :name="tag.name"
                        :subtitle="tag.subtitle"
                        :year="tag.year"
                        :accent="tag.accent"
                        :tilt="false"
                    />
                    <span
                        class="tap-ring absolute top-[58%] right-[-30%] size-14 rounded-full border-2 border-fl-gold/70"
                    />
                </div>

                <Reveal
                    direction="up"
                    :duration-ms="700"
                    class="relative w-[17.5rem] sm:w-[19rem]"
                >
                    <!-- Phone frame -->
                    <div
                        class="relative rounded-[2.75rem] border border-fl-line bg-white p-2.5 shadow-fl-overlay"
                        aria-label="Ejemplo de la página de historia que abre el tag"
                        role="img"
                    >
                        <div
                            class="absolute top-4 left-1/2 z-20 h-5 w-24 -translate-x-1/2 rounded-full bg-fl-black"
                            aria-hidden="true"
                        />
                        <div
                            class="relative overflow-hidden rounded-[2.2rem] bg-fl-paper"
                            aria-hidden="true"
                        >
                            <div class="relative aspect-[4/3.4]">
                                <img
                                    :src="photo.src"
                                    :srcset="photo.srcset"
                                    sizes="300px"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                    class="absolute inset-0 size-full object-cover"
                                />
                                <div
                                    class="absolute inset-0 bg-gradient-to-t from-fl-black/70 via-transparent to-transparent"
                                />
                                <span
                                    class="absolute top-11 right-4 rounded-full bg-white/90 px-2.5 py-0.5 text-[10px] font-semibold tracking-[0.16em] text-fl-gold-ink uppercase"
                                    >Ejemplo</span
                                >
                                <div
                                    class="absolute inset-x-4 bottom-4 text-white"
                                >
                                    <p
                                        class="text-[10px] font-semibold tracking-[0.2em] text-fl-gold-soft uppercase"
                                    >
                                        Finisher Legacy
                                    </p>
                                    <p
                                        class="mt-1 font-serif text-2xl leading-tight"
                                    >
                                        Tu nombre
                                    </p>
                                    <p
                                        class="mt-1 flex items-center gap-1 text-xs text-white/80"
                                    >
                                        <MapPin class="size-3" />
                                        {{ tag.name
                                        }}{{ tag.year ? ` · ${tag.year}` : '' }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-3 p-4">
                                <div class="grid grid-cols-3 gap-2 text-center">
                                    <div
                                        v-for="fact in [
                                            { k: 'Distancia', v: '21 km' },
                                            { k: 'Tiempo', v: '1:58:42' },
                                            { k: 'Lugar', v: '412' },
                                        ]"
                                        :key="fact.k"
                                        class="rounded-xl bg-white px-1 py-2 shadow-fl-micro"
                                    >
                                        <p
                                            class="text-[9px] tracking-[0.14em] text-muted-foreground uppercase"
                                        >
                                            {{ fact.k }}
                                        </p>
                                        <p
                                            class="legacy-numeric mt-0.5 text-sm font-semibold text-foreground"
                                        >
                                            {{ fact.v }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="rounded-xl bg-white p-3 shadow-fl-micro"
                                >
                                    <p
                                        class="font-serif text-[13px] leading-snug text-foreground italic"
                                    >
                                        “Para mamá, que me esperó en el
                                        kilómetro 18.”
                                    </p>
                                </div>
                                <div
                                    class="flex items-center gap-2 rounded-xl bg-white p-2.5 shadow-fl-micro"
                                >
                                    <span
                                        class="grid size-7 shrink-0 place-items-center rounded-full bg-fl-black text-white"
                                    >
                                        <Play class="size-3 fill-current" />
                                    </span>
                                    <span
                                        class="flex h-6 flex-1 items-center gap-[2px]"
                                    >
                                        <span
                                            v-for="(h, i) in bars"
                                            :key="i"
                                            class="w-[3px] rounded-full bg-fl-gold/70"
                                            :style="{ height: `${h}%` }"
                                        />
                                    </span>
                                </div>
                                <div class="flex gap-2 pb-1">
                                    <span
                                        class="flex h-9 flex-1 items-center justify-center gap-1.5 rounded-full bg-fl-black text-xs font-semibold text-white"
                                    >
                                        <Share2 class="size-3.5" /> Compartir
                                    </span>
                                    <span
                                        class="grid size-9 place-items-center rounded-full border border-fl-line bg-white text-fl-gold-ink"
                                    >
                                        <Heart class="size-3.5" />
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </Reveal>
            </div>
        </div>
    </section>
</template>

<style scoped>
.tap-tag {
    animation: tap-approach 4.5s cubic-bezier(0.45, 0, 0.2, 1) infinite;
    transform: rotate(-12deg);
}
.tap-ring {
    animation: tap-ring 4.5s ease-out infinite;
    opacity: 0;
}

@keyframes tap-approach {
    0%,
    100% {
        transform: translateX(0) rotate(-12deg);
    }
    40%,
    60% {
        transform: translateX(18px) rotate(-6deg);
    }
}

@keyframes tap-ring {
    0%,
    38% {
        opacity: 0;
        transform: scale(0.6);
    }
    45% {
        opacity: 0.9;
    }
    70%,
    100% {
        opacity: 0;
        transform: scale(1.6);
    }
}

@media (prefers-reduced-motion: reduce) {
    .tap-tag,
    .tap-ring {
        animation: none;
    }
}
</style>
