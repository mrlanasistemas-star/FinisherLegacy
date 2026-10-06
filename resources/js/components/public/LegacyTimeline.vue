<script setup lang="ts">
/**
 * "Tu Legacy crece contigo" — a demo timeline (explicitly labeled "vista
 * previa", brand system §51: never present example data as real). The
 * Legacy Line fills progressively and each entry fades/lights up as it's
 * scrolled into view, instead of appearing all at once.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

const entries = [
    {
        year: '2026',
        event: 'Maratón CDMX',
        distance: '42.195 KM',
        time: '03:48:17',
    },
    {
        year: '2027',
        event: 'Medio Maratón Guadalajara',
        distance: '21 KM',
        time: '01:42:03',
    },
    {
        year: '2027',
        event: '10K Nocturna Monterrey',
        distance: '10 KM',
        time: '00:44:18',
    },
];

const prefersReducedMotion = useReducedMotion();
const lit = ref<boolean[]>(entries.map(() => prefersReducedMotion.value));
const dotEls: (HTMLElement | null)[] = [];
let observer: IntersectionObserver | undefined;

function setDotRef(el: Element | null, index: number) {
    dotEls[index] = el as HTMLElement | null;
}

onMounted(() => {
    if (prefersReducedMotion.value) {
        return;
    }

    observer = new IntersectionObserver(
        (observedEntries) => {
            observedEntries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                const index = dotEls.findIndex((el) => el === entry.target);

                if (index !== -1) {
                    lit.value[index] = true;
                }
            });
        },
        { threshold: 0.6 },
    );
    dotEls.forEach((el) => el && observer?.observe(el));
});

onBeforeUnmount(() => observer?.disconnect());

const litCount = () => lit.value.filter(Boolean).length;
</script>

<template>
    <div class="relative">
        <span
            class="absolute -top-8 left-10 text-[10px] font-semibold tracking-[0.2em] text-muted-foreground/80 uppercase sm:left-12"
        >
            Vista previa
        </span>

        <div
            class="absolute top-2 bottom-2 left-[15px] w-px bg-foreground/5 sm:left-[19px]"
            aria-hidden="true"
        >
            <div
                class="w-full origin-top bg-gradient-to-b from-fl-gold via-fl-gold-soft to-transparent transition-all duration-500 ease-out"
                :style="{
                    height: `${(litCount() / entries.length) * 100}%`,
                }"
            />
        </div>

        <div class="flex flex-col gap-10">
            <div
                v-for="(entry, index) in entries"
                :key="entry.event"
                class="relative flex items-start gap-5 pl-10 transition-all duration-500 sm:gap-6 sm:pl-12"
                :class="lit[index] ? 'opacity-100' : 'opacity-30'"
                :style="{ transitionDelay: `${index * 60}ms` }"
            >
                <span
                    :ref="(el) => setDotRef(el as Element | null, index)"
                    class="absolute top-1 left-0 flex size-[31px] items-center justify-center sm:size-[39px]"
                >
                    <span
                        class="size-3 rounded-full border-2 transition-colors duration-500"
                        :class="
                            lit[index]
                                ? 'border-fl-gold bg-fl-gold-soft shadow-[0_0_16px_-2px_rgba(224,202,137,0.8)]'
                                : 'border-foreground/15 bg-background'
                        "
                    />
                </span>

                <div>
                    <span
                        class="legacy-numeric text-xs font-semibold text-fl-gold-ink"
                    >
                        {{ entry.year }}
                    </span>
                    <h3 class="mt-1 text-lg font-semibold text-foreground">
                        {{ entry.event }}
                    </h3>
                    <p
                        class="legacy-numeric mt-1 flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground"
                    >
                        <span>{{ entry.distance }}</span>
                        <span aria-hidden="true">·</span>
                        <span class="text-foreground">{{ entry.time }}</span>
                    </p>
                </div>
            </div>

            <div
                class="relative flex items-center gap-5 pl-10 sm:gap-6 sm:pl-12"
            >
                <span
                    class="absolute top-0 left-0 flex size-[31px] items-center justify-center sm:size-[39px]"
                >
                    <span
                        class="size-3 rounded-full border-2 border-dashed border-foreground/15"
                    />
                </span>
                <p class="text-sm text-muted-foreground/80 italic">
                    Tu próxima carrera aparece aquí.
                </p>
            </div>
        </div>
    </div>
</template>
