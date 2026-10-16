<script setup lang="ts">
/**
 * "ACERCA TU TELÉFONO" (owner spec §42): a phone comes down to the plate,
 * the NFC pulses, the profile card appears — explained in one sentence.
 *
 * Fail-open by construction: the resting CSS state IS the final frame
 * (phone over the plate, card visible). The sequence only plays when the
 * section enters the viewport and adds `data-play`; without JS, without
 * IntersectionObserver or with reduced motion, people see the end state.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Nfc, RotateCcw } from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import PhoneProfile from '@/components/public/home/PhoneProfile.vue';
import PlateFront from '@/components/public/home/PlateFront.vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { SAMPLE_FRONT } from '@/lib/plate-art';
import { howItWorks } from '@/routes';

const reduced = useReducedMotion();
const stage = useTemplateRef<HTMLElement>('stage');
const playing = ref(false);
let observer: IntersectionObserver | null = null;

async function play() {
    if (reduced.value) {
        return;
    }

    playing.value = false;
    await nextTick();
    // Force a reflow so the animation restarts on replay.
    void stage.value?.offsetWidth;
    playing.value = true;
}

onMounted(() => {
    if (typeof IntersectionObserver === 'undefined' || !stage.value) {
        return;
    }

    observer = new IntersectionObserver(
        ([entry]) => {
            if (entry?.isIntersecting) {
                play();
                observer?.disconnect();
            }
        },
        { threshold: 0.45 },
    );
    observer.observe(stage.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <section
        class="relative overflow-hidden border-y border-border bg-[linear-gradient(180deg,#fff,#f8f7f3)]"
        aria-labelledby="nfc-title"
    >
        <div
            class="fl-container grid items-center gap-12 py-20 sm:py-28 lg:grid-cols-12 lg:gap-10"
        >
            <div class="lg:col-span-5">
                <p class="fl-eyebrow flex items-center gap-2">
                    <Nfc class="size-4" aria-hidden="true" /> NFC
                </p>
                <h2
                    id="nfc-title"
                    class="fl-display mt-5 text-[2.7rem] leading-[0.95] uppercase sm:text-6xl xl:text-7xl"
                >
                    Acerca tu teléfono.
                </h2>
                <p
                    class="mt-6 max-w-md text-xl leading-relaxed text-foreground/80"
                >
                    Acerca tu teléfono a tu Legacy Plate y se abre tu carrera:
                    tiempo, fotos y medalla, sin descargar nada.
                </p>
                <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3">
                    <Link
                        :href="howItWorks()"
                        class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                    >
                        Cómo funciona <ArrowRight class="size-4" />
                    </Link>
                    <button
                        v-if="!reduced"
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                        @click="play"
                    >
                        <RotateCcw class="size-4" /> Ver de nuevo
                    </button>
                </div>
            </div>

            <div class="lg:col-span-7">
                <div
                    ref="stage"
                    class="fl-tap relative mx-auto aspect-[6/5] w-full max-w-[38rem]"
                    :data-play="playing ? '1' : undefined"
                    role="img"
                    aria-label="Un teléfono se acerca a la Legacy Plate; con el NFC se abre el perfil del atleta con su resultado."
                >
                    <!-- surface -->
                    <div
                        class="absolute inset-x-[4%] bottom-[2%] h-[46%] rounded-[50%] bg-[radial-gradient(closest-side,rgb(23_23_20/0.10),transparent)]"
                        aria-hidden="true"
                    />

                    <!-- plate lying on the table -->
                    <div
                        class="fl-tap__plate absolute bottom-[12%] left-[6%] w-[58%]"
                    >
                        <div class="fl-tap__plate-tilt">
                            <PlateFront :sweep="false" />
                        </div>
                    </div>

                    <!-- pulse over the FL panel (where the NFC sits) -->
                    <div
                        class="fl-tap__pulse absolute bottom-[26%] left-[49%] size-[20%]"
                        aria-hidden="true"
                    >
                        <span />
                        <span />
                        <span />
                    </div>

                    <!-- phone -->
                    <div
                        class="fl-tap__phone absolute top-[2%] left-[40%] w-[25%]"
                    >
                        <PhoneProfile />
                    </div>

                    <!-- revealed card -->
                    <div
                        class="fl-tap__card absolute top-[34%] right-0 w-[38%] rounded-2xl border border-border bg-card p-4 shadow-[0_30px_50px_-30px_rgb(23_23_20/0.45)] sm:p-5"
                    >
                        <p
                            class="text-[10px] font-semibold tracking-[0.18em] text-fl-gold-ink uppercase"
                        >
                            Se abrió tu legado
                        </p>
                        <p
                            class="mt-2 font-serif text-[clamp(0.95rem,2.2vw,1.35rem)] leading-tight text-foreground"
                        >
                            José Alberto Carlos Bueno
                        </p>
                        <p
                            class="legacy-numeric mt-2 text-[clamp(1.4rem,3.4vw,2.2rem)] font-semibold tracking-tight"
                        >
                            {{ SAMPLE_FRONT.time }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Medio maratón · 21.1 km
                        </p>
                        <span
                            class="mt-3 inline-block rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >Ejemplo</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.fl-tap__plate-tilt {
    transform: perspective(900px) rotateX(48deg) rotateZ(-8deg);
    transform-origin: 50% 70%;
}
/* Resting = final frame. */
.fl-tap__phone {
    transform: translateY(0) rotate(-6deg);
}
.fl-tap__pulse span {
    position: absolute;
    inset: 0;
    border-radius: 9999px;
    border: 2px solid color-mix(in srgb, var(--fl-gold) 85%, transparent);
    opacity: 0.35;
}
.fl-tap__pulse span:nth-child(2) {
    inset: 18%;
    opacity: 0.55;
}
.fl-tap__pulse span:nth-child(3) {
    inset: 36%;
    opacity: 0.8;
    background: color-mix(in srgb, var(--fl-gold) 25%, transparent);
}

/* The sequence, only when JS says "play". */
.fl-tap[data-play] .fl-tap__phone {
    animation: fl-tap-approach 1300ms cubic-bezier(0.2, 0.8, 0.2, 1) both;
}
.fl-tap[data-play] .fl-tap__pulse span {
    animation: fl-tap-pulse 1400ms cubic-bezier(0.2, 0.6, 0.3, 1) 1050ms both;
}
.fl-tap[data-play] .fl-tap__pulse span:nth-child(2) {
    animation-delay: 1250ms;
}
.fl-tap[data-play] .fl-tap__pulse span:nth-child(3) {
    animation-delay: 1450ms;
}
.fl-tap[data-play] .fl-tap__card {
    animation: fl-tap-card 900ms cubic-bezier(0.16, 1, 0.3, 1) 1700ms both;
}

@keyframes fl-tap-approach {
    from {
        transform: translate(30%, -38%) rotate(8deg);
        opacity: 0;
    }
    30% {
        opacity: 1;
    }
    to {
        transform: translateY(0) rotate(-6deg);
        opacity: 1;
    }
}
@keyframes fl-tap-pulse {
    0% {
        transform: scale(0.4);
        opacity: 0;
    }
    35% {
        opacity: 1;
    }
    100% {
        transform: scale(1.6);
        opacity: 0.35;
    }
}
@keyframes fl-tap-card {
    from {
        opacity: 0;
        transform: translate(-14%, 10%) scale(0.92);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-tap[data-play] .fl-tap__phone,
    .fl-tap[data-play] .fl-tap__pulse span,
    .fl-tap[data-play] .fl-tap__card {
        animation: none;
    }
}
</style>
