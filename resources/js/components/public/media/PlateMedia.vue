<script setup lang="ts">
/**
 * Defaults to PlateShowcase.vue's CSS-drawn plate (tilt + glare + demo
 * data) — always instant. Upgrades to real front/back photography only once
 * useAssetExists confirms the front photo is there, avoiding the same
 * flash-of-nothing bug LegacyScanMedia had. See
 * public/media/home/plate/README.md.
 */
import { ref, useTemplateRef } from 'vue';
import PlateShowcase from '@/components/public/PlateShowcase.vue';
import { useAssetExists } from '@/composables/useAssetProbe';
import { useReducedMotion } from '@/composables/useReducedMotion';

const FRONT_SRC = '/media/home/plate/legacy-plate-front.webp';
const BACK_SRC = '/media/home/plate/legacy-plate-back.webp';

const { exists: frontExists } = useAssetExists(FRONT_SRC);
const showingBack = ref(false);

const prefersReducedMotion = useReducedMotion();
const buttonEl = useTemplateRef<HTMLElement>('button');
const hovering = ref(false);
const cursorX = ref(0);
const cursorY = ref(0);

function handlePointerMove(event: PointerEvent) {
    if (prefersReducedMotion.value || !buttonEl.value) {
        return;
    }

    const rect = buttonEl.value.getBoundingClientRect();
    cursorX.value = event.clientX - rect.left;
    cursorY.value = event.clientY - rect.top;
}
</script>

<template>
    <PlateShowcase v-if="!frontExists" />

    <div v-else class="mx-auto w-full max-w-md" style="perspective: 1200px">
        <button
            ref="button"
            type="button"
            class="fl-focus-glow relative aspect-[3/2] w-full cursor-none overflow-hidden rounded-xl border border-border shadow-[0_20px_60px_-15px_rgba(23,23,20,0.28)] transition-transform duration-500"
            :style="{
                transformStyle: 'preserve-3d',
                transform: showingBack ? 'rotateY(180deg)' : 'rotateY(0deg)',
            }"
            :aria-label="
                showingBack
                    ? 'Ver anverso de la placa'
                    : 'Ver reverso de la placa'
            "
            @pointermove="handlePointerMove"
            @pointerenter="hovering = true"
            @pointerleave="hovering = false"
            @click="showingBack = !showingBack"
        >
            <img
                :src="FRONT_SRC"
                alt="Placa Finisher Legacy — anverso"
                class="absolute inset-0 size-full object-cover"
                style="backface-visibility: hidden"
            />
            <img
                :src="BACK_SRC"
                alt="Placa Finisher Legacy — reverso"
                class="absolute inset-0 size-full object-cover"
                style="backface-visibility: hidden; transform: rotateY(180deg)"
                loading="lazy"
            />

            <span
                v-if="hovering && !prefersReducedMotion"
                class="pointer-events-none absolute z-20 rounded-full bg-fl-gold-soft px-2.5 py-1 text-[10px] font-bold tracking-wide text-fl-black uppercase"
                :style="{
                    top: `${cursorY}px`,
                    left: `${cursorX}px`,
                    transform: 'translate(-50%, -150%)',
                }"
            >
                Gira
            </span>
        </button>

        <p
            class="mt-3 text-center text-xs tracking-wide text-muted-foreground/80 uppercase"
        >
            Toca la placa para ver el reverso
        </p>
    </div>
</template>
