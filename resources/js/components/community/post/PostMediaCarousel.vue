<script setup lang="ts">
/**
 * Instagram-style media of a post: one full-width frame, swipe (native
 * scroll-snap) between photos, dots + "2/4" counter, arrows on desktop
 * hover, double-tap / double-click a photo to celebrar (heart burst).
 * The frame keeps the first photo's shape (clamped 4:5 … 1.91:1) so the
 * feed never jumps while images load.
 */
import { ChevronLeft, ChevronRight, Heart } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { CommunityPost } from '@/types';

const props = withDefaults(
    defineProps<{
        media: CommunityPost['media'];
        authorName: string;
        /** Increments when the post is celebrated — replays the heart. */
        burst?: number;
        /** Detail page: fill the parent's height, photos contained. */
        fill?: boolean;
    }>(),
    { burst: 0, fill: false },
);

const emit = defineEmits<{ doubleTap: [] }>();

const track = ref<HTMLDivElement | null>(null);
const index = ref(0);
const count = computed(() => props.media.length);

const aspectStyle = computed(() => {
    if (props.fill) {
        return {};
    }

    const first = props.media[0];

    if (!first?.width || !first?.height) {
        return { aspectRatio: '4 / 5' };
    }

    const ratio = Math.min(1.91, Math.max(0.8, first.width / first.height));

    return { aspectRatio: String(ratio) };
});

function onScroll() {
    const el = track.value;

    if (!el || !el.clientWidth) {
        return;
    }

    index.value = Math.round(el.scrollLeft / el.clientWidth);
}

function go(to: number) {
    const el = track.value;

    if (!el) {
        return;
    }

    const target = Math.max(0, Math.min(count.value - 1, to));
    el.scrollTo({ left: target * el.clientWidth, behavior: 'smooth' });
}

// --- Double tap ----------------------------------------------------------
const heartKey = ref(0);
const showHeart = ref(false);
let lastTap = 0;
let downX = 0;
let downY = 0;
let hideTimer: number | undefined;

watch(
    () => props.burst,
    (value, old) => {
        if (value > old) {
            playHeart();
        }
    },
);

function playHeart() {
    heartKey.value++;
    showHeart.value = true;
    window.clearTimeout(hideTimer);
    hideTimer = window.setTimeout(() => (showHeart.value = false), 900);
}

function onPointerDown(event: PointerEvent) {
    downX = event.clientX;
    downY = event.clientY;
}

function onPointerUp(event: PointerEvent) {
    // A swipe is not a tap.
    if (
        Math.abs(event.clientX - downX) > 10 ||
        Math.abs(event.clientY - downY) > 10
    ) {
        return;
    }

    const now = Date.now();

    if (now - lastTap < 300) {
        lastTap = 0;
        emit('doubleTap');

        return;
    }

    lastTap = now;
}
</script>

<template>
    <div
        class="group/media relative overflow-hidden bg-fl-cream select-none"
        :class="fill ? 'h-full' : ''"
        :style="aspectStyle"
    >
        <div
            ref="track"
            class="flex size-full snap-x snap-mandatory [scrollbar-width:none] overflow-x-auto overscroll-x-contain [&::-webkit-scrollbar]:hidden"
            :aria-label="`Fotos de ${authorName}`"
            aria-roledescription="carrusel"
            tabindex="0"
            @scroll.passive="onScroll"
            @keydown.left.prevent="go(index - 1)"
            @keydown.right.prevent="go(index + 1)"
        >
            <div
                v-for="(item, i) in media"
                :key="`${item.url}-${i}`"
                class="relative size-full shrink-0 snap-center snap-always"
                role="group"
                aria-roledescription="foto"
                :aria-label="`${i + 1} de ${count}`"
            >
                <video
                    v-if="item.type === 'video'"
                    :src="item.url"
                    controls
                    muted
                    playsinline
                    preload="metadata"
                    class="size-full bg-fl-cream object-contain"
                />
                <img
                    v-else
                    :src="item.url"
                    :alt="`Foto ${i + 1} de ${authorName}`"
                    :loading="i === 0 ? 'eager' : 'lazy'"
                    decoding="async"
                    draggable="false"
                    class="size-full touch-manipulation"
                    :class="fill ? 'object-contain' : 'object-cover'"
                    :width="item.width ?? undefined"
                    :height="item.height ?? undefined"
                    @pointerdown="onPointerDown"
                    @pointerup="onPointerUp"
                />
            </div>
        </div>

        <!-- Heart burst -->
        <div
            v-if="showHeart"
            :key="heartKey"
            class="pointer-events-none absolute inset-0 flex items-center justify-center"
            aria-hidden="true"
        >
            <Heart
                class="fl-heart-burst size-24 fill-white text-white drop-shadow-[0_6px_24px_rgb(0_0_0/0.35)]"
            />
        </div>

        <template v-if="count > 1">
            <span
                class="legacy-numeric pointer-events-none absolute top-3 right-3 rounded-full bg-black/55 px-2.5 py-1 text-[11px] font-semibold text-white"
                aria-live="polite"
                >{{ index + 1 }}/{{ count }}</span
            >

            <button
                v-if="index > 0"
                type="button"
                class="absolute top-1/2 left-3 hidden size-8 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-foreground shadow-fl-soft transition-opacity group-hover/media:opacity-100 focus-visible:opacity-100 sm:flex sm:opacity-0"
                aria-label="Foto anterior"
                @click="go(index - 1)"
            >
                <ChevronLeft class="size-4" />
            </button>
            <button
                v-if="index < count - 1"
                type="button"
                class="absolute top-1/2 right-3 hidden size-8 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-foreground shadow-fl-soft transition-opacity group-hover/media:opacity-100 focus-visible:opacity-100 sm:flex sm:opacity-0"
                aria-label="Foto siguiente"
                @click="go(index + 1)"
            >
                <ChevronRight class="size-4" />
            </button>

            <div
                class="pointer-events-none absolute inset-x-0 bottom-3 flex justify-center gap-1"
                aria-hidden="true"
            >
                <span
                    v-for="(_, i) in media"
                    :key="i"
                    class="size-1.5 rounded-full transition-all duration-200"
                    :class="
                        i === index ? 'w-3 bg-white' : 'bg-white/60 shadow-sm'
                    "
                />
            </div>
        </template>
    </div>
</template>

<style scoped>
.fl-heart-burst {
    animation: fl-heart-burst 0.85s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
}

@keyframes fl-heart-burst {
    0% {
        transform: scale(0.2);
        opacity: 0;
    }
    15% {
        transform: scale(1.15);
        opacity: 0.95;
    }
    30% {
        transform: scale(0.95);
    }
    45% {
        transform: scale(1);
        opacity: 0.95;
    }
    80% {
        transform: scale(1);
        opacity: 0.95;
    }
    100% {
        transform: scale(1.1) translateY(-12px);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-heart-burst {
        animation: none;
        opacity: 0;
    }
}
</style>
