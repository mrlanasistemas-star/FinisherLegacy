<script setup lang="ts">
/**
 * "Recuerdos" — what a tag opens. Left: an animated product illustration
 * (photos that fan out, a result card that counts up, a voice message
 * playing, a dedication and the privacy switch cycling through its three
 * real modes). Right: what a memory holds and who can see it.
 *
 * The numbers and the dedication on the board are an EXAMPLE and labelled
 * as such; the photos are the brand's own. Motion pauses offscreen and is
 * static under reduced motion.
 */
import {
    BookOpenText,
    Download,
    Globe,
    Images,
    Link2,
    Lock,
    MapPin,
    Mic,
    PenLine,
    Play,
    Timer,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { vReveal } from '@/composables/useReveal';
import { ACTIVATE_URL } from '@/config/public-links';
import {
    prefersReducedMotion,
    useCountUp,
    useOnScreen,
    vParallax,
    vStage,
} from './homeMotion';
import HomeSectionHead from './HomeSectionHead.vue';

const board = useTemplateRef<HTMLElement>('board');
const { onScreen, entered } = useOnScreen(board, { threshold: 0.3 });

// Example result (illustrative): 1:52:07, position 128.
const { value: seconds, start: startTime } = useCountUp(6727, 1800);
const { value: position, start: startPosition } = useCountUp(128, 1500);
const time = computed(() => {
    const s = seconds.value;
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${Math.floor(s / 3600)}:${pad(Math.floor((s % 3600) / 60))}:${pad(s % 60)}`;
});

const privacyModes = [
    { icon: Lock, label: 'Privado' },
    { icon: Link2, label: 'Solo con enlace' },
    { icon: Globe, label: 'Público' },
];
const privacy = ref(1);
let timer: number | null = null;

watch(entered, (now) => {
    if (now) {
        startTime();
        startPosition();
    }
});

watch(onScreen, (visible) => {
    if (timer !== null) {
        window.clearInterval(timer);
        timer = null;
    }

    if (visible && !prefersReducedMotion()) {
        timer = window.setInterval(() => {
            privacy.value = (privacy.value + 1) % privacyModes.length;
        }, 2600);
    }
});

onBeforeUnmount(() => {
    if (timer !== null) {
        window.clearInterval(timer);
    }
});

const holds = [
    { icon: Images, title: 'Fotos', text: 'Las tuyas y las del evento.' },
    {
        icon: BookOpenText,
        title: 'Tu historia',
        text: 'Contada con tus palabras.',
    },
    {
        icon: Timer,
        title: 'Tu resultado',
        text: 'Tiempo, posición y distancia.',
    },
    {
        icon: MapPin,
        title: 'Fecha y lugar',
        text: 'Dónde y cuándo cruzaste la meta.',
    },
    {
        icon: PenLine,
        title: 'Dedicatoria',
        text: 'Para quien te acompañó.',
    },
    { icon: Mic, title: 'Mensaje de voz', text: 'La voz de los tuyos.' },
];

const bars = [
    0.4, 0.7, 1, 0.6, 0.85, 0.5, 0.95, 0.65, 0.4, 0.8, 0.55, 0.9, 0.45, 0.7,
    0.35, 0.6,
];
</script>

<template>
    <section
        id="recuerdos"
        class="scroll-mt-20 bg-white py-20 sm:py-24 lg:py-32"
        aria-labelledby="memories-title"
    >
        <div
            class="fl-container grid items-center gap-14 lg:grid-cols-12 lg:gap-12"
        >
            <!-- The board -->
            <div class="lg:col-span-7">
                <div
                    ref="board"
                    v-stage
                    class="mem-board relative mx-auto aspect-[10/11] w-full max-w-[34rem] sm:aspect-[6/5] sm:max-w-[40rem]"
                    :class="{ 'is-open': entered }"
                    role="img"
                    aria-label="Ilustración de un recuerdo: fotos de la carrera, tarjeta de resultado, mensaje de voz, dedicatoria y selector de privacidad. Datos de ejemplo."
                >
                    <div
                        class="absolute inset-[6%_4%] rounded-hero bg-fl-paper"
                        aria-hidden="true"
                    >
                        <div
                            class="absolute inset-0 rounded-hero bg-[radial-gradient(60%_60%_at_50%_45%,color-mix(in_srgb,var(--fl-gold)_26%,transparent),transparent_75%)]"
                        />
                    </div>

                    <!-- Photos -->
                    <div
                        v-parallax="14"
                        class="absolute inset-0"
                        aria-hidden="true"
                    >
                        <img
                            src="/media/home/story/training-dawn-640.webp"
                            alt=""
                            loading="lazy"
                            decoding="async"
                            class="mem-photo mem-photo--left object-[60%_50%]"
                        />
                        <img
                            src="/media/home/story/race-effort-640.webp"
                            alt=""
                            loading="lazy"
                            decoding="async"
                            class="mem-photo mem-photo--right object-[38%_50%]"
                        />
                        <img
                            src="/media/home/story/medal-closeup-667.webp"
                            alt=""
                            loading="lazy"
                            decoding="async"
                            class="mem-photo mem-photo--main"
                        />
                    </div>

                    <!-- Privacy -->
                    <div
                        class="mem-card mem-card--privacy absolute top-[2%] left-0 sm:top-[6%]"
                        aria-hidden="true"
                    >
                        <span
                            class="flex items-center gap-1 rounded-full border border-fl-line bg-white p-1 shadow-fl-float"
                        >
                            <span
                                v-for="(mode, i) in privacyModes"
                                :key="mode.label"
                                class="inline-flex h-7 items-center gap-1.5 rounded-full px-2.5 text-[11px] font-semibold whitespace-nowrap transition-colors duration-500 motion-reduce:transition-none"
                                :class="
                                    privacy === i
                                        ? 'bg-fl-black text-white'
                                        : 'text-fl-stone'
                                "
                            >
                                <component :is="mode.icon" class="size-3" />
                                <span
                                    :class="{ 'max-sm:hidden': privacy !== i }"
                                    >{{ mode.label }}</span
                                >
                            </span>
                        </span>
                    </div>

                    <!-- Voice message -->
                    <div
                        class="mem-card mem-card--voice absolute top-[20%] right-0 w-[46%] max-w-[15rem] sm:top-[14%]"
                        aria-hidden="true"
                    >
                        <span
                            class="flex items-center gap-2.5 rounded-2xl bg-fl-black p-2.5 pr-3.5 shadow-fl-overlay"
                        >
                            <span
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-fl-gold text-fl-black"
                            >
                                <Play class="size-4 fill-current" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span
                                    class="home-eq flex h-5 items-center gap-[2px]"
                                >
                                    <span
                                        v-for="(h, i) in bars"
                                        :key="i"
                                        class="block w-full rounded-full bg-fl-gold"
                                        :style="{
                                            height: `${h * 100}%`,
                                            animationDelay: `${(i % 6) * -0.2}s`,
                                        }"
                                    />
                                </span>
                                <span
                                    class="mt-1 block truncate text-[10px] font-medium text-white/60"
                                    >Mensaje de voz · 0:24</span
                                >
                            </span>
                        </span>
                    </div>

                    <!-- Result -->
                    <div
                        class="mem-card mem-card--result absolute bottom-[5%] left-0 w-[52%] max-w-[16.5rem] sm:bottom-[8%] sm:left-[2%]"
                        aria-hidden="true"
                    >
                        <span
                            class="block rounded-2xl border border-fl-line bg-white p-4 shadow-fl-overlay"
                        >
                            <span
                                class="block text-[10px] font-bold tracking-[0.2em] text-fl-gold-ink uppercase"
                                >Tu resultado</span
                            >
                            <span
                                class="mt-1 block font-serif text-[clamp(1.6rem,6vw,2.4rem)] leading-none font-semibold tracking-[-0.03em] text-fl-black tabular-nums"
                                >{{ time }}</span
                            >
                            <span
                                class="mt-3 grid grid-cols-2 gap-2 border-t border-fl-line pt-3 text-fl-black"
                            >
                                <span>
                                    <span
                                        class="block text-[9px] font-semibold tracking-[0.14em] text-fl-stone uppercase"
                                        >Posición</span
                                    >
                                    <span
                                        class="font-serif text-base font-semibold tabular-nums"
                                        >{{ position }}</span
                                    >
                                </span>
                                <span>
                                    <span
                                        class="block text-[9px] font-semibold tracking-[0.14em] text-fl-stone uppercase"
                                        >Distancia</span
                                    >
                                    <span
                                        class="font-serif text-base font-semibold"
                                        >21K</span
                                    >
                                </span>
                            </span>
                        </span>
                    </div>

                    <!-- Dedication -->
                    <div
                        class="mem-card mem-card--note absolute right-0 bottom-0 w-[44%] max-w-[13.5rem] sm:right-[3%] sm:bottom-[3%]"
                        aria-hidden="true"
                    >
                        <span
                            class="block rotate-2 rounded-2xl bg-fl-cream p-4 shadow-fl-float"
                        >
                            <span
                                class="block text-[10px] font-bold tracking-[0.2em] text-fl-gold-ink uppercase"
                                >Dedicatoria</span
                            >
                            <span
                                class="mt-1.5 block font-serif text-[0.8125rem] leading-snug font-medium text-fl-black italic sm:text-[0.9375rem]"
                                >“Para quien me esperó en la meta.”</span
                            >
                        </span>
                    </div>
                </div>
                <p class="mt-3 text-center text-[11px] text-fl-stone/80">
                    Ilustración con datos de ejemplo.
                </p>
            </div>

            <!-- Copy -->
            <div class="lg:col-span-5">
                <HomeSectionHead
                    id="memories-title"
                    eyebrow="Recuerdos"
                    title="Todo lo que viviste ese día, en un solo lugar."
                    lead="Cada tag abre un recuerdo que tú construyes: la historia completa de tu carrera, no solo una foto."
                />

                <ul
                    v-reveal.stagger
                    class="mt-9 grid grid-cols-2 gap-x-5 gap-y-6"
                >
                    <li
                        v-for="item in holds"
                        :key="item.title"
                        class="flex items-start gap-3"
                    >
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-full bg-fl-paper text-fl-gold-ink"
                            aria-hidden="true"
                        >
                            <component :is="item.icon" class="size-[1.1rem]" />
                        </span>
                        <span class="min-w-0">
                            <span
                                class="block font-serif text-[0.9375rem] font-semibold text-foreground"
                                >{{ item.title }}</span
                            >
                            <span
                                class="mt-0.5 block text-[0.8125rem] leading-snug text-muted-foreground"
                                >{{ item.text }}</span
                            >
                        </span>
                    </li>
                </ul>

                <div
                    v-reveal
                    class="mt-9 rounded-[1.25rem] border border-fl-line bg-fl-paper p-5"
                >
                    <p
                        class="flex items-start gap-3 text-[0.9375rem] leading-relaxed text-foreground"
                    >
                        <Lock
                            class="mt-1 size-4 shrink-0 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <span>
                            <strong class="font-semibold"
                                >Tú decides quién lo ve:</strong
                            >
                            privado, solo con enlace o público. Los enlaces que
                            compartes se pueden revocar.
                        </span>
                    </p>
                    <p
                        class="mt-3 flex items-start gap-3 text-[0.9375rem] leading-relaxed text-foreground"
                    >
                        <Download
                            class="mt-1 size-4 shrink-0 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                        <span>
                            <strong class="font-semibold">Es tuyo:</strong>
                            descarga todo tu recuerdo cuando quieras.
                        </span>
                    </p>
                </div>

                <a v-reveal :href="ACTIVATE_URL" class="fl-btn-secondary mt-8">
                    Ya tengo mi tag: activarlo
                </a>
            </div>
        </div>
    </section>
</template>

<style scoped>
.mem-board {
    --mem-ease: cubic-bezier(0.16, 1, 0.3, 1);
}
.mem-photo {
    position: absolute;
    top: 50%;
    left: 50%;
    border: 5px solid #fff;
    border-radius: 1rem;
    object-fit: cover;
    box-shadow: 0 30px 50px -32px rgb(11 11 12 / 0.6);
    transition: transform 1300ms var(--mem-ease);
}
.mem-photo--main {
    width: 40%;
    aspect-ratio: 3 / 4;
    transform: translate3d(-50%, -52%, 0) rotate(0deg);
}
.mem-photo--left,
.mem-photo--right {
    width: 36%;
    aspect-ratio: 4 / 5;
    transform: translate3d(-50%, -52%, 0) rotate(0deg) scale(0.92);
}
.mem-board.is-open .mem-photo--main {
    transform: translate3d(-50%, -54%, 0) rotate(-1.5deg);
}
.mem-board.is-open .mem-photo--left {
    transform: translate3d(-108%, -50%, 0) rotate(-9deg) scale(0.92);
    transition-delay: 120ms;
}
.mem-board.is-open .mem-photo--right {
    transform: translate3d(8%, -56%, 0) rotate(8deg) scale(0.92);
    transition-delay: 220ms;
}
.mem-card {
    opacity: 0;
    transform: translate3d(0, 16px, 0) scale(0.96);
    transition:
        opacity 700ms ease,
        transform 900ms var(--mem-ease);
}
.mem-board.is-open .mem-card {
    opacity: 1;
    transform: none;
}
.mem-board.is-open .mem-card--privacy {
    transition-delay: 500ms;
}
.mem-board.is-open .mem-card--voice {
    transition-delay: 650ms;
}
.mem-board.is-open .mem-card--result {
    transition-delay: 800ms;
}
.mem-board.is-open .mem-card--note {
    transition-delay: 950ms;
}
@media (prefers-reduced-motion: reduce) {
    .mem-photo,
    .mem-card {
        transition: none;
    }
    .mem-card {
        opacity: 1;
        transform: none;
    }
}
</style>
