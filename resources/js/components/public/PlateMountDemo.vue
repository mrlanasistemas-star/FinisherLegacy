<script setup lang="ts">
/**
 * Micro demonstration of montaje — not an industrial tutorial, a few
 * seconds of motion the first time this scrolls into view that answers
 * "how does this attach to my medal" almost without reading text. Plays
 * once (IntersectionObserver-gated, `played` never resets), reusing
 * PlateShowcase.vue itself so the geometry stays identical to the real
 * showcase below it.
 */
import { useIntersectionObserver } from '@vueuse/core';
import { onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import PlateShowcase from '@/components/public/PlateShowcase.vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

// Fail-open for real (brief item C1/C4): `.fl-mount-scene` is fully
// visible by default (SSR and no-JS render this way, permanently). Only
// once `armed` is set — inside onMounted, i.e. JS is actually running —
// does the CSS hide it in order to animate it back in on `played`. A
// bounded fallback timer still force-reveals it if the observer never
// fires while JS *is* running.
const REVEAL_FALLBACK_MS = 2500;

const prefersReducedMotion = useReducedMotion();
const rootEl = useTemplateRef<HTMLElement>('root');
const armed = ref(false);
const played = ref(prefersReducedMotion.value);
let fallbackTimer: ReturnType<typeof setTimeout> | null = null;

function play() {
    if (fallbackTimer !== null) {
        clearTimeout(fallbackTimer);
        fallbackTimer = null;
    }

    played.value = true;
}

useIntersectionObserver(
    rootEl,
    ([entry]) => {
        if (entry?.isIntersecting) {
            play();
        }
    },
    { threshold: 0.4 },
);

onMounted(() => {
    armed.value = true;

    if (!played.value) {
        fallbackTimer = setTimeout(play, REVEAL_FALLBACK_MS);
    }
});

onBeforeUnmount(() => {
    if (fallbackTimer !== null) {
        clearTimeout(fallbackTimer);
    }
});

const stages = ['Listón', 'Se sujeta', 'Medalla completa'];
</script>

<template>
    <div
        ref="root"
        class="mx-auto flex max-w-[200px] flex-col items-center gap-4"
    >
        <div
            class="fl-mount-scene"
            :class="{ 'fl-mount-armed': armed, 'fl-mount-play': played }"
        >
            <PlateShowcase
                size="sm"
                :show-hotspots="false"
                :show-caption="false"
            />
            <div
                aria-hidden="true"
                class="fl-mount-medal relative mx-auto -mt-1 flex size-14 items-center justify-center rounded-full border border-fl-gold/30"
                style="
                    background: radial-gradient(
                        circle at 35% 30%,
                        #3a3a3d,
                        #1a1a1c 70%
                    );
                "
            >
                <span
                    class="size-9 rounded-full border border-fl-gold-soft/25"
                />
            </div>
        </div>

        <div
            class="flex items-center gap-1.5 text-[9px] font-semibold tracking-[0.18em] uppercase"
        >
            <template v-for="(stage, index) in stages" :key="stage">
                <span
                    class="transition-colors duration-500"
                    :class="played ? 'text-fl-gold-soft' : 'text-white/30'"
                    :style="{ transitionDelay: `${index * 450 + 200}ms` }"
                >
                    {{ stage }}
                </span>
                <span
                    v-if="index < stages.length - 1"
                    class="text-white/15"
                    aria-hidden="true"
                    >·</span
                >
            </template>
        </div>
    </div>
</template>

<style scoped>
/* Visible and static by default — SSR and no-JS both render this rule
   only, permanently (brief item C1/C4). The hidden "about to animate in"
   state below only ever applies once JS has added .fl-mount-armed. */
.fl-mount-scene {
    opacity: 1;
    transform: none;
}

.fl-mount-scene.fl-mount-armed {
    opacity: 0;
    transform: translateX(-24px);
    transition:
        opacity 700ms cubic-bezier(0.16, 1, 0.3, 1),
        transform 700ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fl-mount-scene.fl-mount-armed.fl-mount-play {
    opacity: 1;
    transform: translateX(0);
}

.fl-mount-medal {
    opacity: 1;
    transform: none;
}
.fl-mount-armed .fl-mount-medal {
    opacity: 0;
    transform: translateY(-8px) scale(0.8);
}
.fl-mount-armed.fl-mount-play .fl-mount-medal {
    animation: fl-mount-medal-in 600ms cubic-bezier(0.16, 1, 0.3, 1) 650ms
        forwards;
}

@keyframes fl-mount-medal-in {
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-mount-scene.fl-mount-armed {
        opacity: 1;
        transform: none;
        transition: none;
    }
    .fl-mount-armed .fl-mount-medal {
        opacity: 1;
        transform: none;
    }
    .fl-mount-armed.fl-mount-play .fl-mount-medal {
        animation: none;
    }
}
</style>
