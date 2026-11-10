<script setup lang="ts">
/**
 * "Fotos de evento" — two cards: for athletes (search by participant
 * number → pick → download; illustrated by a contact sheet where a scan
 * line passes and "your" frames light up) and a short teaser for
 * photographers linking to their landing page. The bib number on the
 * search pill is an example; the frames are the brand's own photos.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Aperture, Camera, Check, Hash } from '@lucide/vue';
import { computed } from 'vue';
import { vReveal } from '@/composables/useReveal';
import { useI18n } from '@/i18n';
import { landing as photographersLanding } from '@/routes/photographers';
import { index as photosIndex } from '@/routes/photos';
import { vStage } from './homeMotion';

const { t } = useI18n();

const shots = [
    { src: '/media/home/story/race-effort-640.webp', match: false },
    { src: '/media/home/story/medal-closeup-640.webp', match: true },
    { src: '/media/home/story/finish-emotion-640.webp', match: false },
    { src: '/media/home/story/training-dawn-640.webp', match: false },
    { src: '/media/home/final/legacy-final-poster-640.webp', match: true },
    { src: '/media/home/story/race-effort-640.webp', match: false },
];

const steps = computed(() => [
    t('Escribe tu número'),
    t('Elige tus fotos'),
    t('Descárgalas'),
]);
</script>

<template>
    <section
        id="fotos"
        class="scroll-mt-20 bg-fl-paper py-20 sm:py-24 lg:py-28"
        aria-labelledby="photos-title"
    >
        <div class="fl-container grid gap-5 lg:grid-cols-12">
            <!-- Athletes -->
            <div
                v-reveal
                class="grid items-center gap-10 overflow-hidden rounded-hero border border-fl-line bg-white p-6 sm:p-10 lg:col-span-8 lg:grid-cols-2 lg:p-12"
            >
                <div>
                    <p class="fl-eyebrow inline-flex items-center gap-2">
                        <span
                            class="inline-block h-px w-6 bg-fl-gold-dim/70"
                            aria-hidden="true"
                        />
                        {{ $t('Fotos de evento') }}
                    </p>
                    <h2
                        id="photos-title"
                        class="fl-display mt-4 text-[clamp(1.8rem,3.4vw,2.75rem)] text-balance"
                    >
                        {{ $t('Encuentra tus fotos de carrera.') }}
                    </h2>
                    <p
                        class="mt-4 text-[1.0625rem] leading-relaxed text-pretty text-muted-foreground"
                    >
                        {{
                            $t(
                                'Los fotógrafos del evento suben sus fotos. Tú escribes tu número de participante, eliges las tuyas y las descargas en alta resolución.',
                            )
                        }}
                    </p>
                    <ol class="mt-6 flex flex-wrap gap-2">
                        <li
                            v-for="(step, i) in steps"
                            :key="step"
                            class="inline-flex h-9 items-center gap-2 rounded-full bg-fl-paper pr-3.5 pl-1.5 text-[13px] font-medium text-foreground"
                        >
                            <span
                                class="grid size-6 place-items-center rounded-full bg-fl-black font-serif text-[11px] font-semibold text-fl-gold"
                                aria-hidden="true"
                                >{{ i + 1 }}</span
                            >
                            {{ step }}
                        </li>
                    </ol>
                    <Link
                        :href="photosIndex()"
                        class="fl-btn-primary group mt-8"
                    >
                        {{ $t('Buscar mis fotos') }}
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </Link>
                </div>

                <!-- Contact sheet -->
                <div
                    v-stage
                    class="relative mx-auto w-full max-w-[24rem]"
                    role="img"
                    :aria-label="
                        $t(
                            'Ilustración: se busca un número de participante y, entre las fotos del evento, se resaltan las del atleta.',
                        )
                    "
                >
                    <div
                        class="mx-auto flex h-12 w-[78%] items-center gap-2.5 rounded-full border border-fl-line bg-white px-4 shadow-fl-float"
                        aria-hidden="true"
                    >
                        <Hash class="size-4 text-fl-gold-ink" />
                        <span
                            class="font-serif text-lg font-semibold tracking-[0.12em] text-fl-black tabular-nums"
                            >128</span
                        >
                        <span
                            class="ml-auto text-[10px] font-semibold tracking-[0.14em] text-fl-stone uppercase"
                            >{{ $t('Tu número') }}</span
                        >
                    </div>
                    <div
                        class="relative mt-5 grid grid-cols-3 gap-2 overflow-hidden rounded-2xl"
                        aria-hidden="true"
                    >
                        <span
                            v-for="(shot, i) in shots"
                            :key="i"
                            class="ph-shot relative block aspect-[4/5] overflow-hidden rounded-xl bg-fl-black"
                            :class="{ 'ph-shot--match': shot.match }"
                        >
                            <img
                                :src="shot.src"
                                alt=""
                                loading="lazy"
                                decoding="async"
                                class="size-full object-cover"
                                :class="{ '-scale-x-100': i === 5 }"
                            />
                            <span
                                v-if="shot.match"
                                class="ph-check absolute top-1.5 right-1.5 grid size-6 place-items-center rounded-full bg-fl-gold text-fl-black"
                            >
                                <Check class="size-3.5" />
                            </span>
                        </span>
                        <span class="ph-scan" />
                    </div>
                </div>
            </div>

            <!-- Photographers -->
            <div
                v-reveal="{ delay: 120 }"
                class="relative flex flex-col justify-between overflow-hidden rounded-hero bg-fl-black p-8 text-white sm:p-10 lg:col-span-4"
            >
                <span
                    v-stage
                    class="pointer-events-none absolute -top-16 -right-16 block size-64 text-fl-gold/20"
                    aria-hidden="true"
                >
                    <Aperture
                        class="ph-aperture size-full"
                        :stroke-width="0.6"
                    />
                </span>
                <span
                    class="relative grid size-12 place-items-center rounded-full bg-fl-gold text-fl-black"
                    aria-hidden="true"
                >
                    <Camera class="size-5" />
                </span>
                <div class="relative mt-16 lg:mt-24">
                    <h3
                        class="font-serif text-[clamp(1.5rem,2.4vw,2rem)] leading-tight font-semibold tracking-[-0.02em]"
                    >
                        {{ $t('¿Eres fotógrafo?') }}
                    </h3>
                    <p
                        class="mt-3 text-[0.9375rem] leading-relaxed text-white/70"
                    >
                        {{
                            $t(
                                'Sube las fotos de los eventos que cubres, véndelas a los atletas que aparecen en ellas y sigue tus ventas desde tu propio panel.',
                            )
                        }}
                    </p>
                    <Link
                        :href="photographersLanding()"
                        class="group mt-7 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-fl-gold focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-4 focus-visible:ring-offset-fl-black focus-visible:outline-none"
                    >
                        <span class="fl-link">{{
                            $t('Conocer el programa')
                        }}</span>
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
/* One 6 s loop: the scan passes, the athlete's frames light up, rest dim. */
.ph-scan {
    position: absolute;
    inset: 0 auto 0 0;
    width: 22%;
    background: linear-gradient(
        90deg,
        transparent,
        color-mix(in srgb, var(--fl-gold) 55%, transparent),
        transparent
    );
    opacity: 0;
    animation: ph-scan 6s ease-in-out infinite;
}
@keyframes ph-scan {
    0% {
        transform: translate3d(-100%, 0, 0);
        opacity: 0;
    }
    8% {
        opacity: 1;
    }
    34% {
        transform: translate3d(460%, 0, 0);
        opacity: 1;
    }
    40%,
    100% {
        transform: translate3d(460%, 0, 0);
        opacity: 0;
    }
}
.ph-shot img {
    animation: ph-dim 6s ease-in-out infinite;
}
.ph-shot--match {
    outline: 2px solid var(--fl-gold);
    outline-offset: -2px;
}
.ph-shot--match img {
    animation: none;
}
@keyframes ph-dim {
    0%,
    34% {
        opacity: 1;
    }
    44%,
    90% {
        opacity: 0.38;
    }
    100% {
        opacity: 1;
    }
}
.ph-check {
    animation: ph-check 6s var(--legacy-ease) infinite;
}
@keyframes ph-check {
    0%,
    36% {
        transform: scale(0);
        opacity: 0;
    }
    44%,
    90% {
        transform: scale(1);
        opacity: 1;
    }
    100% {
        transform: scale(0);
        opacity: 0;
    }
}
.ph-aperture {
    animation: ph-spin 60s linear infinite;
}
@keyframes ph-spin {
    to {
        transform: rotate(360deg);
    }
}
@media (prefers-reduced-motion: reduce) {
    .ph-scan {
        display: none;
    }
    .ph-shot img,
    .ph-check,
    .ph-aperture {
        animation: none;
    }
    .ph-shot:not(.ph-shot--match) img {
        opacity: 0.45;
    }
}
</style>
