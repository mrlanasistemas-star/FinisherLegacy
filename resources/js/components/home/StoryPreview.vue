<script setup lang="ts">
/**
 * "Así se ve tu historia" — the NFC tap, told in one loop: the tag comes
 * to the phone, the phone feels it (rings) and the story page pops up.
 * The tag is a stop of the Home's travelling 3D tag (on phones / reduced
 * motion, the illustrative SVG). The animation only plays while the
 * section is in view; with reduced motion the story is simply shown.
 *
 * It is ONE EXAMPLE, clearly labelled ("Ejemplo", "Tu nombre"); the photo
 * is project imagery, the figures are illustrative and nothing in the
 * mockup is a control.
 */
import { Heart, MapPin, Nfc, Play, Share2 } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import TagAnchor from '@/components/three/TagAnchor.vue';
import { MEDIA } from '@/config/media';

const props = defineProps<{
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

const pose = {
    face: 'back' as const,
    turn: -0.55,
    tilt: -0.06,
    roll: 0.1,
    pulse: true,
    art: props.tag,
};

/** Deterministic bar heights for the illustrative waveform. */
const bars = Array.from({ length: 28 }, (_, i) =>
    Math.round(25 + 70 * Math.abs(Math.sin(i * 0.55) * Math.cos(i * 0.17))),
);

const scene = useTemplateRef<HTMLElement>('scene');
const playing = ref(false);
let observer: IntersectionObserver | null = null;

onMounted(() => {
    if (!scene.value || !('IntersectionObserver' in window)) {
        return;
    }

    observer = new IntersectionObserver(
        ([entry]) => {
            playing.value = !!entry?.isIntersecting;
        },
        { threshold: 0.35 },
    );
    observer.observe(scene.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <section
        class="relative overflow-x-clip bg-fl-cream/60 py-20 sm:py-24 lg:py-32"
        aria-labelledby="story-preview-title"
    >
        <div
            class="home-grain pointer-events-none absolute inset-0 opacity-30"
            aria-hidden="true"
        />
        <div
            class="fl-container relative grid items-center gap-14 lg:grid-cols-12 lg:gap-10"
        >
            <Reveal class="text-center lg:col-span-5 lg:text-left">
                <p class="fl-eyebrow">Tu historia</p>
                <h2
                    id="story-preview-title"
                    class="fl-display mt-4 text-[clamp(2.1rem,4.4vw,3.6rem)]"
                >
                    Acércalo al celular y esto es lo que se abre.
                </h2>
                <p
                    class="mx-auto mt-5 max-w-[30rem] text-lg leading-relaxed text-muted-foreground lg:mx-0"
                >
                    Tu página personal, sin instalar nada: se abre en el
                    navegador en cuanto tu celular toca el tag.
                </p>
                <ul class="mx-auto mt-9 max-w-md space-y-5 text-left lg:mx-0">
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

            <div
                ref="scene"
                class="tap-scene relative mx-auto w-full max-w-[34rem] lg:col-span-7"
                :class="{ 'is-playing': playing }"
            >
                <div class="relative mx-auto w-[16.5rem] sm:w-[19rem]">
                    <!-- Phone frame -->
                    <div
                        class="phone relative rounded-[2.75rem] border border-fl-line bg-white p-2.5 shadow-fl-overlay"
                        aria-label="Ejemplo de la página de historia que se abre al acercar el celular al tag"
                        role="img"
                    >
                        <div
                            class="absolute top-4 left-1/2 z-30 h-5 w-24 -translate-x-1/2 rounded-full bg-fl-black"
                            aria-hidden="true"
                        />
                        <div
                            class="relative overflow-hidden rounded-[2.2rem] bg-fl-paper"
                            aria-hidden="true"
                        >
                            <div class="story">
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
                                            }}{{
                                                tag.year ? ` · ${tag.year}` : ''
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <div class="space-y-3 p-4">
                                    <div
                                        class="grid grid-cols-3 gap-2 text-center"
                                    >
                                        <div
                                            v-for="(fact, i) in [
                                                { k: 'Distancia', v: '21 km' },
                                                { k: 'Tiempo', v: '1:58:42' },
                                                { k: 'Lugar', v: '412' },
                                            ]"
                                            :key="fact.k"
                                            class="pop rounded-xl bg-white px-1 py-2 shadow-fl-micro"
                                            :style="{ '--d': i }"
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
                                        class="pop rounded-xl bg-white p-3 shadow-fl-micro"
                                        style="--d: 3"
                                    >
                                        <p
                                            class="font-serif text-[13px] leading-snug text-foreground italic"
                                        >
                                            “Para mamá, que me esperó en el
                                            kilómetro 18.”
                                        </p>
                                    </div>
                                    <div
                                        class="pop flex items-center gap-2 rounded-xl bg-white p-2.5 shadow-fl-micro"
                                        style="--d: 4"
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
                                            <Share2 class="size-3.5" />
                                            Compartir
                                        </span>
                                        <span
                                            class="grid size-9 place-items-center rounded-full border border-fl-line bg-white text-fl-gold-ink"
                                        >
                                            <Heart class="size-3.5" />
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Waiting screen: before the tap -->
                            <div
                                class="lock absolute inset-0 z-20 flex flex-col items-center justify-center gap-4 bg-fl-paper px-6 text-center"
                            >
                                <span
                                    class="relative grid size-20 place-items-center rounded-full border border-fl-gold/50 bg-white text-fl-gold-ink shadow-fl-micro"
                                >
                                    <Nfc class="size-8" />
                                    <span
                                        class="lock-ring absolute inset-0 rounded-full border-2 border-fl-gold"
                                    />
                                </span>
                                <p
                                    class="font-serif text-xl leading-snug text-foreground"
                                >
                                    Acerca tu tag
                                </p>
                                <p
                                    class="text-[11px] font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                                >
                                    Listo para leer NFC
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Contact rings where the tag touches the phone -->
                    <span
                        class="contact absolute top-[42%] right-[-1.75rem] size-16 rounded-full border-2 border-fl-gold"
                        aria-hidden="true"
                    />

                    <!-- The tag (3D on desktop, poster otherwise) -->
                    <TagAnchor
                        class="tap-tag absolute top-[24%] right-[-3rem] h-40 w-[6.5rem] sm:right-[-8.5rem] sm:h-56 sm:w-36 lg:right-[-11.5rem] lg:h-80 lg:w-52"
                        :pose="pose"
                    />
                </div>

                <p
                    class="mt-8 text-center text-[11px] tracking-[0.16em] text-muted-foreground uppercase"
                >
                    Ejemplo ilustrativo
                </p>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* One 6 s loop: approach → touch (rings) → story pops → tag steps back. */
.lock,
.contact {
    opacity: 0;
}
.lock {
    visibility: hidden;
}

.is-playing .tap-tag {
    animation: tap-approach 6s cubic-bezier(0.45, 0, 0.2, 1) infinite;
}
.is-playing .lock {
    visibility: visible;
    animation: tap-lock 6s ease infinite;
}
.is-playing .lock-ring {
    animation: tap-ring-small 1.6s ease-out infinite;
}
.is-playing .contact {
    animation: tap-contact 6s ease-out infinite;
}
.is-playing .story {
    animation: tap-story 6s cubic-bezier(0.16, 1, 0.3, 1) infinite;
    transform-origin: 50% 30%;
}
.is-playing .pop {
    animation: tap-pop 6s cubic-bezier(0.16, 1, 0.3, 1) infinite;
    animation-delay: calc(var(--d, 0) * 60ms);
}

@keyframes tap-approach {
    0%,
    12% {
        transform: translate(0, 0) rotate(10deg);
    }
    34%,
    44% {
        transform: translate(-3.25rem, 0.5rem) rotate(-4deg);
    }
    70%,
    100% {
        transform: translate(0, 0) rotate(10deg);
    }
}

@keyframes tap-lock {
    0%,
    36% {
        opacity: 1;
    }
    42%,
    94% {
        opacity: 0;
    }
    100% {
        opacity: 1;
    }
}

@keyframes tap-contact {
    0%,
    33% {
        opacity: 0;
        transform: scale(0.5);
    }
    37% {
        opacity: 0.95;
    }
    55%,
    100% {
        opacity: 0;
        transform: scale(2.2);
    }
}

@keyframes tap-story {
    0%,
    38% {
        transform: scale(0.94);
    }
    48%,
    100% {
        transform: scale(1);
    }
}

@keyframes tap-pop {
    0%,
    40% {
        opacity: 0;
        transform: translateY(10px);
    }
    52%,
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes tap-ring-small {
    from {
        opacity: 0.8;
        transform: scale(1);
    }
    to {
        opacity: 0;
        transform: scale(1.7);
    }
}

@media (prefers-reduced-motion: reduce) {
    .is-playing .tap-tag,
    .is-playing .lock,
    .is-playing .lock-ring,
    .is-playing .contact,
    .is-playing .story,
    .is-playing .pop {
        animation: none;
    }
    .is-playing .lock {
        visibility: hidden;
    }
}
</style>
