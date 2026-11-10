<script setup lang="ts">
/**
 * TagFlipCard — the tag render for store/collection tiles: the FRONT
 * (collection artwork) that flips to the BACK on hover (fine pointers) and
 * with a button (touch + keyboard). Both faces are the shared
 * TagVisualizer, so they always match the 3D model. Under reduced motion
 * the flip is an instant swap.
 */
import { RefreshCw } from '@lucide/vue';
import { ref } from 'vue';
import type { TagArt } from '@/components/three/tagStageContext';
import TagVisualizer from './TagVisualizer.vue';

const props = withDefaults(
    defineProps<{
        art: TagArt;
        /** Show the "Ver reverso" button (touch / keyboard). */
        button?: boolean;
        /** Flip on hover (fine pointers only). */
        hover?: boolean;
    }>(),
    { button: true, hover: true },
);

const flipped = ref(false);

function toggle(event: Event) {
    // Tiles are often links: the button must not navigate.
    event.preventDefault();
    event.stopPropagation();
    flipped.value = !flipped.value;
}
</script>

<template>
    <div
        class="tag-flip relative"
        :class="{ 'is-flipped': flipped, 'flip-on-hover': props.hover }"
    >
        <div class="tag-flip__inner">
            <div class="tag-flip__face" :aria-hidden="flipped">
                <TagVisualizer
                    :name="art.name"
                    :subtitle="art.subtitle"
                    :year="art.year"
                    :accent="art.accent"
                    :front-art="art.frontArt"
                    :name-line="art.nameLine"
                    :discipline-line="art.disciplineLine"
                    :back-line="art.backLine"
                    face="front"
                    :tilt="false"
                />
            </div>
            <div
                class="tag-flip__face tag-flip__face--back"
                :aria-hidden="!flipped"
            >
                <TagVisualizer
                    :name="art.name"
                    :subtitle="art.subtitle"
                    :year="art.year"
                    :accent="art.accent"
                    :back-line="art.backLine"
                    face="back"
                    :tilt="false"
                />
            </div>
        </div>
        <button
            v-if="button"
            type="button"
            class="tag-flip__button absolute bottom-[4%] left-1/2 z-10 inline-flex h-8 -translate-x-1/2 items-center gap-1.5 rounded-full border border-foreground/10 bg-white/90 px-2.5 text-[11px] font-medium whitespace-nowrap text-foreground shadow-fl-micro backdrop-blur transition-colors hover:bg-white focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
            :aria-pressed="flipped"
            @click="toggle"
        >
            <RefreshCw class="size-3.5" aria-hidden="true" />
            {{ flipped ? 'Ver frente' : 'Ver reverso' }}
        </button>
    </div>
</template>

<style scoped>
.tag-flip {
    perspective: 1200px;
}

.tag-flip__inner {
    position: relative;
    transform-style: preserve-3d;
    transition: transform 700ms cubic-bezier(0.16, 1, 0.3, 1);
}

.tag-flip__face {
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

.tag-flip__face--back {
    position: absolute;
    inset: 0;
    transform: rotateY(180deg);
}

.tag-flip.is-flipped .tag-flip__inner {
    transform: rotateY(180deg);
}

@media (hover: hover) and (pointer: fine) {
    /* Hovering the tag — or the whole tile (`group`) — shows the back. */
    .tag-flip.flip-on-hover:hover .tag-flip__inner,
    :global(.group:hover) .tag-flip.flip-on-hover .tag-flip__inner {
        transform: rotateY(180deg);
    }

    .tag-flip.flip-on-hover.is-flipped:hover .tag-flip__inner,
    :global(.group:hover) .tag-flip.flip-on-hover.is-flipped .tag-flip__inner {
        transform: rotateY(0deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .tag-flip__inner {
        transition: none;
    }
}
</style>
