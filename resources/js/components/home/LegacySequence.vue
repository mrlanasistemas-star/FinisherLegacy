<script setup lang="ts">
/**
 * Bloque G — "Del logro físico al Legacy." Six moments on one gold line
 * (horizontal ≥lg, vertical on phones). When the line scrolls into view
 * it fills step by step, once. Reduced motion: drawn complete.
 */
import { onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { useInView } from '@/composables/useInView';

const steps = [
    { title: 'Compites', body: 'Entrenas, llegas a la salida y lo das todo.' },
    {
        title: 'Consigues tu tag',
        body: 'El de la colección de tu carrera. Te lo llevas al momento.',
    },
    {
        title: 'Lo activas',
        body: 'Acercas el teléfono o escribes su código. Ahora es tuyo.',
    },
    {
        title: 'Agregas tu historia',
        body: 'Foto, tiempo, lugar, una dedicatoria, un audio.',
    },
    {
        title: 'La compartes',
        body: 'Con quien estuvo ahí, con un enlace, el NFC o el QR.',
    },
    {
        title: 'Regresas a ella',
        body: 'Cada vez que lo tocas, vuelves a ese día.',
    },
];

const root = useTemplateRef<HTMLElement>('root');
const { inView, reducedMotion } = useInView(root, { threshold: 0.3 });
const reached = ref(-1);
let timer: ReturnType<typeof setInterval> | null = null;

watch(
    inView,
    (visible) => {
        if (!visible) {
            return;
        }

        if (reducedMotion.value) {
            reached.value = steps.length - 1;

            return;
        }

        timer = setInterval(() => {
            reached.value++;

            if (reached.value >= steps.length - 1 && timer) {
                clearInterval(timer);
                timer = null;
            }
        }, 380);
    },
    { immediate: true },
);

onBeforeUnmount(() => timer && clearInterval(timer));
</script>

<template>
    <section
        class="relative overflow-hidden bg-fl-graphite py-20 text-white sm:py-28 lg:py-36"
        aria-labelledby="sequence-title"
    >
        <div class="fl-container">
            <div class="max-w-2xl">
                <p
                    class="flex items-center gap-3 text-[11px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
                >
                    <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                    El recorrido
                </p>
                <h2
                    id="sequence-title"
                    class="mt-6 font-serif text-[clamp(2.25rem,5vw,4.25rem)] leading-[1.04] font-medium tracking-[-0.02em]"
                >
                    Del logro físico al Legacy.
                </h2>
            </div>

            <ol
                ref="root"
                class="seq relative mt-16 grid gap-0 lg:mt-20 lg:grid-cols-6 lg:gap-6"
            >
                <!-- the line (behind the dots) -->
                <span
                    class="absolute top-0 bottom-0 left-[5px] w-px bg-white/12 lg:top-[5px] lg:right-0 lg:bottom-auto lg:left-0 lg:h-px lg:w-auto"
                    aria-hidden="true"
                />
                <span
                    class="seq-fill absolute top-0 left-[5px] w-px bg-fl-gold lg:top-[5px] lg:left-0 lg:h-px"
                    :style="{
                        '--p': Math.max(0, reached) / (steps.length - 1),
                    }"
                    aria-hidden="true"
                />

                <li
                    v-for="(step, index) in steps"
                    :key="step.title"
                    class="seq-step relative pb-10 pl-10 last:pb-0 lg:pb-0 lg:pl-0"
                    :class="{ 'is-on': index <= reached }"
                >
                    <span
                        class="seq-dot absolute top-0 left-0 size-[11px] rounded-full border lg:static lg:block"
                        aria-hidden="true"
                    />
                    <span
                        class="legacy-numeric block text-xs text-white/40 lg:mt-7"
                        >0{{ index + 1 }}</span
                    >
                    <span
                        class="mt-2 block text-[13px] font-semibold tracking-[0.18em] uppercase"
                        >{{ step.title }}</span
                    >
                    <span
                        class="mt-3 block max-w-[17rem] text-[15px] leading-relaxed text-white/60"
                        >{{ step.body }}</span
                    >
                </li>
            </ol>
        </div>
    </section>
</template>

<style scoped>
.seq-fill {
    height: calc(var(--p, 0) * 100%);
    transition:
        height 380ms linear,
        width 380ms linear;
}
@media (min-width: 1024px) {
    .seq-fill {
        height: 1px;
        width: calc(var(--p, 0) * (100% - (100% - 7.5rem) / 6));
    }
}
.seq-dot {
    border-color: rgb(255 255 255 / 0.3);
    background: var(--fl-graphite);
    transition:
        background-color 300ms ease,
        border-color 300ms ease,
        box-shadow 300ms ease;
}
.seq-step {
    color: rgb(255 255 255 / 0.55);
    transition: color 400ms ease;
}
.seq-step.is-on {
    color: #fff;
}
.seq-step.is-on .seq-dot {
    border-color: var(--fl-gold);
    background: var(--fl-gold);
    box-shadow: 0 0 0 4px color-mix(in srgb, var(--fl-gold) 18%, transparent);
}
@media (prefers-reduced-motion: reduce) {
    .seq-fill,
    .seq-dot,
    .seq-step {
        transition: none;
    }
}
</style>
