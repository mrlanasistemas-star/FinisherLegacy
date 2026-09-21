<script setup lang="ts">
/**
 * MEDALLA → PLACA → LEGACY CODE → LEGACY PROFILE as one object evolving,
 * not four different shapes taking turns. A single frame (same metal
 * material, same light sweep, same pointer-tilt) morphs from a circular
 * medal into a rectangular plate — border-radius and aspect-ratio driven
 * directly by scroll progress, no CSS transition fighting the rAF loop —
 * while only the CONTENT inside crossfades per stage. Replaces the old
 * four-icon stepper (JourneyExperience.vue) — that component is now
 * unused; not deleted in case another page wants the simpler static
 * version later.
 *
 * Runs the sticky-pinned morph on every viewport now (2026-08-21 polish
 * pass — used to be lg+ only, mobile got the plain vertical list). Only
 * prefers-reduced-motion still falls back to that plain, fully accessible
 * vertical sequence. The pinned region is a responsive height (shorter on
 * phones, taller on desktop) and the two-column layout collapses to one
 * column (frame, then text) below lg.
 */
import { Award, IdCard, Medal, QrCode } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

const stages = [
    {
        icon: Medal,
        title: 'Medalla',
        copy: 'El logro comienza aquí.',
    },
    {
        icon: Award,
        title: 'Placa',
        copy: 'Convertimos los datos de esa carrera en una pieza creada para permanecer.',
    },
    {
        icon: QrCode,
        title: 'Legacy Code',
        copy: 'Una conexión permanente entre el objeto físico y su historia digital.',
    },
    {
        icon: IdCard,
        title: 'Legacy Profile',
        copy: 'Cada carrera pasa a formar parte de tu historia como atleta.',
    },
];

const prefersReducedMotion = useReducedMotion();
// Fail-open (brief item C7): starts false so SSR/no-JS renders the plain,
// complete, always-visible stage list below (v-else) — matching what
// useReducedMotion() itself already does for the same reason. Only once
// onMounted confirms JS is actually running, and that this browser has
// what the scroll-driven morph needs, does this upgrade to the sticky
// experience.
const jsReady = ref(false);
const usesSticky = computed(() => jsReady.value && !prefersReducedMotion.value);

const wrapperEl = useTemplateRef<HTMLElement>('wrapper');
const frameEl = useTemplateRef<HTMLElement>('frame');
const progress = ref(0); // 0..1 across the whole pinned region
let rafId: number | null = null;

function measure() {
    rafId = null;
    const el = wrapperEl.value;

    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();
    const total = rect.height - window.innerHeight;
    const raw = total > 0 ? -rect.top / total : 0;
    progress.value = Math.min(1, Math.max(0, raw));
}

function onScroll() {
    if (rafId === null) {
        rafId = requestAnimationFrame(measure);
    }
}

onMounted(() => {
    // requestAnimationFrame is near-universal, but this is the one API
    // the whole morph depends on every frame — feature-detected rather
    // than assumed (brief item C11), so a browser without it keeps the
    // static list instead of running a scroll handler that can never
    // actually update anything.
    if (typeof requestAnimationFrame !== 'function') {
        return;
    }

    jsReady.value = true;

    if (usesSticky.value) {
        measure();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onScroll);

    if (rafId !== null) {
        cancelAnimationFrame(rafId);
    }
});

// Which stage is active, and how far into it (0..1).
const stageFloat = computed(() => progress.value * stages.length);
const activeIndex = computed(() =>
    Math.min(stages.length - 1, Math.floor(stageFloat.value)),
);
const localT = computed(() => stageFloat.value - activeIndex.value);

// Each stage gets a clear "hold" window before it starts crossfading into
// the next — content used to start leaving the instant a stage became
// active, which read as barely any transition at all for some pairs
// (brand feedback: la fase 3→4 "casi no dura"). Crossfade now only runs
// across the back half of each stage's own scroll range, so there's always
// a settled read period first.
const HOLD = 0.5;
function crossfadeT(raw: number) {
    return Math.min(1, Math.max(0, (raw - HOLD) / (1 - HOLD)));
}

// The frame itself is the thing that "becomes" a plate: a perfect circle
// (medal) morphing into a short engraved bar (plate) across stage 0. No CSS
// transition on these — progress already updates every scroll frame via
// rAF, so a transition would only lag behind it. Target is aspect-[5/2] and
// an 8px machined edge — deliberately far from a credit-card ratio, same
// geometry language as PlateShowcase.vue. A continuous rotate+bob keeps the
// object visibly alive through every stage, not just while it's morphing.
const frameStyle = computed(() => {
    const inMedalStage = activeIndex.value === 0;
    const t = inMedalStage ? crossfadeT(localT.value) : 1;
    const radius = 999 - t * 991; // 999px (circle) → 8px (machined edge)
    const aspect = 1 + t * 1.5; // 1/1 (circle) → 5/2 (plate bar)
    const wobble = Math.sin(stageFloat.value * 1.1) * 3;
    const bob = Math.sin(stageFloat.value * 0.9) * 5;

    return {
        borderRadius: `${radius}px`,
        aspectRatio: `${aspect}`,
        transform: `rotate(${wobble}deg) translateY(${bob}px)`,
    };
});

// The medal doesn't just morph in isolation — a ribbon and two lateral
// attachment loops appear as it becomes a plate (brand system: "esta pieza
// pertenece a la medalla" has to read even mid-scroll), then fade once the
// scene moves on to Legacy Code / Profile, where they'd stop being true.
const ribbonOpacity = computed(() => {
    const s = stageFloat.value;

    if (s < 1.6) {
        return 1;
    }

    if (s < 2) {
        return 1 - (s - 1.6) / 0.4;
    }

    return 0;
});
const attachmentOpacity = computed(() => {
    const s = stageFloat.value;

    if (s < 0.5) {
        return 0;
    }

    if (s < 1) {
        return (s - 0.5) / 0.5;
    }

    if (s < 1.6) {
        return 1;
    }

    if (s < 2) {
        return 1 - (s - 1.6) / 0.4;
    }

    return 0;
});

// Content inside the frame crossfades between the 4 stages, held at full
// opacity for the first half of its stage (see HOLD above) then leaving
// with a slide + scale on top of the blur — not just a flat opacity fade,
// so every stage change reads as motion, not a cut.
function contentStyle(index: number) {
    const isLast = index === stages.length - 1;
    const active = activeIndex.value;
    const t = crossfadeT(localT.value);

    if (active === index) {
        const leave = isLast ? 0 : t;

        return {
            opacity: isLast ? 1 : 1 - leave,
            filter: `blur(${leave * 5}px)`,
            transform: `translateY(${leave * -14}px) scale(${1 - leave * 0.05})`,
        };
    }

    if (active === index - 1) {
        return {
            opacity: t,
            filter: `blur(${(1 - t) * 5}px)`,
            transform: `translateY(${(1 - t) * 14}px) scale(${0.95 + t * 0.05})`,
        };
    }

    return {
        opacity: 0,
        filter: 'blur(5px)',
        transform: 'translateY(14px) scale(0.95)',
    };
}

// Pointer tilt on the frame, on top of the scroll-driven shape morph — the
// same "product you can touch" feel the hero plate has.
function handlePointerMove(event: PointerEvent) {
    if (prefersReducedMotion.value || !frameEl.value) {
        return;
    }

    const rect = frameEl.value.getBoundingClientRect();
    const relX = (event.clientX - rect.left) / rect.width;
    const relY = (event.clientY - rect.top) / rect.height;
    frameEl.value.style.setProperty('--glare-x', `${relX * 100}%`);
    frameEl.value.style.setProperty('--glare-y', `${relY * 100}%`);
}
</script>

<template>
    <!-- Motion-enabled (all viewports): sticky-pinned morph -->
    <div
        v-if="usesSticky"
        ref="wrapper"
        class="relative h-[260vh] sm:h-[300vh] lg:h-[400vh]"
    >
        <div
            class="sticky top-16 flex h-[calc(100svh-4rem)] items-center overflow-hidden"
        >
            <div
                class="mx-auto grid w-full max-w-6xl grid-cols-1 items-center gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:px-8"
            >
                <!-- The object: one frame, evolving -->
                <div
                    class="relative mx-auto w-full max-w-[220px] sm:max-w-xs lg:max-w-lg"
                >
                    <div
                        class="absolute -inset-16 rounded-full bg-gradient-to-br from-fl-gold/25 via-fl-gold-soft/10 to-transparent blur-3xl"
                        :style="{
                            opacity: activeIndex === 2 ? 0.7 : 0.4,
                        }"
                    />

                    <!-- Orbit ring — only reads while the frame is still a medal -->
                    <div
                        class="fl-orbit pointer-events-none absolute top-1/2 left-1/2 size-[120%] -translate-x-1/2 -translate-y-1/2 rounded-full border border-dashed border-fl-gold/25 transition-opacity duration-500"
                        :style="{
                            opacity: activeIndex === 0 ? 1 - localT : 0,
                        }"
                    />

                    <!-- Ribbon the medal/plate hangs from — proves "this
                         piece belongs to the medal" instead of the frame
                         floating on its own. -->
                    <div
                        aria-hidden="true"
                        class="pointer-events-none absolute top-0 left-1/2 h-10 w-1.5 -translate-x-1/2 -translate-y-[95%] sm:h-14 sm:w-2 lg:h-16"
                        :style="{
                            opacity: ribbonOpacity,
                            background:
                                'linear-gradient(180deg, transparent, var(--fl-gold-dim), var(--fl-gold))',
                        }"
                    />

                    <!-- Lateral attachment loops -->
                    <span
                        aria-hidden="true"
                        class="pointer-events-none absolute top-1/2 -left-2.5 z-10 flex size-5 -translate-y-1/2 items-center justify-center sm:-left-3 sm:size-6 lg:-left-3.5 lg:size-7"
                        :style="{ opacity: attachmentOpacity }"
                    >
                        <span
                            class="absolute inset-0 rounded-full border-2 border-white/20"
                            style="
                                background: linear-gradient(
                                    155deg,
                                    #4b4b4e 0%,
                                    #232326 55%,
                                    #141416 100%
                                );
                            "
                        />
                        <span
                            class="relative size-2 rounded-full bg-fl-black ring-1 ring-black/60 sm:size-2.5"
                        />
                    </span>
                    <span
                        aria-hidden="true"
                        class="pointer-events-none absolute top-1/2 -right-2.5 z-10 flex size-5 -translate-y-1/2 items-center justify-center sm:-right-3 sm:size-6 lg:-right-3.5 lg:size-7"
                        :style="{ opacity: attachmentOpacity }"
                    >
                        <span
                            class="absolute inset-0 rounded-full border-2 border-white/20"
                            style="
                                background: linear-gradient(
                                    155deg,
                                    #4b4b4e 0%,
                                    #232326 55%,
                                    #141416 100%
                                );
                            "
                        />
                        <span
                            class="relative size-2 rounded-full bg-fl-black ring-1 ring-black/60 sm:size-2.5"
                        />
                    </span>

                    <div
                        ref="frame"
                        class="fl-frame-sweep relative mx-auto w-full overflow-hidden border border-white/15 p-3 shadow-[0_30px_80px_-15px_rgba(0,0,0,0.75)] sm:p-5 lg:p-8"
                        style="
                            --glare-x: 50%;
                            --glare-y: 30%;
                            background:
                                repeating-linear-gradient(
                                    100deg,
                                    rgba(255, 255, 255, 0.05) 0px,
                                    rgba(255, 255, 255, 0.05) 1px,
                                    transparent 1px,
                                    transparent 3px
                                ),
                                radial-gradient(
                                    circle at var(--glare-x) var(--glare-y),
                                    rgba(255, 255, 255, 0.16),
                                    transparent 45%
                                ),
                                linear-gradient(
                                    160deg,
                                    #3a3a3d 0%,
                                    #232326 55%,
                                    #17171a 100%
                                );
                        "
                        :style="frameStyle"
                        @pointermove="handlePointerMove"
                    >
                        <div
                            class="pointer-events-none absolute inset-0 bg-gradient-to-br from-white/10 via-transparent to-transparent"
                        />

                        <!-- Medal -->
                        <div
                            class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 lg:gap-3"
                            :style="contentStyle(0)"
                        >
                            <Medal
                                class="fl-stage-icon size-9 text-fl-gold-soft sm:size-12 lg:size-16"
                            />
                            <span
                                class="text-[9px] font-semibold tracking-[0.25em] text-fl-gold-soft uppercase sm:text-xs sm:tracking-[0.3em]"
                                >El logro</span
                            >
                        </div>

                        <!-- Plate -->
                        <div
                            class="absolute inset-0 flex flex-col justify-between p-1 sm:p-1.5 lg:p-2"
                            :style="contentStyle(1)"
                        >
                            <p
                                class="text-[9px] font-semibold tracking-[0.25em] text-fl-gold-soft uppercase sm:text-xs sm:tracking-[0.35em]"
                            >
                                Finisher · Legacy
                            </p>
                            <div class="flex items-end justify-between">
                                <span
                                    class="legacy-numeric text-base font-bold text-white sm:text-2xl lg:text-3xl"
                                    >03:42:18</span
                                >
                                <Award
                                    class="fl-stage-icon size-4 text-fl-gold-soft sm:size-6 lg:size-8"
                                />
                            </div>
                        </div>

                        <!-- Legacy Code (QR) -->
                        <div
                            class="absolute inset-0 flex items-center justify-center"
                            :style="contentStyle(2)"
                        >
                            <div
                                class="fl-stage-icon relative grid aspect-square w-[34%] max-w-40 min-w-16 grid-cols-7 gap-[3px] sm:gap-1"
                            >
                                <span
                                    v-for="n in 49"
                                    :key="n"
                                    class="aspect-square rounded-[1px]"
                                    :class="
                                        [
                                            1, 5, 8, 12, 15, 20, 24, 27, 31, 36,
                                            40, 44, 47,
                                        ].includes(n)
                                            ? 'bg-fl-gold-soft'
                                            : 'bg-white/10'
                                    "
                                />
                                <span
                                    class="fl-qr-scan absolute inset-x-2 h-0.5 rounded-full bg-fl-gold-soft shadow-[0_0_10px_2px_rgba(224,202,137,0.8)]"
                                />
                            </div>
                        </div>

                        <!-- Legacy Profile -->
                        <div
                            class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 lg:gap-4"
                            :style="contentStyle(3)"
                        >
                            <div
                                class="fl-stage-icon flex size-8 items-center justify-center rounded-full border-2 border-fl-gold/40 bg-black/30 text-fl-gold-soft sm:size-11 lg:size-14"
                            >
                                <IdCard class="size-4 sm:size-5 lg:size-6" />
                            </div>
                            <div
                                class="legacy-numeric text-[10px] font-semibold text-white sm:text-xs lg:text-sm"
                            >
                                12 medallas
                            </div>
                            <div
                                class="h-1 w-20 rounded-full bg-white/15 sm:w-28 lg:h-1.5 lg:w-40"
                            >
                                <div
                                    class="fl-profile-fill h-full rounded-full bg-gradient-to-r from-fl-gold to-fl-gold-soft"
                                    :style="{
                                        width:
                                            activeIndex === 3
                                                ? `${20 + localT * 60}%`
                                                : '20%',
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Side text, synced to the active stage -->
                <div
                    class="flex flex-col items-center gap-4 text-center lg:flex-row lg:items-center lg:gap-6 lg:text-left"
                >
                    <div
                        class="legacy-line-v hidden h-48 shrink-0 self-center opacity-40 lg:block"
                    >
                        <div
                            class="h-full w-full origin-top bg-gradient-to-b from-fl-gold via-fl-gold-soft to-transparent transition-transform duration-200"
                            :style="{ transform: `scaleY(${progress})` }"
                        />
                    </div>
                    <Transition name="fl-stage-fade" mode="out-in">
                        <div :key="activeIndex" class="min-w-0">
                            <span
                                class="legacy-numeric text-xs font-semibold text-white/40"
                            >
                                0{{ activeIndex + 1 }} / 0{{ stages.length }}
                            </span>
                            <h3
                                class="mt-2 text-2xl font-bold text-white sm:text-3xl lg:text-5xl"
                            >
                                {{ stages[activeIndex].title }}
                            </h3>
                            <p
                                class="mx-auto mt-3 max-w-sm text-sm leading-relaxed text-white/60 lg:mx-0 lg:mt-4 lg:text-lg"
                            >
                                {{ stages[activeIndex].copy }}
                            </p>
                        </div>
                    </Transition>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile / reduced-motion: plain vertical sequence -->
    <div
        v-else
        class="mx-auto flex max-w-3xl flex-col gap-10 px-4 py-24 sm:px-6 lg:px-8"
    >
        <Reveal
            v-for="(stage, index) in stages"
            :key="stage.title"
            :delay-ms="index * 90"
            class="flex items-start gap-4"
        >
            <div
                class="flex size-12 shrink-0 items-center justify-center rounded-full border border-fl-gold/40 bg-fl-graphite/60 text-fl-gold-soft"
            >
                <component :is="stage.icon" class="size-5" />
            </div>
            <div>
                <span class="legacy-numeric text-xs font-semibold text-white/40"
                    >0{{ index + 1 }}</span
                >
                <h3 class="mt-1 text-lg font-semibold text-white">
                    {{ stage.title }}
                </h3>
                <p class="mt-1 text-sm leading-relaxed text-white/60">
                    {{ stage.copy }}
                </p>
            </div>
        </Reveal>
    </div>
</template>

<style scoped>
.fl-orbit {
    animation: fl-orbit-spin 18s linear infinite;
}
@keyframes fl-orbit-spin {
    to {
        transform: translate(-50%, -50%) rotate(360deg);
    }
}

.fl-frame-sweep::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        115deg,
        transparent 35%,
        rgba(255, 255, 255, 0.14) 50%,
        transparent 65%
    );
    transform: translateX(-130%);
    animation: fl-frame-sweep 4.5s ease-in-out infinite;
    pointer-events: none;
}
@keyframes fl-frame-sweep {
    0%,
    35% {
        transform: translateX(-130%);
    }
    75%,
    100% {
        transform: translateX(130%);
    }
}

.fl-qr-scan {
    animation: fl-qr-scan 2.2s ease-in-out infinite;
}
@keyframes fl-qr-scan {
    0%,
    100% {
        top: 12%;
        opacity: 0;
    }
    15% {
        opacity: 1;
    }
    50% {
        top: 88%;
        opacity: 1;
    }
    65% {
        opacity: 0;
    }
}

.fl-profile-fill {
    transition: width 500ms ease-out;
}

/* Whichever stage icon/glyph is on screen breathes gently — the frame
   keeps moving (rotate+bob) between shape changes, this keeps the content
   inside it feeling just as alive. */
.fl-stage-icon {
    animation: fl-stage-icon-breathe 3.2s ease-in-out infinite;
}
@keyframes fl-stage-icon-breathe {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.06);
    }
}

.fl-stage-fade-enter-active,
.fl-stage-fade-leave-active {
    transition:
        opacity 220ms ease,
        transform 220ms ease;
}
.fl-stage-fade-enter-from {
    opacity: 0;
    transform: translateY(10px);
}
.fl-stage-fade-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}

@media (prefers-reduced-motion: reduce) {
    .fl-orbit,
    .fl-frame-sweep::before,
    .fl-qr-scan,
    .fl-stage-icon {
        animation: none;
    }
    .fl-stage-fade-enter-active,
    .fl-stage-fade-leave-active {
        transition: none;
    }
}
</style>
