<script setup lang="ts">
/**
 * "Entrenamientos · Envíame ánimo" — the page's first carbon section.
 * Background: the brand's dawn-run video (muted loop, loaded and played
 * only while the section is on screen; the poster stays under reduced
 * motion). Right: an illustrative training player — voice messages from
 * "your people" flow into it, the waveform plays and the REAL speed steps
 * (0.75–2×, same as the product) change how fast it moves.
 *
 * Senders are relations ("Mamá", "Tu coach"), never invented people.
 */
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Flame,
    Heart,
    LockKeyhole,
    Medal,
    Music,
    Pause,
    Repeat,
    SkipBack,
    SkipForward,
} from '@lucide/vue';
import { ref, useTemplateRef, watch } from 'vue';
import { vReveal } from '@/composables/useReveal';
import { index as trainingIndex } from '@/routes/training';
import { prefersReducedMotion, useOnScreen, vStage } from './homeMotion';
import HomeSectionHead from './HomeSectionHead.vue';

const section = useTemplateRef<HTMLElement>('section');
const video = useTemplateRef<HTMLVideoElement>('video');
const { onScreen, entered } = useOnScreen(section, { rootMargin: '200px' });

watch(onScreen, (visible) => {
    const el = video.value;

    if (!el || prefersReducedMotion()) {
        return;
    }

    if (visible) {
        el.play().catch(() => {
            /* autoplay blocked: the poster stays */
        });
    } else {
        el.pause();
    }
});

const speeds = [0.75, 1, 1.25, 1.5, 2];
const speed = ref(1);

const steps = [
    {
        title: 'Compartes tu enlace',
        text: '«Envíame ánimo»: lo mandas por donde quieras a tu familia y amigos.',
    },
    {
        title: 'Ellos te graban',
        text: 'Desde cualquier navegador, sin cuenta y sin instalar nada.',
    },
    {
        title: 'Tú los escuchas',
        text: 'Como una lista de reproducción mientras entrenas.',
    },
];

const perks = [
    { icon: Music, label: 'Suena sobre tu música' },
    { icon: LockKeyhole, label: 'Con la pantalla bloqueada' },
    { icon: Repeat, label: 'Modo intervalos' },
];

const incoming = [
    { icon: Heart, from: 'Mamá', length: '0:18' },
    { icon: Flame, from: 'Tu coach', length: '0:32' },
    { icon: Medal, from: 'Tu mejor amigo', length: '0:24' },
];

const wave = Array.from({ length: 34 }, (_, i) =>
    Number((0.3 + Math.abs(Math.sin(i * 1.7) * 0.7)).toFixed(2)),
);
</script>

<template>
    <section
        id="entrenamientos"
        ref="section"
        v-stage
        class="relative scroll-mt-16 overflow-hidden bg-fl-black py-20 text-white sm:py-24 lg:py-32"
        aria-labelledby="training-title"
    >
        <!-- Dawn run, far right, melting into carbon -->
        <div class="pointer-events-none absolute inset-0" aria-hidden="true">
            <video
                ref="video"
                class="absolute inset-0 size-full object-cover opacity-70"
                muted
                loop
                playsinline
                preload="none"
                poster="/media/home/story/training-dawn-poster.webp"
            >
                <template v-if="entered">
                    <source
                        src="/media/home/story/training-dawn.webm"
                        type="video/webm"
                    />
                    <source
                        src="/media/home/story/training-dawn.mp4"
                        type="video/mp4"
                    />
                </template>
            </video>
            <div
                class="absolute inset-0 bg-[linear-gradient(90deg,#0b0b0c_0%,rgb(11_11_12/0.9)_40%,rgb(11_11_12/0.45)_100%)]"
            />
            <div
                class="absolute inset-0 bg-[linear-gradient(180deg,#0b0b0c_0%,transparent_22%,transparent_78%,#0b0b0c_100%)]"
            />
        </div>

        <div
            class="fl-container relative grid items-center gap-14 lg:grid-cols-12 lg:gap-10"
        >
            <div class="lg:col-span-6">
                <HomeSectionHead
                    id="training-title"
                    eyebrow="Entrenamientos · Envíame ánimo"
                    title="Entrena con las voces de quienes creen en ti."
                    lead="Los kilómetros difíciles pesan menos cuando te acompaña tu gente. Ellos te dejan mensajes de voz; tú los escuchas justo cuando más los necesitas."
                    tone="dark"
                />

                <ol
                    v-reveal.stagger
                    class="mt-10 grid gap-5 sm:grid-cols-3 sm:gap-4"
                >
                    <li
                        v-for="(step, i) in steps"
                        :key="step.title"
                        class="relative border-t border-white/15 pt-4"
                    >
                        <span
                            class="absolute -top-px left-0 h-px w-10 bg-fl-gold"
                            aria-hidden="true"
                        />
                        <span
                            class="font-serif text-xs font-semibold tracking-[0.18em] text-fl-gold"
                            aria-hidden="true"
                            >0{{ i + 1 }}</span
                        >
                        <span
                            class="mt-1.5 block font-serif text-base font-semibold text-white"
                            >{{ step.title }}</span
                        >
                        <span
                            class="mt-1 block text-sm leading-relaxed text-white/65"
                            >{{ step.text }}</span
                        >
                    </li>
                </ol>

                <ul v-reveal class="mt-8 flex flex-wrap gap-2">
                    <li
                        v-for="perk in perks"
                        :key="perk.label"
                        class="inline-flex h-9 items-center gap-2 rounded-full border border-white/15 bg-white/[0.06] px-3.5 text-[13px] font-medium text-white/90 backdrop-blur"
                    >
                        <component
                            :is="perk.icon"
                            class="size-3.5 text-fl-gold"
                            aria-hidden="true"
                        />
                        {{ perk.label }}
                    </li>
                </ul>

                <Link
                    v-reveal
                    :href="trainingIndex()"
                    class="home-shine group mt-9 inline-flex h-12 items-center justify-center gap-2 rounded-full bg-fl-gold px-7 text-[15px] font-semibold text-fl-black transition-[background-color,transform] hover:-translate-y-0.5 hover:bg-fl-gold-soft focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-fl-black focus-visible:outline-none motion-reduce:transition-none"
                >
                    Crear mi enlace de ánimo
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none"
                        aria-hidden="true"
                    />
                </Link>
            </div>

            <!-- Player -->
            <div class="lg:col-span-6">
                <div
                    v-reveal="{ y: 28 }"
                    class="relative mx-auto w-full max-w-[27rem] lg:mr-0"
                >
                    <!-- Incoming voice messages -->
                    <ul
                        class="relative z-10 mb-[-1.25rem] space-y-2.5 px-3 sm:px-6"
                        aria-label="Mensajes de voz de ejemplo que llegan al reproductor"
                    >
                        <li
                            v-for="(msg, i) in incoming"
                            :key="msg.from"
                            class="tr-bubble flex items-center gap-3 rounded-2xl border border-white/10 bg-[#1b1b1e]/90 p-2.5 pr-4 shadow-fl-overlay backdrop-blur"
                            :class="i % 2 ? 'ml-[14%]' : 'mr-[14%]'"
                            :style="{ animationDelay: `${i * 1.3}s` }"
                        >
                            <span
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-fl-gold/15 text-fl-gold"
                                aria-hidden="true"
                            >
                                <component :is="msg.icon" class="size-4" />
                            </span>
                            <span
                                class="min-w-0 shrink-0 text-[13px] font-semibold text-white"
                                >{{ msg.from }}</span
                            >
                            <span
                                class="home-eq flex h-4 min-w-0 flex-1 items-center gap-[2px]"
                                aria-hidden="true"
                            >
                                <span
                                    v-for="n in 16"
                                    :key="n"
                                    class="block h-full w-full rounded-full bg-white/35"
                                    :style="{
                                        animationDelay: `${((n + i) % 6) * -0.2}s`,
                                    }"
                                />
                            </span>
                            <span
                                class="shrink-0 text-[11px] text-white/50 tabular-nums"
                                >{{ msg.length }}</span
                            >
                        </li>
                    </ul>

                    <div
                        class="relative rounded-[1.75rem] border border-white/10 bg-[linear-gradient(160deg,#232327,#111113)] p-5 pt-10 shadow-[0_50px_80px_-40px_rgb(0_0_0/0.9)] sm:p-7 sm:pt-12"
                    >
                        <p
                            class="text-[10px] font-bold tracking-[0.22em] text-fl-gold uppercase"
                        >
                            Modo entrenamiento
                        </p>
                        <p
                            class="mt-1.5 font-serif text-xl font-semibold text-white"
                        >
                            Suena: Mamá
                        </p>
                        <p class="mt-1 text-sm text-white/60">
                            “Vas increíble. No aflojes, aquí te espero.”
                        </p>

                        <!-- Waveform -->
                        <div
                            class="tr-wave home-eq mt-6 flex h-14 items-center gap-[3px]"
                            :style="{ '--eq-speed': `${1.2 / speed}s` }"
                            aria-hidden="true"
                        >
                            <span
                                v-for="(h, i) in wave"
                                :key="i"
                                class="block w-full rounded-full"
                                :class="i < 13 ? 'bg-fl-gold' : 'bg-white/25'"
                                :style="{
                                    height: `${h * 100}%`,
                                    animationDelay: `${(i % 7) * -0.17}s`,
                                }"
                            />
                        </div>
                        <div
                            class="mt-2 flex justify-between text-[11px] text-white/45 tabular-nums"
                            aria-hidden="true"
                        >
                            <span>0:07</span>
                            <span>0:18</span>
                        </div>

                        <div
                            class="mt-4 flex items-center justify-center gap-6 text-white"
                            aria-hidden="true"
                        >
                            <SkipBack class="size-5 text-white/70" />
                            <span
                                class="relative grid size-14 place-items-center rounded-full bg-fl-gold text-fl-black"
                            >
                                <span class="home-ripple inset-0" />
                                <Pause class="size-5 fill-current" />
                            </span>
                            <SkipForward class="size-5 text-white/70" />
                        </div>

                        <div
                            class="mt-6 flex items-center justify-between gap-3 border-t border-white/10 pt-4"
                        >
                            <span
                                id="training-speed-label"
                                class="text-[11px] font-semibold tracking-[0.14em] text-white/55 uppercase"
                                >Velocidad</span
                            >
                            <div
                                class="flex gap-1"
                                role="group"
                                aria-labelledby="training-speed-label"
                            >
                                <button
                                    v-for="s in speeds"
                                    :key="s"
                                    type="button"
                                    class="h-9 min-w-10 rounded-full px-2 text-xs font-semibold tabular-nums transition-colors focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none motion-reduce:transition-none sm:min-w-11"
                                    :class="
                                        speed === s
                                            ? 'bg-white text-fl-black'
                                            : 'bg-white/[0.07] text-white/70 hover:bg-white/15'
                                    "
                                    :aria-pressed="speed === s"
                                    :aria-label="`Velocidad ${s}×`"
                                    @click="speed = s"
                                >
                                    {{ s }}×
                                </button>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 text-center text-[11px] text-white/40">
                        Ilustración de ejemplo del reproductor.
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.tr-wave > span {
    animation-duration: var(--eq-speed, 1.2s);
}
.tr-bubble {
    animation: tr-bubble 3.9s ease-in-out infinite;
}
@keyframes tr-bubble {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
        border-color: rgb(255 255 255 / 0.1);
    }
    12% {
        transform: translate3d(0, -4px, 0) scale(1.015);
        border-color: color-mix(in srgb, var(--fl-gold) 60%, transparent);
    }
    30% {
        transform: translate3d(0, 0, 0);
        border-color: rgb(255 255 255 / 0.1);
    }
}
@media (prefers-reduced-motion: reduce) {
    .tr-bubble {
        animation: none;
    }
}
</style>
