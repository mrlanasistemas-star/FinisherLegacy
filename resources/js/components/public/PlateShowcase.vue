<script setup lang="ts">
/**
 * CSS-drawn Legacy Plate — the fallback used whenever real front/back
 * photography isn't available yet (see PlateMedia.vue / public/media/home/
 * plate/README.md). This is NOT a credit card: it's a slim engraved metal
 * bar with two lateral attachment loops that clip onto a medal ribbon,
 * which is exactly what a real finisher plate is. Geometry choices that
 * matter here, on purpose:
 *  - aspect-[3/1]: a short wide bar, nowhere near a card's ~1.6:1 ratio.
 *  - rounded-[8px]: a machined edge, not a pill/app-icon corner.
 *  - lateral loops (the two rings either side) + ribbon glimpses above/
 *    below: the thing a visitor is missing without them is "how does this
 *    attach to my medal", which is the #1 misread risk for this product.
 */
import { Nfc } from '@lucide/vue';
import { ref, useTemplateRef } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

withDefaults(
    defineProps<{
        eventName?: string;
        athleteName?: string;
        time?: string;
        serial?: string;
        /** Off inside compact/decorative placements (e.g. the Hero) where
         * hover affordances would just be noise. */
        showHotspots?: boolean;
        showCaption?: boolean;
        size?: 'sm' | 'md';
    }>(),
    {
        eventName: 'TU EVENTO AQUÍ',
        athleteName: 'TU NOMBRE',
        time: '00:00:00',
        serial: '#FL-0000000',
        showHotspots: true,
        showCaption: true,
        size: 'md',
    },
);

// Tilt-on-hover + a cursor-follow label, standing in for the light-catching
// reflection a real brushed-steel plate has. Desktop only, off under
// prefers-reduced-motion.
const prefersReducedMotion = useReducedMotion();
const plateEl = useTemplateRef<HTMLElement>('plate');
const hovering = ref(false);
const cursorX = ref(0);
const cursorY = ref(0);
const showingBack = ref(false);
const activeHotspot = ref<string | null>(null);

function toggleHotspot(label: string) {
    activeHotspot.value = activeHotspot.value === label ? null : label;
}

function handlePointerMove(event: PointerEvent) {
    if (prefersReducedMotion.value || !plateEl.value) {
        return;
    }

    const rect = plateEl.value.getBoundingClientRect();
    const relX = (event.clientX - rect.left) / rect.width;
    const relY = (event.clientY - rect.top) / rect.height;

    plateEl.value.style.setProperty('--tilt-x', `${(relY - 0.5) * -5}deg`);
    plateEl.value.style.setProperty('--tilt-y', `${(relX - 0.5) * 5}deg`);
    plateEl.value.style.setProperty('--glare-x', `${relX * 100}%`);
    plateEl.value.style.setProperty('--glare-y', `${relY * 100}%`);
    cursorX.value = event.clientX - rect.left;
    cursorY.value = event.clientY - rect.top;
}

function resetTilt() {
    plateEl.value?.style.setProperty('--tilt-x', '0deg');
    plateEl.value?.style.setProperty('--tilt-y', '0deg');
    hovering.value = false;
}

function resetHotspots() {
    activeHotspot.value = null;
}

const hotspots = [
    { label: 'Nombre', top: '38%', left: '15%' },
    { label: 'Tiempo', top: '66%', left: '32%' },
    { label: 'Legacy Code', top: '50%', left: '90%' },
    { label: 'Sistema de sujeción', top: '50%', left: '1%' },
];
</script>

<template>
    <div
        class="mx-auto w-full"
        :class="size === 'sm' ? 'max-w-[240px]' : 'max-w-md'"
    >
        <div style="perspective: 1000px">
            <!-- Ribbon glimpse — the plate visibly continues onto a ribbon
                 above it, so it never reads as a free-floating card. -->
            <div
                aria-hidden="true"
                class="relative mx-auto h-8 w-2.5 sm:h-10"
                style="
                    mask-image: linear-gradient(to bottom, transparent, black);
                    background: linear-gradient(
                        180deg,
                        var(--fl-gold-dim),
                        var(--fl-gold) 55%,
                        var(--fl-gold-soft)
                    );
                "
            >
                <div
                    class="absolute inset-0 opacity-30"
                    style="
                        background-image: repeating-linear-gradient(
                            115deg,
                            rgba(0, 0, 0, 0.35) 0px,
                            rgba(0, 0, 0, 0.35) 1px,
                            transparent 1px,
                            transparent 3px
                        );
                    "
                />
            </div>

            <div
                ref="plate"
                class="relative w-full cursor-none"
                style="
                    --tilt-x: 0deg;
                    --tilt-y: 0deg;
                    --glare-x: 50%;
                    --glare-y: 30%;
                    transform: rotateX(var(--tilt-x)) rotateY(var(--tilt-y));
                    transition: transform 300ms ease-out;
                "
                @pointermove="handlePointerMove"
                @pointerenter="hovering = true"
                @pointerleave="resetTilt"
                @click="resetHotspots"
            >
                <!-- Lateral attachment loops — the sujeción hotspot points
                     directly at the left one. Real hardware: a metal ring
                     the ribbon threads through, not a decoration. -->
                <span
                    aria-hidden="true"
                    class="fl-loop-pulse absolute top-1/2 -left-3 z-10 flex size-7 -translate-y-1/2 items-center justify-center sm:-left-3.5 sm:size-8"
                >
                    <span
                        class="absolute inset-0 rounded-full border-2 border-foreground/15"
                        style="
                            background: linear-gradient(
                                155deg,
                                #fbfaf7 0%,
                                #d6d3cb 55%,
                                #aeaaa1 100%
                            );
                        "
                    />
                    <span
                        class="relative size-2.5 rounded-full bg-background ring-1 ring-black/20"
                    />
                </span>
                <span
                    aria-hidden="true"
                    class="fl-loop-pulse absolute top-1/2 -right-3 z-10 flex size-7 -translate-y-1/2 items-center justify-center sm:-right-3.5 sm:size-8"
                    style="animation-delay: 220ms"
                >
                    <span
                        class="absolute inset-0 rounded-full border-2 border-foreground/15"
                        style="
                            background: linear-gradient(
                                155deg,
                                #fbfaf7 0%,
                                #d6d3cb 55%,
                                #aeaaa1 100%
                            );
                        "
                    />
                    <span
                        class="relative size-2.5 rounded-full bg-background ring-1 ring-black/20"
                    />
                </span>

                <!-- The engraved metal bar itself -->
                <button
                    type="button"
                    class="fl-shine relative aspect-[3/1] w-full overflow-hidden rounded-[8px] border border-foreground/15"
                    style="
                        box-shadow:
                            0 22px 44px -22px rgba(23, 23, 20, 0.45),
                            inset 0 1px 0 rgba(255, 255, 255, 0.9),
                            inset 0 -1px 0 rgba(0, 0, 0, 0.18);
                        background:
                            repeating-linear-gradient(
                                100deg,
                                rgba(0, 0, 0, 0.035) 0px,
                                rgba(0, 0, 0, 0.035) 1px,
                                transparent 1px,
                                transparent 3px
                            ),
                            radial-gradient(
                                circle at var(--glare-x) var(--glare-y),
                                rgba(255, 255, 255, 0.7),
                                transparent 45%
                            ),
                            linear-gradient(
                                160deg,
                                #f6f5f1 0%,
                                #dedbd4 55%,
                                #bdb9b0 100%
                            );
                    "
                    :aria-label="
                        showingBack
                            ? 'Ver anverso de la placa'
                            : 'Ver reverso de la placa'
                    "
                    @click="showingBack = !showingBack"
                >
                    <span
                        class="pointer-events-none absolute inset-0 bg-gradient-to-br from-white/40 via-transparent to-transparent"
                    />
                    <span
                        class="pointer-events-none absolute inset-[3px] rounded-[5px] border border-foreground/10"
                    />

                    <!-- Front: engraved athlete data -->
                    <span
                        class="absolute inset-0 flex items-center justify-between gap-3 px-4 transition-opacity duration-500 sm:px-6"
                        :class="
                            showingBack
                                ? 'pointer-events-none opacity-0'
                                : 'opacity-100'
                        "
                    >
                        <span class="min-w-0 text-left">
                            <span
                                class="block text-[8px] font-semibold tracking-[0.32em] text-fl-gold-ink uppercase sm:text-[9px]"
                            >
                                Finisher · Legacy
                            </span>
                            <span
                                class="mt-1 block truncate text-sm leading-tight font-bold tracking-tight text-foreground uppercase sm:text-base"
                            >
                                {{ eventName }}
                            </span>
                            <span
                                class="mt-1 flex items-baseline gap-2 text-xs text-muted-foreground"
                            >
                                <span class="truncate">{{ athleteName }}</span>
                                <span
                                    aria-hidden="true"
                                    class="text-muted-foreground/80"
                                    >·</span
                                >
                                <span
                                    class="legacy-numeric shrink-0 text-sm font-semibold text-fl-gold-ink"
                                >
                                    {{ time }}
                                </span>
                            </span>
                        </span>

                        <span class="flex shrink-0 flex-col items-center gap-1">
                            <span
                                class="flex size-8 items-center justify-center rounded-[4px] border border-foreground/15 bg-white/50 sm:size-9"
                            >
                                <Nfc
                                    class="size-4 text-fl-gold-ink sm:size-5"
                                />
                            </span>
                            <span
                                class="legacy-numeric text-[7px] tracking-wide text-muted-foreground/80 sm:text-[8px]"
                                >{{ serial }}</span
                            >
                        </span>
                    </span>

                    <!-- Back: minimal reverse engraving -->
                    <span
                        class="absolute inset-0 flex items-center justify-center gap-3 px-4 transition-opacity duration-500"
                        :class="
                            showingBack
                                ? 'opacity-100'
                                : 'pointer-events-none opacity-0'
                        "
                    >
                        <span
                            class="text-lg font-black tracking-[0.2em] text-fl-gold-ink"
                            >FL</span
                        >
                        <span
                            class="h-6 w-px bg-foreground/[0.07]"
                            aria-hidden="true"
                        />
                        <span
                            class="legacy-numeric text-[9px] tracking-[0.18em] text-muted-foreground/80 uppercase sm:text-[10px]"
                        >
                            {{ serial }} · Acero inoxidable cepillado
                        </span>
                    </span>
                </button>

                <!-- Hotspots: hover (desktop) or tap (touch) to see what
                     each part means — a plain group-hover tooltip is
                     unreachable on touch, so tapping toggles it too. -->
                <template v-if="showHotspots && !showingBack">
                    <button
                        v-for="hotspot in hotspots"
                        :key="hotspot.label"
                        type="button"
                        class="group absolute z-20 flex size-6 -translate-x-1/2 -translate-y-1/2 cursor-pointer items-center justify-center"
                        :style="{ top: hotspot.top, left: hotspot.left }"
                        :aria-label="hotspot.label"
                        :aria-pressed="activeHotspot === hotspot.label"
                        @click.stop="toggleHotspot(hotspot.label)"
                    >
                        <span
                            class="flex size-4 items-center justify-center rounded-full border border-fl-gold-soft/50 bg-background/70 text-[10px] leading-none text-fl-gold-ink backdrop-blur-sm transition-transform duration-200 group-hover:scale-125"
                            :class="{
                                'scale-125': activeHotspot === hotspot.label,
                            }"
                            >+</span
                        >
                        <span
                            class="pointer-events-none absolute bottom-full left-1/2 mb-1.5 -translate-x-1/2 rounded-md border border-border bg-background px-2 py-1 text-[10px] font-medium tracking-wide whitespace-nowrap text-foreground opacity-0 transition-opacity duration-150 group-hover:opacity-100"
                            :class="{
                                'opacity-100': activeHotspot === hotspot.label,
                            }"
                        >
                            {{ hotspot.label }}
                        </span>
                    </button>
                </template>

                <!-- Cursor-follow label -->
                <span
                    v-if="hovering && !prefersReducedMotion"
                    class="pointer-events-none absolute z-30 rounded-full bg-fl-gold-soft px-2.5 py-1 text-[10px] font-bold tracking-wide text-fl-black uppercase"
                    :style="{
                        top: `${cursorY}px`,
                        left: `${cursorX}px`,
                        transform: 'translate(-50%, -150%)',
                    }"
                >
                    {{ showingBack ? 'Ver anverso' : 'Ver reverso' }}
                </span>
            </div>

            <!-- Ribbon continuing down toward the medal -->
            <div
                aria-hidden="true"
                class="relative mx-auto h-6 w-2.5 sm:h-8"
                style="
                    mask-image: linear-gradient(to top, transparent, black);
                    background: linear-gradient(
                        180deg,
                        var(--fl-gold-soft),
                        var(--fl-gold) 60%,
                        var(--fl-gold-dim)
                    );
                "
            >
                <div
                    class="absolute inset-0 opacity-30"
                    style="
                        background-image: repeating-linear-gradient(
                            115deg,
                            rgba(0, 0, 0, 0.35) 0px,
                            rgba(0, 0, 0, 0.35) 1px,
                            transparent 1px,
                            transparent 3px
                        );
                    "
                />
            </div>
        </div>

        <p
            v-if="showCaption"
            class="mt-3 text-center text-xs tracking-wide text-muted-foreground/80 uppercase"
        >
            Se sujeta al listón de tu medalla · toca para ver el reverso
        </p>
    </div>
</template>

<style scoped>
.fl-loop-pulse {
    animation: fl-loop-pulse 2.4s ease-out 1;
    animation-fill-mode: both;
}

@keyframes fl-loop-pulse {
    0% {
        opacity: 0;
        transform: translateY(-50%) scale(0.6);
    }
    60% {
        opacity: 1;
        transform: translateY(-50%) scale(1.08);
    }
    100% {
        opacity: 1;
        transform: translateY(-50%) scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-loop-pulse {
        animation: none;
    }
}
</style>
