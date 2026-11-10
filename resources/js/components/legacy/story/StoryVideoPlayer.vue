<script setup lang="ts">
/**
 * One video of a Legacy story. Nothing is downloaded up front: the poster
 * is a lazy image and the `<video>` only receives its `src` when the
 * visitor presses play (or, without a poster, when the card nears the
 * viewport — then only metadata is fetched so a first frame can show).
 * The frame keeps the video's own aspect ratio, capped so a vertical clip
 * never fills more than the screen.
 */
import { Play } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { clock } from './types';
import type { StoryMediaExtended } from './types';

const props = defineProps<{
    video: StoryMediaExtended;
    /** "Video 2" style fallback label for assistive tech. */
    label: string;
}>();

const emit = defineEmits<{ play: [uuid: string] }>();

const root = ref<HTMLElement | null>(null);
const player = ref<HTMLVideoElement | null>(null);
const started = ref(false);
const near = ref(false);
const failed = ref(false);
let observer: IntersectionObserver | null = null;

const ratio = computed(() => {
    const { width, height } = props.video;

    return width && height ? `${width} / ${height}` : '16 / 9';
});
const vertical = computed(() => {
    const { width, height } = props.video;

    return !!width && !!height && height > width;
});
const poster = computed(
    () => props.video.poster_url ?? props.video.thumb_url ?? null,
);
// With a poster the bytes wait for a tap; without one, metadata loads
// once the card is close so the browser can paint a first frame.
const src = computed(() =>
    started.value || (near.value && poster.value === null)
        ? (props.video.url ?? undefined)
        : undefined,
);
const duration = computed(() =>
    props.video.duration_seconds ? clock(props.video.duration_seconds) : null,
);

async function start() {
    if (!props.video.url) {
        return;
    }

    started.value = true;
    await nextTick();

    try {
        await player.value?.play();
    } catch {
        // Autoplay refused: the native controls are visible, one more tap plays.
    }
}

function pause() {
    player.value?.pause();
}

defineExpose({ pause });

onMounted(() => {
    if (typeof IntersectionObserver === 'undefined' || !root.value) {
        near.value = true;

        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                near.value = true;
                observer?.disconnect();
                observer = null;
            }
        },
        { rootMargin: '300px 0px' },
    );
    observer.observe(root.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <figure ref="root" class="min-w-0">
        <div
            class="relative mx-auto overflow-hidden rounded-2xl bg-fl-black shadow-fl-soft"
            :class="vertical ? 'max-w-sm' : 'w-full'"
            :style="{ aspectRatio: ratio, maxHeight: '80svh' }"
        >
            <video
                ref="player"
                class="absolute inset-0 size-full bg-fl-black object-contain"
                :src="src"
                :poster="poster ?? undefined"
                :controls="started"
                :preload="started ? 'auto' : poster ? 'none' : 'metadata'"
                playsinline
                :aria-label="video.caption ?? label"
                @play="emit('play', video.uuid)"
                @error="failed = started"
            />

            <button
                v-if="!started"
                type="button"
                class="group absolute inset-0 flex items-center justify-center focus-visible:ring-4 focus-visible:ring-fl-gold/60 focus-visible:outline-none focus-visible:ring-inset"
                :aria-label="
                    $t('Reproducir {title}', { title: video.caption ?? label })
                "
                @click="start"
            >
                <img
                    v-if="poster && near"
                    :src="poster"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    class="absolute inset-0 size-full object-cover transition duration-700 motion-safe:group-hover:scale-[1.02]"
                />
                <span
                    class="absolute inset-0 bg-gradient-to-t from-black/45 via-black/5 to-transparent"
                    aria-hidden="true"
                />
                <span
                    class="relative flex size-16 items-center justify-center rounded-full bg-white/95 text-foreground shadow-fl-float transition motion-safe:group-hover:scale-105"
                >
                    <Play class="ml-1 size-7" aria-hidden="true" />
                </span>
                <span
                    v-if="duration"
                    class="legacy-numeric absolute right-3 bottom-3 rounded-full bg-black/65 px-2.5 py-1 text-xs font-medium text-white backdrop-blur-sm"
                >
                    {{ duration }}
                </span>
            </button>

            <p
                v-if="failed"
                class="absolute inset-x-0 bottom-0 bg-black/70 px-4 py-3 text-center text-xs text-white"
                role="status"
            >
                {{
                    $t(
                        'No pudimos reproducir este video. Recarga la página e intenta de nuevo.',
                    )
                }}
            </p>
        </div>
        <figcaption
            v-if="video.caption"
            class="mt-2 text-center text-sm text-muted-foreground"
        >
            {{ video.caption }}
        </figcaption>
    </figure>
</template>
