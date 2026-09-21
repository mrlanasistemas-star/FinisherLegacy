<script setup lang="ts">
/**
 * The mascot as a floating guide, not a static illustration: a corner
 * companion whose speech bubble tip changes on its own as the visitor
 * scrolls, tracking whichever `[data-mascot-tip="<id>"]` section is
 * currently most visible (a plain IntersectionObserver picking the
 * highest `intersectionRatio`, matched against the `tips` prop by id — no
 * extra dependency). Each page that wants a guide adds the
 * `data-mascot-tip` attribute to its own sections/components (attribute
 * fallthrough puts it on a component's root element automatically) and
 * mounts this once with its own `welcome` + `tips`.
 *
 * 2026-08-21 follow-up ("más interactivo, más grande, que se mueva, más
 * animoso, algo más que guíe"): bigger avatar (FinisherMascot 'guide' is
 * now h-24/h-28), a livelier squash-and-stretch idle loop instead of a
 * plain sine float, a hover scale, and — the actual "guide" part —
 * Siguiente/Anterior buttons in the bubble that don't just describe the
 * next section, they scroll to it (`goTo`), so this doubles as a
 * click-through tour on top of the ambient scroll-tracking. `tips` order
 * is the tour order.
 *
 * First-ever visit across the site auto-opens with `welcome`, then leaves
 * it be for ~2.6s before tip-tracking kicks in — long enough to actually
 * read the greeting before it's replaced. Every later page load starts
 * collapsed to just the avatar (localStorage `fl-mascot-guide-seen`) —
 * "no vuelve a insistir," per earlier feedback — but tip-tracking still
 * runs quietly in the background so whatever they open to is current.
 * Skips localStorage/DOM access outside `onMounted` so this stays SSR-safe
 * (`vite build --ssr` is configured for this project).
 */
import { ChevronLeft, ChevronRight, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import FinisherMascot from '@/components/public/FinisherMascot.vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

const { welcome, tips } = defineProps<{
    welcome: string;
    tips: { id: string; text: string }[];
}>();

const STORAGE_KEY = 'fl-mascot-guide-seen';
const SCROLL_OFFSET = 88; // clears the sticky navbar + a little breathing room

const prefersReducedMotion = useReducedMotion();
const open = ref(false);
const activeIndex = ref<number | null>(null);
const pulseKey = ref(0);
let observer: IntersectionObserver | undefined;
const visibility = new Map<string, number>();

const currentTip = computed(() =>
    activeIndex.value === null
        ? welcome
        : (tips[activeIndex.value]?.text ?? welcome),
);

function applyBestTip() {
    let bestId: string | null = null;
    let bestRatio = 0;

    visibility.forEach((ratio, id) => {
        if (ratio > bestRatio) {
            bestRatio = ratio;
            bestId = id;
        }
    });

    if (!bestId || bestRatio < 0.15) {
        return;
    }

    const index = tips.findIndex((tip) => tip.id === bestId);

    if (index !== -1 && index !== activeIndex.value) {
        activeIndex.value = index;
        pulseKey.value++;
    }
}

function startObserving() {
    // Scroll-tracking is an enhancement — without it the mascot just stays
    // on `welcome`/whatever tip goTo() last set, still fully usable via
    // its Siguiente/Anterior buttons (brief item C8).
    if (typeof IntersectionObserver === 'undefined') {
        return;
    }

    const els = document.querySelectorAll<HTMLElement>('[data-mascot-tip]');

    if (els.length === 0) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                const id = entry.target.getAttribute('data-mascot-tip');

                if (id) {
                    visibility.set(id, entry.intersectionRatio);
                }
            });
            applyBestTip();
        },
        { threshold: [0, 0.15, 0.3, 0.5, 0.75, 1] },
    );

    els.forEach((el) => observer!.observe(el));
}

function persistSeen() {
    try {
        localStorage.setItem(STORAGE_KEY, '1');
    } catch {
        // storage unavailable (private mode, etc.) — just won't persist
    }
}

function toggleOpen() {
    open.value = !open.value;
    pulseKey.value++;
    persistSeen();
}

function close() {
    open.value = false;
    persistSeen();
}

/** Actually guides — jumps the page to the section for that tip, not just
 * the text describing it. */
function goTo(index: number) {
    if (index < 0 || index >= tips.length) {
        return;
    }

    activeIndex.value = index;
    pulseKey.value++;
    open.value = true;
    persistSeen();

    const el = document.querySelector<HTMLElement>(
        `[data-mascot-tip="${tips[index].id}"]`,
    );

    if (el) {
        const top =
            el.getBoundingClientRect().top + window.scrollY - SCROLL_OFFSET;
        window.scrollTo({
            top,
            behavior: prefersReducedMotion.value ? 'auto' : 'smooth',
        });
    }
}

function next() {
    goTo((activeIndex.value ?? -1) + 1);
}

function prev() {
    if (activeIndex.value === null) {
        return;
    }

    goTo(activeIndex.value - 1);
}

onMounted(() => {
    let seen = true;

    try {
        seen = localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        seen = true;
    }

    if (!seen) {
        open.value = true;
        persistSeen();
        window.setTimeout(startObserving, 2600);
    } else {
        startObserving();
    }
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div
        class="fixed bottom-4 left-4 z-40 flex items-end gap-3 sm:bottom-6 sm:left-6"
    >
        <button
            type="button"
            class="fl-focus-glow fl-mascot-hover shrink-0 cursor-pointer rounded-full border-none bg-transparent p-0"
            :aria-label="open ? 'Cerrar guía Finisher' : 'Abrir guía Finisher'"
            @click="toggleOpen"
        >
            <span class="fl-mascot-float block">
                <span
                    :key="pulseKey"
                    class="block"
                    :class="{ 'fl-mascot-spin': !prefersReducedMotion }"
                >
                    <FinisherMascot variant="guide" alt="" />
                </span>
            </span>
        </button>

        <Transition name="fl-mascot-bubble">
            <div
                v-if="open"
                class="relative mb-2 w-[16.5rem] rounded-2xl border border-fl-gold/25 bg-fl-graphite/95 px-4 py-3 pr-7 text-sm leading-relaxed text-white/85 shadow-[0_20px_50px_-15px_rgba(0,0,0,0.8)] backdrop-blur-sm sm:w-72"
            >
                <button
                    type="button"
                    class="fl-focus-glow absolute top-1.5 right-1.5 flex size-5 items-center justify-center rounded-full text-white/40 hover:text-white"
                    aria-label="Cerrar"
                    @click="close"
                >
                    <X class="size-3.5" />
                </button>
                <Transition name="fl-mascot-tip-fade" mode="out-in">
                    <p :key="currentTip">{{ currentTip }}</p>
                </Transition>

                <div
                    class="mt-3 flex items-center justify-between gap-2 border-t border-white/10 pt-2.5"
                >
                    <button
                        type="button"
                        class="fl-focus-glow flex items-center gap-0.5 rounded-full px-1.5 py-1 text-xs font-medium text-white/50 transition-colors enabled:hover:text-fl-gold-soft disabled:pointer-events-none disabled:opacity-30"
                        :disabled="activeIndex === null"
                        @click="prev"
                    >
                        <ChevronLeft class="size-3.5" />
                        Antes
                    </button>

                    <span
                        v-if="activeIndex !== null"
                        class="legacy-numeric text-[10px] font-semibold text-white/30"
                    >
                        {{ activeIndex + 1 }} / {{ tips.length }}
                    </span>
                    <span
                        v-else
                        class="text-[10px] font-semibold tracking-wide text-fl-gold-soft/70 uppercase"
                    >
                        Guía
                    </span>

                    <button
                        type="button"
                        class="fl-focus-glow flex items-center gap-0.5 rounded-full px-1.5 py-1 text-xs font-medium text-white/50 transition-colors enabled:hover:text-fl-gold-soft disabled:pointer-events-none disabled:opacity-30"
                        :disabled="activeIndex === tips.length - 1"
                        @click="next"
                    >
                        Sigue
                        <ChevronRight class="size-3.5" />
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.fl-mascot-hover {
    transition: transform 250ms cubic-bezier(0.16, 1, 0.3, 1);
}
.fl-mascot-hover:hover {
    transform: scale(1.12);
}
.fl-mascot-hover:active {
    transform: scale(0.96);
}

/* A livelier squash-and-stretch idle loop, not a plain side-to-side
   sine float — this is meant to read as "alive," per feedback. */
.fl-mascot-float {
    animation: fl-mascot-idle-float 3.2s ease-in-out infinite;
}
@keyframes fl-mascot-idle-float {
    0%,
    100% {
        transform: translateY(0) rotate(-3deg) scale(1);
    }
    25% {
        transform: translateY(-11px) rotate(3deg) scale(1.04, 0.97);
    }
    50% {
        transform: translateY(-3px) rotate(-2deg) scale(0.97, 1.03);
    }
    75% {
        transform: translateY(-14px) rotate(4deg) scale(1.05, 0.96);
    }
}

/* Spins once whenever the tip changes or the bubble is toggled — a
   separate inner layer so it doesn't fight the idle float's transform. */
.fl-mascot-spin {
    animation: fl-mascot-spin 650ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
@keyframes fl-mascot-spin {
    from {
        transform: rotate(0deg) scale(1);
    }
    50% {
        transform: rotate(190deg) scale(1.18);
    }
    to {
        transform: rotate(360deg) scale(1);
    }
}

.fl-mascot-bubble-enter-active,
.fl-mascot-bubble-leave-active {
    transition:
        opacity 220ms ease,
        transform 220ms ease;
}
.fl-mascot-bubble-enter-from,
.fl-mascot-bubble-leave-to {
    opacity: 0;
    transform: translateY(8px) scale(0.96);
}

.fl-mascot-tip-fade-enter-active,
.fl-mascot-tip-fade-leave-active {
    transition:
        opacity 200ms ease,
        transform 200ms ease;
}
.fl-mascot-tip-fade-enter-from {
    opacity: 0;
    transform: translateY(4px);
}
.fl-mascot-tip-fade-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

@media (prefers-reduced-motion: reduce) {
    .fl-mascot-hover,
    .fl-mascot-float,
    .fl-mascot-spin {
        animation: none;
        transition: none;
    }
    .fl-mascot-bubble-enter-active,
    .fl-mascot-bubble-leave-active,
    .fl-mascot-tip-fade-enter-active,
    .fl-mascot-tip-fade-leave-active {
        transition: none;
    }
}
</style>
