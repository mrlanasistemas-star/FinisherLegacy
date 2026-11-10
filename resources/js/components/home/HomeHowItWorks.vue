<script setup lang="ts">
/**
 * "Cómo funciona" — three steps (elige → acerca → revive) told by one
 * auto-playing scene: a CSS phone and the real tag artwork. The tag waits
 * in its box, travels to the phone (NFC ripples) and the phone opens an
 * example memory. The steps on the left are buttons, so the scene is also
 * fully controllable by pointer and keyboard; auto-play stops for good once
 * the visitor picks a step, pauses offscreen and never starts under
 * reduced motion. The phone's content is an illustration, labelled as such.
 */
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    AudioLines,
    Check,
    Nfc,
    Package,
    Share2,
} from '@lucide/vue';
import { onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import type { TagArt } from '@/components/three/tagStageContext';
import { vReveal } from '@/composables/useReveal';
import { howItWorks } from '@/routes';
import { prefersReducedMotion, useOnScreen, vStage } from './homeMotion';
import HomeSectionHead from './HomeSectionHead.vue';

defineProps<{ art: TagArt }>();

const STEP_MS = 4800;

const steps = [
    {
        title: 'Elige tu colección',
        text: 'Compra el tag de tu carrera en la tienda o recíbelo en tu evento. Llega listo para activarse con el código de su empaque.',
    },
    {
        title: 'Acerca tu celular',
        text: 'Sin códigos que escanear: el chip NFC del tag abre tu recuerdo con un toque en la parte trasera del teléfono.',
    },
    {
        title: 'Revive y comparte',
        text: 'Sube tus fotos, tu tiempo y tu historia. Tu familia lo ve desde cualquier teléfono, sin instalar nada.',
    },
];

const thumbs = [
    '/media/home/story/race-effort-640.webp',
    '/media/home/story/training-dawn-640.webp',
    '/media/home/final/legacy-final-poster-640.webp',
];

const active = ref(0);
const cycle = ref(0);
const auto = ref(true);
const scene = useTemplateRef<HTMLElement>('scene');
const { onScreen } = useOnScreen(scene, { threshold: 0.25 });
let timer: number | null = null;

function stop() {
    if (timer !== null) {
        window.clearInterval(timer);
        timer = null;
    }
}

function play() {
    stop();

    if (!auto.value || prefersReducedMotion()) {
        auto.value = false;

        return;
    }

    cycle.value++;
    timer = window.setInterval(() => {
        active.value = (active.value + 1) % steps.length;
        cycle.value++;
    }, STEP_MS);
}

function pick(index: number) {
    auto.value = false;
    stop();
    active.value = index;
}

watch(onScreen, (visible) => (visible ? play() : stop()));
onBeforeUnmount(stop);
</script>

<template>
    <section
        id="como-funciona"
        class="scroll-mt-20 bg-fl-paper py-20 sm:py-24 lg:py-32"
        aria-labelledby="how-title"
    >
        <div
            class="fl-container grid items-center gap-12 lg:grid-cols-12 lg:gap-10"
        >
            <div class="lg:col-span-5">
                <HomeSectionHead
                    id="how-title"
                    eyebrow="Cómo funciona"
                    title="Un toque y vuelves a tu meta."
                    lead="Tres pasos, sin instalar nada para verlo."
                />

                <ol v-reveal.stagger class="mt-9 space-y-3">
                    <li v-for="(step, index) in steps" :key="step.title">
                        <button
                            type="button"
                            class="how-step group relative w-full overflow-hidden rounded-[1.25rem] border px-5 py-4 text-left transition-[background-color,border-color,box-shadow] duration-500 focus-visible:ring-2 focus-visible:ring-fl-gold-dim focus-visible:ring-offset-2 focus-visible:outline-none motion-reduce:transition-none"
                            :class="
                                active === index
                                    ? 'border-fl-gold/60 bg-white shadow-fl-float'
                                    : 'border-transparent hover:border-fl-line hover:bg-white/60'
                            "
                            :aria-current="
                                active === index ? 'step' : undefined
                            "
                            @click="pick(index)"
                        >
                            <span class="flex items-start gap-4">
                                <span
                                    class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-full font-serif text-sm font-semibold transition-colors duration-500 motion-reduce:transition-none"
                                    :class="
                                        active === index
                                            ? 'bg-fl-black text-fl-gold'
                                            : 'bg-white text-fl-stone shadow-fl-micro'
                                    "
                                    aria-hidden="true"
                                    >{{ index + 1 }}</span
                                >
                                <span class="min-w-0">
                                    <span
                                        class="block font-serif text-lg font-semibold tracking-[-0.01em] text-foreground sm:text-xl"
                                    >
                                        <span class="sr-only"
                                            >Paso {{ index + 1 }}:
                                        </span>
                                        {{ step.title }}
                                    </span>
                                    <span
                                        class="mt-1 block text-[0.9375rem] leading-relaxed transition-colors duration-500 motion-reduce:transition-none"
                                        :class="
                                            active === index
                                                ? 'text-muted-foreground'
                                                : 'text-muted-foreground/75'
                                        "
                                        >{{ step.text }}</span
                                    >
                                </span>
                            </span>
                            <span
                                v-if="active === index && auto"
                                :key="cycle"
                                class="how-progress absolute inset-x-0 bottom-0 h-0.5 origin-left bg-fl-gold"
                                :style="{ animationDuration: `${STEP_MS}ms` }"
                                aria-hidden="true"
                            />
                        </button>
                    </li>
                </ol>

                <Link
                    v-reveal
                    :href="howItWorks()"
                    class="group mt-7 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <span class="fl-link fl-link--gold"
                        >Ver la guía completa</span
                    >
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none"
                        aria-hidden="true"
                    />
                </Link>
            </div>

            <!-- The scene -->
            <div class="order-first lg:order-none lg:col-span-7">
                <div
                    ref="scene"
                    v-stage
                    v-reveal="{ y: 28 }"
                    class="how-scene relative mx-auto aspect-[10/9] w-full max-w-[40rem] overflow-hidden rounded-hero border border-fl-line bg-white sm:aspect-[5/4]"
                    :data-step="active"
                    role="img"
                    :aria-label="`Ilustración del paso ${active + 1}: ${steps[active].title}.`"
                >
                    <div
                        class="pointer-events-none absolute inset-0 bg-[radial-gradient(55%_60%_at_50%_45%,color-mix(in_srgb,var(--fl-gold)_22%,transparent),transparent_75%)]"
                    />
                    <div
                        class="home-grain pointer-events-none absolute inset-0 opacity-30"
                    />

                    <!-- The box the tag arrives in -->
                    <div class="how-box" aria-hidden="true">
                        <span class="how-box__lid" />
                        <img
                            src="/images/brand/logo/fl-monograma.png"
                            alt=""
                            width="80"
                            height="36"
                            loading="lazy"
                            decoding="async"
                            class="absolute bottom-[14%] left-1/2 w-[34%] -translate-x-1/2 opacity-80"
                        />
                    </div>

                    <!-- Phone -->
                    <div class="how-phone" aria-hidden="true">
                        <span class="how-phone__island" />
                        <div class="how-phone__screen">
                            <!-- 1 · order on its way -->
                            <div class="how-layer how-layer--0">
                                <span
                                    class="grid size-[3.4em] place-items-center rounded-full bg-fl-cream text-fl-gold-ink"
                                >
                                    <Package class="size-[1.5em]" />
                                </span>
                                <span
                                    class="mt-3 font-serif text-[1em] leading-tight font-semibold text-fl-black"
                                    >Tu tag va en camino</span
                                >
                                <span
                                    class="mt-1 text-[0.75em] leading-snug text-fl-stone"
                                    >Colección {{ art.name }}</span
                                >
                                <span
                                    class="mt-4 block h-1 w-[70%] overflow-hidden rounded-full bg-fl-line"
                                >
                                    <span
                                        class="how-ship block h-full w-full origin-left rounded-full bg-fl-gold"
                                    />
                                </span>
                            </div>
                            <!-- 2 · NFC read -->
                            <div class="how-layer how-layer--1">
                                <span
                                    class="relative grid size-[5em] place-items-center"
                                >
                                    <span class="home-ripple inset-0" />
                                    <span
                                        class="home-ripple inset-0 [animation-delay:1s]"
                                    />
                                    <span
                                        class="home-ripple inset-0 [animation-delay:2s]"
                                    />
                                    <span
                                        class="relative grid size-[3.4em] place-items-center rounded-full bg-fl-black text-fl-gold"
                                    >
                                        <Nfc class="size-[1.5em]" />
                                    </span>
                                </span>
                                <span
                                    class="mt-4 font-serif text-[1em] leading-tight font-semibold text-fl-black"
                                    >Acerca tu tag</span
                                >
                                <span
                                    class="mt-1 text-[0.75em] leading-snug text-fl-stone"
                                    >Abriendo tu recuerdo…</span
                                >
                            </div>
                            <!-- 3 · the memory -->
                            <div
                                class="how-layer how-layer--2 !justify-start !p-0"
                            >
                                <span
                                    class="relative block h-[46%] w-full overflow-hidden"
                                >
                                    <img
                                        src="/media/home/story/medal-closeup-640.webp"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        class="how-memory-photo size-full object-cover object-[50%_30%]"
                                    />
                                    <span
                                        class="absolute inset-0 bg-gradient-to-t from-fl-black/70 to-transparent"
                                    />
                                    <span
                                        class="absolute bottom-[0.5em] left-[0.8em] text-left font-serif text-[1em] leading-tight font-semibold text-white"
                                        >Tu carrera<br /><span
                                            class="text-[0.7em] font-medium tracking-[0.14em] text-fl-gold-soft uppercase"
                                            >{{ art.name }}</span
                                        ></span
                                    >
                                </span>
                                <span
                                    class="grid w-full grid-cols-2 gap-[0.4em] px-[0.8em] pt-[0.8em] text-left"
                                >
                                    <span class="how-chip">
                                        <span class="how-chip__k">Tiempo</span>
                                        <span class="how-chip__v">1:52:07</span>
                                    </span>
                                    <span class="how-chip">
                                        <span class="how-chip__k"
                                            >Distancia</span
                                        >
                                        <span class="how-chip__v">21K</span>
                                    </span>
                                </span>
                                <span
                                    class="mx-[0.8em] mt-[0.4em] flex w-[calc(100%-1.6em)] items-center gap-[0.5em] rounded-[0.5em] bg-fl-black px-[0.7em] py-[0.55em] text-fl-gold"
                                >
                                    <AudioLines class="size-[1.1em] shrink-0" />
                                    <span
                                        class="home-eq flex h-[0.9em] flex-1 items-center gap-[2px]"
                                    >
                                        <span
                                            v-for="n in 14"
                                            :key="n"
                                            class="block h-full w-full rounded-full bg-fl-gold/80"
                                            :style="{
                                                animationDelay: `${(n % 5) * -0.2}s`,
                                            }"
                                        />
                                    </span>
                                </span>
                                <span
                                    class="mx-[0.8em] mt-[0.4em] flex w-[calc(100%-1.6em)] items-center justify-between rounded-[0.5em] border border-fl-line px-[0.7em] py-[0.45em] text-[0.72em] font-semibold text-fl-black"
                                >
                                    Compartir con mi familia
                                    <Share2
                                        class="size-[1em] text-fl-gold-ink"
                                    />
                                </span>
                                <span
                                    class="mx-[0.8em] mt-[0.5em] grid w-[calc(100%-1.6em)] grid-cols-3 gap-[0.35em]"
                                >
                                    <img
                                        v-for="thumb in thumbs"
                                        :key="thumb"
                                        :src="thumb"
                                        alt=""
                                        loading="lazy"
                                        decoding="async"
                                        class="aspect-square w-full rounded-[0.4em] object-cover"
                                    />
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- The tag -->
                    <div class="how-tag" aria-hidden="true">
                        <span class="how-tag__ripple home-ripple" />
                        <span
                            class="how-tag__ripple home-ripple [animation-delay:1.2s]"
                        />
                        <TagVisualizer v-bind="art" :tilt="false" label="" />
                    </div>

                    <!-- Step caption inside the scene -->
                    <p
                        class="absolute top-4 left-4 inline-flex items-center gap-2 rounded-full border border-fl-line bg-white/90 px-3 py-1.5 text-[11px] font-semibold tracking-[0.14em] text-fl-gold-ink uppercase backdrop-blur sm:top-5 sm:left-5"
                        aria-hidden="true"
                    >
                        <Check v-if="active === 2" class="size-3.5" />
                        <span v-else class="size-1.5 rounded-full bg-fl-gold" />
                        Paso {{ active + 1 }} de 3
                    </p>
                    <p
                        class="absolute right-4 bottom-3 text-[10px] text-fl-stone/80 sm:right-5 sm:bottom-4"
                    >
                        Ilustración de ejemplo
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.how-progress {
    animation: how-progress linear both;
}
@keyframes how-progress {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}

.how-scene {
    --how-ease: cubic-bezier(0.16, 1, 0.3, 1);
    container-type: inline-size;
}

/* Phone */
.how-phone {
    position: absolute;
    top: 50%;
    left: 34%;
    width: 30%;
    aspect-ratio: 9 / 18.5;
    transform: translate3d(-50%, -50%, 0) rotate(-4deg);
    border-radius: 12% / 6%;
    background: #0b0b0c;
    padding: 2.2%;
    box-shadow:
        0 40px 60px -40px rgb(11 11 12 / 0.55),
        inset 0 0 0 1px rgb(255 255 255 / 0.12);
    transition: transform 1100ms var(--how-ease);
}
.how-phone__island {
    position: absolute;
    top: 3.2%;
    left: 50%;
    z-index: 2;
    width: 26%;
    height: 2.6%;
    transform: translateX(-50%);
    border-radius: 9999px;
    background: #0b0b0c;
}
.how-phone__screen {
    position: relative;
    height: 100%;
    overflow: hidden;
    border-radius: 10% / 5%;
    background: #f8f7f3;
    font-size: clamp(7px, 2.05cqw, 13.5px);
}
.how-layer {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 12% 10%;
    text-align: center;
    opacity: 0;
    transform: translate3d(0, 10px, 0);
    transition:
        opacity 600ms ease,
        transform 800ms var(--how-ease);
}
.how-scene[data-step='0'] .how-layer--0,
.how-scene[data-step='1'] .how-layer--1,
.how-scene[data-step='2'] .how-layer--2 {
    opacity: 1;
    transform: none;
    transition-delay: 350ms;
}
.how-ship {
    animation: how-ship 3.6s ease-in-out infinite;
}
@keyframes how-ship {
    0% {
        transform: scaleX(0.08);
    }
    70%,
    100% {
        transform: scaleX(0.82);
    }
}
.how-memory-photo {
    transition: transform 2400ms var(--how-ease);
    transform: scale(1.18);
}
.how-scene[data-step='2'] .how-memory-photo {
    transform: scale(1);
}
.how-chip {
    display: block;
    border-radius: 0.5rem;
    background: #fff;
    padding: 0.4em 0.55em;
    box-shadow: 0 1px 2px rgb(23 23 20 / 0.06);
}
.how-chip__k {
    display: block;
    font-size: 0.6em;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--fl-stone);
}
.how-chip__v {
    display: block;
    font-family: var(--font-serif);
    font-size: 1em;
    font-weight: 600;
    color: var(--fl-black);
}

/* Box */
.how-box {
    position: absolute;
    top: 56%;
    left: 74%;
    width: 24%;
    aspect-ratio: 1 / 1.05;
    transform: translate3d(-50%, -50%, 0) rotate(5deg);
    border-radius: 8%;
    background: linear-gradient(160deg, #1e1e21, #0b0b0c);
    box-shadow: 0 30px 44px -30px rgb(11 11 12 / 0.6);
    transition:
        opacity 700ms ease,
        transform 1100ms var(--how-ease);
}
.how-box__lid {
    position: absolute;
    inset: 0 0 auto;
    height: 16%;
    border-radius: 16% 16% 0 0 / 60% 60% 0 0;
    border-bottom: 1px solid color-mix(in srgb, var(--fl-gold) 55%, transparent);
    background: #26262a;
}
.how-scene:not([data-step='0']) .how-box {
    opacity: 0;
    transform: translate3d(-30%, -40%, 0) rotate(9deg) scale(0.9);
}

/* Tag */
.how-tag {
    position: absolute;
    top: 50%;
    left: 74%;
    z-index: 3;
    width: 16%;
    transform: translate3d(-50%, -78%, 0) rotate(6deg);
    filter: drop-shadow(0 18px 18px rgb(11 11 12 / 0.28));
    transition: transform 1200ms var(--how-ease);
}
.how-tag__ripple {
    top: 55%;
    left: 50%;
    width: 150%;
    aspect-ratio: 1;
    margin: -75% 0 0 -75%;
    animation-name: none;
}
.how-scene[data-step='1'] .how-tag {
    transform: translate3d(-168%, -84%, 0) rotate(-9deg) scale(1.02);
}
.how-scene[data-step='1'] .how-tag__ripple {
    animation-name: home-ripple;
}
.how-scene[data-step='1'] .how-phone {
    transform: translate3d(-42%, -50%, 0) rotate(3deg);
}
.how-scene[data-step='2'] .how-tag {
    transform: translate3d(-10%, -48%, 0) rotate(9deg) scale(1.12);
}
.how-scene[data-step='2'] .how-phone {
    left: 40%;
    transform: translate3d(-50%, -50%, 0) rotate(-2deg) scale(1.06);
}

@media (prefers-reduced-motion: reduce) {
    .how-phone,
    .how-layer,
    .how-box,
    .how-tag,
    .how-memory-photo {
        transition: none;
    }
    .how-progress {
        display: none;
    }
    .how-scene:not([data-step='1']) .how-tag__ripple {
        display: none;
    }
}
</style>
