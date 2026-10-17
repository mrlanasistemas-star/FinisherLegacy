<script setup lang="ts">
/**
 * "Cada logro cuenta." — the athlete's participations as a dated timeline
 * (real event results; nothing sampled). Shared by Mi Legado and the public
 * profile; each entry can link to its detail page.
 *
 * The Legacy Line is the brand differentiator: a gold rail that fills
 * progressively (transform: scaleY — no layout thrash) as entries scroll into
 * view, with each dot lighting up when its row is seen. Driven entirely by the
 * real `entries` prop. IntersectionObserver-based, transform/opacity only, and
 * fully reduced-motion aware (everything renders lit + at rest, no motion). A
 * bounded fallback lights every row even if the observer never fires, so the
 * timeline is never left dim (content-safety parity with motion/Reveal.vue).
 */
import { Link } from '@inertiajs/vue3';
import { Award, Camera, ChevronRight, Lock } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { shortDate } from '@/lib/datetime';

export type TimelineEntry = {
    key: string | number;
    date: string | null;
    title: string;
    subtitle?: string | null;
    distance?: string | null;
    time?: string | null;
    pace?: string | null;
    position?: number | null;
    imageUrl?: string | null;
    href?: string | null;
    hasLegacyPlate?: boolean;
    photoCount?: number;
    privateNote?: string | null;
};

const props = defineProps<{ entries: TimelineEntry[] }>();

const prefersReducedMotion = useReducedMotion();
// Content-safety parity with motion/Reveal.vue: start VISIBLE (lit) so the
// timeline renders complete under SSR / before hydration / if JS never runs.
// We only switch to the "hidden, reveal-on-scroll" state once mounted confirms
// JS is alive AND motion is allowed. `mounted` stays false under reduced motion,
// leaving everything lit and the gold rail full.
const lit = ref<boolean[]>(props.entries.map(() => true));
const mounted = ref(false);
const dotEls: (HTMLElement | null)[] = [];
let observer: IntersectionObserver | undefined;
let fallbackTimer: ReturnType<typeof setTimeout> | null = null;

// Gold fill fraction for the rail (0→1), driven by how many dots are lit.
const fillFraction = computed(() => {
    if (!props.entries.length) {
        return 0;
    }

    const count = lit.value.filter(Boolean).length;

    return count / props.entries.length;
});

function setDotRef(el: Element | null, index: number) {
    dotEls[index] = el as HTMLElement | null;
}

function litAll() {
    lit.value = props.entries.map(() => true);
}

onMounted(() => {
    if (prefersReducedMotion.value) {
        return;
    }

    // JS is alive and motion is allowed: switch to the reveal-on-scroll state
    // (hide rows, empty the rail) now, so there is no server/pre-hydration dim.
    mounted.value = true;
    lit.value = props.entries.map(() => false);

    observer = new IntersectionObserver(
        (observed) => {
            observed.forEach((entry) => {
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

    // Never leave rows dim if the observer never fires for any reason.
    fallbackTimer = setTimeout(litAll, 2500);
});

onBeforeUnmount(() => {
    observer?.disconnect();

    if (fallbackTimer !== null) {
        clearTimeout(fallbackTimer);
    }
});
</script>

<template>
    <ol class="relative">
        <!-- Legacy Line: faint track + progressive gold fill (scaleY). -->
        <span
            class="absolute top-2 bottom-2 left-[15px] w-px bg-foreground/10 sm:left-[19px]"
            aria-hidden="true"
        >
            <span
                class="block h-full w-full origin-top bg-gradient-to-b from-fl-gold via-fl-gold-soft to-fl-gold-soft/30 transition-transform duration-500 ease-out motion-reduce:transition-none"
                :style="{ transform: `scaleY(${fillFraction})` }"
            />
        </span>

        <li
            v-for="(entry, index) in entries"
            :key="entry.key"
            class="relative pb-10 pl-10 transition-[opacity,transform] duration-500 ease-out last:pb-0 motion-reduce:transition-none sm:pl-12"
            :class="
                !mounted || lit[index]
                    ? 'translate-y-0 opacity-100'
                    : 'translate-y-2 opacity-30'
            "
            :style="{ transitionDelay: `${index * 60}ms` }"
        >
            <!-- Dot node, observed to light its row + advance the gold fill. -->
            <span
                :ref="(el) => setDotRef(el as Element | null, index)"
                class="absolute top-1 left-0 flex size-[31px] items-center justify-center sm:size-[39px]"
                aria-hidden="true"
            >
                <span
                    class="size-3 rounded-full border-2 transition-colors duration-500 motion-reduce:transition-none"
                    :class="
                        !mounted || lit[index]
                            ? 'border-fl-gold bg-fl-gold-soft shadow-[0_0_16px_-2px_rgb(224_202_137/0.85)]'
                            : 'border-foreground/15 bg-background'
                    "
                />
            </span>

            <component
                :is="entry.href ? Link : 'div'"
                :href="entry.href ?? undefined"
                class="group fl-card flex gap-4 p-4 transition-colors sm:p-5"
                :class="entry.href ? 'hover:border-foreground/20' : ''"
            >
                <img
                    v-if="entry.imageUrl"
                    :src="entry.imageUrl"
                    :alt="entry.title"
                    loading="lazy"
                    decoding="async"
                    class="size-16 shrink-0 rounded-media object-cover sm:size-20"
                />
                <div class="min-w-0 flex-1">
                    <p
                        v-if="entry.date"
                        class="legacy-numeric text-xs font-semibold text-fl-gold-ink"
                    >
                        {{ shortDate(entry.date) }}
                    </p>
                    <h3
                        class="mt-0.5 text-base font-semibold text-foreground sm:text-lg"
                    >
                        {{ entry.title }}
                    </h3>
                    <p
                        v-if="entry.subtitle || entry.distance"
                        class="mt-0.5 text-sm text-muted-foreground"
                    >
                        {{
                            [entry.subtitle, entry.distance]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </p>
                    <div
                        class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm"
                    >
                        <span v-if="entry.time" class="legacy-numeric">
                            <span class="text-muted-foreground">Tiempo </span>
                            <span class="font-semibold">{{ entry.time }}</span>
                        </span>
                        <span v-if="entry.pace" class="legacy-numeric">
                            <span class="text-muted-foreground">Ritmo </span>
                            <span class="font-semibold">{{ entry.pace }}</span>
                        </span>
                        <span v-if="entry.position" class="legacy-numeric">
                            <span class="text-muted-foreground">Posición </span>
                            <span class="font-semibold"
                                >#{{ entry.position }}</span
                            >
                        </span>
                        <span
                            v-if="entry.hasLegacyPlate"
                            class="inline-flex items-center gap-1 rounded-full bg-fl-cream px-2 py-0.5 text-xs font-medium text-fl-gold-ink"
                        >
                            <Award class="size-3" /> Legacy Plate
                        </span>
                        <span
                            v-if="entry.photoCount"
                            class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Camera class="size-3" /> {{ entry.photoCount }}
                        </span>
                        <span
                            v-if="entry.privateNote"
                            class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Lock class="size-3" /> {{ entry.privateNote }}
                        </span>
                    </div>
                </div>
                <ChevronRight
                    v-if="entry.href"
                    class="mt-1 size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 motion-reduce:transition-none"
                    aria-hidden="true"
                />
            </component>
        </li>
    </ol>
</template>
