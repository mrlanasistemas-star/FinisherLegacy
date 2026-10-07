<script setup lang="ts">
/**
 * "Cómo se sujeta" — the four physical steps of the money clip on the
 * back of the Legacy Plate, drawn from resources/js/lib/plate-art.ts
 * (side profile, depth exaggerated, with the real ≈6 mm labelled).
 * Steps cycle on their own unless the visitor picks one or prefers
 * reduced motion.
 */
import { computed, onBeforeUnmount, onMounted, ref, useId } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { plateOnRibbonSvg, plateProfileSvg } from '@/lib/plate-art';
import type { PlateSpec } from '@/lib/plate-art';

const props = withDefaults(
    defineProps<{
        spec?: Partial<PlateSpec>;
        /** Compact = no heading, used inside dialogs. */
        compact?: boolean;
        autoplay?: boolean;
    }>(),
    { spec: () => ({}), compact: false, autoplay: true },
);

const uid = useId().replace(/[^a-zA-Z0-9-]/g, '');

const steps = computed(() => [
    {
        n: 1,
        title: 'Clip listo',
        text: 'La placa llega con el clip de acero inoxidable instalado en el reverso.',
        svg: plateProfileSvg(`${uid}a`, 'none', props.spec),
    },
    {
        n: 2,
        title: 'Inserta el listón',
        text: 'Desliza el listón de tu medalla entre la placa y el clip, por el extremo abierto.',
        svg: plateProfileSvg(`${uid}b`, 'inserting', props.spec),
    },
    {
        n: 3,
        title: 'El clip aplica presión',
        text: 'Sus ondulaciones presionan el listón en tres puntos. Sin mecanismos ni piezas sueltas.',
        svg: plateProfileSvg(`${uid}c`, 'held', props.spec),
    },
    {
        n: 4,
        title: 'Sujeción firme y segura',
        text: 'La Legacy Plate queda fija y visible sobre el listón, lista para acercar el teléfono.',
        svg: plateOnRibbonSvg(`${uid}d`, {}, props.spec),
    },
]);

const active = ref(0);
const reduced = useReducedMotion();
let timer: number | null = null;

function stop() {
    if (timer !== null) {
        window.clearInterval(timer);
    }

    timer = null;
}

function pick(index: number) {
    stop();
    active.value = index;
}

onMounted(() => {
    if (props.autoplay && !reduced.value) {
        timer = window.setInterval(() => {
            active.value = (active.value + 1) % 4;
        }, 3200);
    }
});

onBeforeUnmount(stop);
</script>

<template>
    <div class="grid grid-cols-1 items-center gap-8 lg:grid-cols-12">
        <div
            class="relative flex aspect-[4/3] items-center justify-center overflow-hidden rounded-[22px] border border-border bg-gradient-to-b from-card to-fl-cream/60 p-5 sm:p-8"
            :class="compact ? 'lg:col-span-12' : 'lg:col-span-7'"
        >
            <template v-for="(step, i) in steps" :key="step.n">
                <div
                    class="absolute inset-0 flex items-center justify-center p-6 transition-[opacity,transform] duration-500 ease-out motion-reduce:transition-none sm:p-10 [&_svg]:max-h-full [&_svg]:w-full"
                    :class="
                        active === i
                            ? 'translate-y-0 opacity-100'
                            : 'pointer-events-none translate-y-2 opacity-0'
                    "
                    :aria-hidden="active !== i"
                    v-html="step.svg"
                />
            </template>
            <span
                class="absolute top-4 left-4 rounded-full bg-foreground px-3 py-1 text-[11px] font-semibold tracking-wider text-background uppercase"
                aria-live="polite"
            >
                Paso {{ active + 1 }} de 4
            </span>
        </div>

        <ol
            class="grid grid-cols-1 gap-2 sm:grid-cols-2"
            :class="compact ? 'lg:col-span-12' : 'lg:col-span-5 lg:grid-cols-1'"
        >
            <li v-for="(step, i) in steps" :key="step.n">
                <button
                    type="button"
                    class="flex w-full items-start gap-4 rounded-2xl border p-4 text-left transition-colors focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                    :class="
                        active === i
                            ? 'border-foreground/20 bg-card shadow-sm'
                            : 'border-transparent hover:bg-card/70'
                    "
                    :aria-pressed="active === i"
                    @click="pick(i)"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-full font-serif text-base transition-colors"
                        :class="
                            active === i
                                ? 'bg-fl-gold text-fl-black'
                                : 'border border-fl-gold/50 text-fl-gold-ink'
                        "
                        >{{ step.n }}</span
                    >
                    <span>
                        <span class="block font-semibold">{{
                            step.title
                        }}</span>
                        <span
                            class="mt-1 block text-sm leading-relaxed text-muted-foreground"
                            >{{ step.text }}</span
                        >
                    </span>
                </button>
            </li>
        </ol>
    </div>
</template>
