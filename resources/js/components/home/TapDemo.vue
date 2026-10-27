<script setup lang="ts">
/**
 * Bloque C — "Acerca tu teléfono." A wordless demo of the gesture:
 * the tag waits, the phone approaches, an NFC pulse, the story opens.
 * Plays once when it scrolls into view (no loop), can be replayed, and
 * jumps straight to the final frame under prefers-reduced-motion. The
 * profile on the phone is an EXAMPLE ("Tu nombre"), labelled as such.
 */
import { RotateCcw } from '@lucide/vue';
import { onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { useInView } from '@/composables/useInView';
import { MEDIA } from '@/config/media';

defineProps<{
    tag: {
        name: string;
        subtitle: string | null;
        year: number | null;
        accent: string | null;
    };
}>();

const photo = MEDIA.photo.cyclist;
const stage = useTemplateRef<HTMLElement>('stage');
const { inView, reducedMotion } = useInView(stage, { threshold: 0.45 });

/** 0 idle · 1 approach · 2 pulse · 3 story open */
const step = ref(0);
const timers: ReturnType<typeof setTimeout>[] = [];

function clear() {
    timers.splice(0).forEach(clearTimeout);
}

function play() {
    clear();

    if (reducedMotion.value) {
        step.value = 3;

        return;
    }

    step.value = 0;
    timers.push(setTimeout(() => (step.value = 1), 250));
    timers.push(setTimeout(() => (step.value = 2), 1350));
    timers.push(setTimeout(() => (step.value = 3), 2500));
}

watch(inView, (visible) => visible && play(), { immediate: true });
onBeforeUnmount(clear);

const nodes = ['Tu tag', 'NFC · QR', 'Tu historia'];
</script>

<template>
    <section
        class="relative overflow-hidden bg-fl-graphite py-20 text-white sm:py-28 lg:py-36"
        aria-labelledby="tap-demo-title"
    >
        <div
            class="fl-container grid items-center gap-14 lg:grid-cols-12 lg:gap-10"
        >
            <div class="lg:col-span-4">
                <p
                    class="flex items-center gap-3 text-[11px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
                >
                    <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                    La historia
                </p>
                <h2
                    id="tap-demo-title"
                    class="mt-6 font-serif text-[clamp(2.25rem,4.6vw,4rem)] leading-[1.04] font-medium tracking-[-0.02em]"
                >
                    Acerca tu teléfono.
                    <span class="block text-fl-gold-soft italic"
                        >Vuelve al momento.</span
                    >
                </h2>
                <p
                    class="mt-6 max-w-[28rem] text-lg leading-relaxed text-white/70"
                >
                    El tag abre tu historia con NFC. Si tu teléfono no lo lee,
                    escanea el QR del reverso o escribe el código impreso.
                </p>
                <button
                    type="button"
                    class="mt-8 inline-flex min-h-11 items-center gap-2 text-sm font-medium text-white/85 underline decoration-fl-gold decoration-1 underline-offset-[6px] transition-colors hover:text-white hover:decoration-2"
                    @click="play"
                >
                    <RotateCcw class="size-4" aria-hidden="true" />
                    Ver de nuevo
                </button>
            </div>

            <div class="lg:col-span-8">
                <div
                    ref="stage"
                    class="demo relative mx-auto aspect-[4/5] w-full max-w-xl overflow-hidden rounded-hero bg-fl-black sm:aspect-[5/4] lg:max-w-none"
                    :data-step="step"
                    role="img"
                    aria-label="Animación: un teléfono se acerca al Finisher Legacy Tag, el NFC lo detecta y se abre la historia del atleta en la pantalla."
                >
                    <!-- floor light -->
                    <div
                        class="absolute inset-x-0 bottom-0 h-2/3 bg-[radial-gradient(60%_60%_at_40%_100%,color-mix(in_srgb,var(--fl-gold)_16%,transparent),transparent)]"
                        aria-hidden="true"
                    />

                    <!-- Tag -->
                    <div
                        class="demo-tag absolute top-[14%] left-[8%] w-[38%] sm:top-[8%] sm:left-[12%] sm:w-[26%]"
                        aria-hidden="true"
                    >
                        <TagVisualizer
                            :name="tag.name"
                            :subtitle="tag.subtitle"
                            :year="tag.year"
                            :accent="tag.accent"
                            :tilt="false"
                        />
                    </div>

                    <!-- NFC pulse at the contact point -->
                    <div
                        class="demo-pulse absolute top-[46%] left-[27%] sm:top-[44%] sm:left-[25%]"
                        aria-hidden="true"
                    >
                        <span
                            v-for="ring in 3"
                            :key="ring"
                            class="demo-ring"
                            :style="{ animationDelay: `${(ring - 1) * 260}ms` }"
                        />
                    </div>

                    <!-- Phone -->
                    <div
                        class="demo-phone absolute top-[22%] w-[46%] sm:top-[10%] sm:w-[30%]"
                        aria-hidden="true"
                    >
                        <div
                            class="relative aspect-[9/19] rounded-[1.6rem] border border-white/15 bg-fl-black p-[5%] shadow-fl-overlay"
                        >
                            <div
                                class="relative size-full overflow-hidden rounded-[1.15rem] bg-fl-graphite"
                            >
                                <!-- before: waiting screen -->
                                <div
                                    class="demo-screen-wait absolute inset-0 flex flex-col items-center justify-center gap-3 px-3 text-center"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        class="size-7 text-fl-gold"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                        stroke-linecap="round"
                                    >
                                        <path d="M8 8.5a5 5 0 0 1 0 7" />
                                        <path d="M11.5 6a8.5 8.5 0 0 1 0 12" />
                                        <path d="M15 3.5a12 12 0 0 1 0 17" />
                                    </svg>
                                    <span
                                        class="text-[10px] leading-tight text-white/60"
                                        >Acerca el teléfono al tag</span
                                    >
                                </div>
                                <!-- after: the story -->
                                <div
                                    class="demo-screen-story absolute inset-0 flex flex-col"
                                >
                                    <div
                                        class="relative h-[46%] overflow-hidden"
                                    >
                                        <img
                                            :src="photo.src"
                                            :srcset="photo.srcset"
                                            sizes="20vw"
                                            alt=""
                                            loading="lazy"
                                            decoding="async"
                                            class="size-full object-cover"
                                        />
                                        <span
                                            class="absolute top-2 left-2 rounded-full bg-fl-black/70 px-1.5 py-0.5 text-[7px] font-semibold tracking-[0.14em] text-fl-gold-soft uppercase"
                                            >Ejemplo</span
                                        >
                                    </div>
                                    <div
                                        class="flex flex-1 flex-col gap-1.5 p-2.5"
                                    >
                                        <span
                                            class="text-[7px] font-semibold tracking-[0.18em] text-fl-gold uppercase"
                                            >Mi Legacy</span
                                        >
                                        <span
                                            class="font-serif text-[13px] leading-tight text-white"
                                            >Tu nombre</span
                                        >
                                        <span class="text-[8px] text-white/55"
                                            >Tu carrera · Tu ciudad</span
                                        >
                                        <span
                                            class="mt-auto h-px bg-white/10"
                                        />
                                        <span
                                            class="flex justify-between text-[8px] text-white/70"
                                        >
                                            <span>Tiempo</span>
                                            <span
                                                class="legacy-numeric text-white"
                                                >— : — : —</span
                                            >
                                        </span>
                                        <span
                                            class="mt-1 rounded-full bg-white py-1 text-center text-[8px] font-semibold text-fl-black"
                                            >Abrir historia</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Process line -->
                    <ol
                        class="absolute inset-x-[6%] bottom-[5%] flex items-center justify-between gap-2 text-[10px] font-semibold tracking-[0.14em] uppercase sm:text-[11px]"
                        aria-hidden="true"
                    >
                        <template v-for="(node, index) in nodes" :key="node">
                            <li
                                class="demo-node flex items-center gap-2"
                                :class="{
                                    'is-on':
                                        step >= index + 1 ||
                                        (index === 0 && step >= 0),
                                }"
                            >
                                <span class="size-1.5 rounded-full" />
                                {{ node }}
                            </li>
                            <li
                                v-if="index < nodes.length - 1"
                                class="demo-link h-px flex-1"
                                :class="{ 'is-on': step >= index + 2 }"
                            />
                        </template>
                    </ol>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.demo-phone {
    left: 58%;
    transform: translateX(18%) rotate(8deg);
    transition:
        transform 1100ms cubic-bezier(0.16, 1, 0.3, 1),
        left 1100ms cubic-bezier(0.16, 1, 0.3, 1);
}
.demo[data-step='1'] .demo-phone,
.demo[data-step='2'] .demo-phone,
.demo[data-step='3'] .demo-phone {
    left: 40%;
    transform: translateX(0) rotate(-4deg);
}
@media (min-width: 640px) {
    .demo-phone {
        left: 62%;
    }
    .demo[data-step='1'] .demo-phone,
    .demo[data-step='2'] .demo-phone,
    .demo[data-step='3'] .demo-phone {
        left: 34%;
    }
}

.demo-tag {
    transition: transform 900ms cubic-bezier(0.16, 1, 0.3, 1);
    transform: rotate(-6deg);
}
.demo[data-step='2'] .demo-tag,
.demo[data-step='3'] .demo-tag {
    transform: rotate(-3deg) translateY(-4px);
}

.demo-screen-wait,
.demo-screen-story {
    transition: opacity 600ms ease;
}
.demo-screen-story {
    opacity: 0;
}
.demo[data-step='3'] .demo-screen-story {
    opacity: 1;
}
.demo[data-step='3'] .demo-screen-wait {
    opacity: 0;
}

.demo-ring {
    position: absolute;
    left: -2.25rem;
    top: -2.25rem;
    width: 4.5rem;
    height: 4.5rem;
    border-radius: 9999px;
    border: 1px solid var(--fl-gold);
    opacity: 0;
}
.demo[data-step='2'] .demo-ring {
    animation: demo-ring 1100ms ease-out both;
}
@keyframes demo-ring {
    0% {
        transform: scale(0.4);
        opacity: 0.9;
    }
    100% {
        transform: scale(1.7);
        opacity: 0;
    }
}

.demo-node {
    color: rgb(255 255 255 / 0.35);
    transition: color 500ms ease;
}
.demo-node span {
    background: rgb(255 255 255 / 0.25);
    transition: background-color 500ms ease;
}
.demo-node.is-on {
    color: var(--fl-gold-soft);
}
.demo-node.is-on span {
    background: var(--fl-gold);
}
.demo-link {
    background: rgb(255 255 255 / 0.15);
    transition: background-color 600ms ease;
}
.demo-link.is-on {
    background: var(--fl-gold);
}

@media (prefers-reduced-motion: reduce) {
    .demo-phone,
    .demo-tag,
    .demo-screen-wait,
    .demo-screen-story,
    .demo-node,
    .demo-node span,
    .demo-link {
        transition: none;
    }
    .demo-ring {
        animation: none !important;
    }
}
</style>
