<script setup lang="ts">
/**
 * Photos of a post, laid out deterministically so the feed never jumps:
 *   1  → full width, capped 16:9 (landscape) / 4:5 (portrait)
 *   2  → two equal 1:1 columns
 *   3  → one large left (full height) + two stacked right
 *   4  → 2×2 equal
 *   5+ → 2×2 with a gold-tinted "+N" overlay on the last visible tile
 * Fixed 3px gutter, uniform radius (edge-to-edge on phone, rounded on
 * desktop). Image tiles are focusable buttons that open a lightbox
 * (Reka Dialog, focus-trapped, arrow-key + Esc navigable). Event-sourced
 * videos play inline (muted, playsinline, preload=none, no autoplay; the
 * media contract carries no poster frame) so their controls stay usable;
 * they are reachable in the lightbox only via arrow navigation.
 */
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { CommunityPost } from '@/types';

const props = defineProps<{
    media: CommunityPost['media'];
    authorName: string;
}>();

const MAX_VISIBLE = 4;

const count = computed(() => props.media.length);
const visible = computed(() => props.media.slice(0, MAX_VISIBLE));
const overflow = computed(() => Math.max(0, props.media.length - MAX_VISIBLE));

/** Single image: cap the aspect so one photo never swallows the viewport. */
const singleAspectClass = computed(() => {
    const m = props.media[0];

    if (!m?.width || !m?.height) {
        return 'aspect-[4/5]';
    }

    if (m.width > m.height) {
        return 'aspect-[16/9]';
    }

    if (m.height > m.width) {
        return 'aspect-[4/5]';
    }

    return 'aspect-square';
});

/** Grid shell per count. */
const gridClass = computed(() => {
    switch (count.value) {
        case 1:
            return 'grid-cols-1';
        case 2:
            return 'grid-cols-2';
        case 3:
            return 'grid-cols-2 grid-rows-2 aspect-[3/2]';
        default:
            return 'grid-cols-2';
    }
});

/** Grid placement + intrinsic sizing per tile index. */
function tileLayoutClass(index: number): string {
    const c = count.value;

    if (c === 1) {
        return `col-span-1 ${singleAspectClass.value}`;
    }

    if (c === 2) {
        return 'col-span-1 aspect-square';
    }

    if (c === 3) {
        return index === 0
            ? 'col-span-1 row-span-2 h-full'
            : 'col-span-1 h-full';
    }

    // 4 and 5+
    return 'col-span-1 aspect-square';
}

// --- Lightbox -----------------------------------------------------------
const lightboxIndex = ref<number | null>(null);
const open = computed({
    get: () => lightboxIndex.value !== null,
    set: (value: boolean) => {
        if (!value) {
            lightboxIndex.value = null;
        }
    },
});

const current = computed(() =>
    lightboxIndex.value !== null ? props.media[lightboxIndex.value] : null,
);

function openAt(index: number) {
    lightboxIndex.value = index;
}

function go(direction: number) {
    if (lightboxIndex.value === null || count.value < 2) {
        return;
    }

    lightboxIndex.value =
        (lightboxIndex.value + direction + count.value) % count.value;
}
</script>

<template>
    <div>
        <div class="overflow-hidden bg-muted">
            <div class="grid gap-[3px]" :class="gridClass">
                <template v-for="(item, index) in visible" :key="item.url">
                    <!-- Event-sourced video stays inline, never autoplays. -->
                    <video
                        v-if="item.type === 'video'"
                        :src="item.url"
                        controls
                        muted
                        playsinline
                        preload="none"
                        class="size-full bg-black object-cover"
                        :class="
                            count === 1
                                ? 'col-span-1 aspect-video'
                                : tileLayoutClass(index)
                        "
                    />
                    <!-- Photo tile → opens the lightbox. -->
                    <button
                        v-else
                        type="button"
                        class="group relative block overflow-hidden bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        :class="tileLayoutClass(index)"
                        :aria-label="`Ampliar foto ${index + 1} de ${authorName}`"
                        @click="
                            openAt(
                                overflow && index === MAX_VISIBLE - 1
                                    ? MAX_VISIBLE
                                    : index,
                            )
                        "
                    >
                        <img
                            :src="item.url"
                            :alt="`Foto ${index + 1} de ${authorName}`"
                            loading="lazy"
                            decoding="async"
                            class="size-full object-cover transition-transform duration-500 ease-out group-hover:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100"
                            :width="item.width ?? undefined"
                            :height="item.height ?? undefined"
                        />
                        <!-- +N overlay on the last visible tile. -->
                        <span
                            v-if="overflow && index === MAX_VISIBLE - 1"
                            class="absolute inset-0 flex items-center justify-center bg-fl-black/55 text-2xl font-semibold text-white backdrop-blur-[1px]"
                            aria-hidden="true"
                        >
                            <span class="legacy-numeric">+{{ overflow }}</span>
                        </span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Lightbox -->
        <Dialog v-model:open="open">
            <DialogContent
                class="max-w-[min(96vw,1100px)] border-0 bg-transparent p-0 shadow-none sm:max-w-[min(92vw,1100px)]"
                @keydown.left="go(-1)"
                @keydown.right="go(1)"
            >
                <DialogTitle class="sr-only">
                    Fotos de {{ authorName }}
                </DialogTitle>
                <DialogDescription class="sr-only">
                    Galería de fotos. Usa las flechas izquierda y derecha para
                    navegar entre las imágenes.
                </DialogDescription>

                <div class="relative flex items-center justify-center">
                    <video
                        v-if="current && current.type === 'video'"
                        :src="current.url"
                        controls
                        muted
                        playsinline
                        preload="none"
                        class="max-h-[85svh] w-auto max-w-full rounded-media bg-black"
                    />
                    <img
                        v-else-if="current"
                        :src="current.url"
                        :alt="`Foto de ${authorName}`"
                        class="max-h-[85svh] w-auto max-w-full rounded-media object-contain"
                    />

                    <template v-if="count > 1">
                        <button
                            type="button"
                            class="absolute left-2 flex size-11 items-center justify-center rounded-full bg-fl-black/60 text-white transition-colors hover:bg-fl-black/80 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                            aria-label="Foto anterior"
                            @click="go(-1)"
                        >
                            <svg
                                class="size-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path
                                    d="M15 18l-6-6 6-6"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="absolute right-2 flex size-11 items-center justify-center rounded-full bg-fl-black/60 text-white transition-colors hover:bg-fl-black/80 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
                            aria-label="Foto siguiente"
                            @click="go(1)"
                        >
                            <svg
                                class="size-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                aria-hidden="true"
                            >
                                <path
                                    d="M9 6l6 6-6 6"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </button>
                        <span
                            v-if="lightboxIndex !== null"
                            class="legacy-numeric absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-fl-black/60 px-3 py-1 text-xs font-medium text-white"
                            aria-live="polite"
                        >
                            {{ lightboxIndex + 1 }} / {{ count }}
                        </span>
                    </template>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
