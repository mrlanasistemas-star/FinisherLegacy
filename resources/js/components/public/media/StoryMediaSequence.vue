<script setup lang="ts">
/**
 * "NO ES SOLO UNA MEDALLA" (Escena 02) — opens on the real training video,
 * then evolves into a crossfading sequence of the other three story
 * photos. All four are confirmed-present assets (public/media/home/story/
 * README.md), so they're referenced directly rather than probed with
 * useAssetExists — that composable is for genuinely-optional future
 * assets, not files we already know are in the repo (brand system §20).
 *
 * Video plays once, muted, on entering the viewport, then hands off to the
 * photo cycle permanently — never a second replay, never more than one
 * video/animation running on the page at a time. Both the video and the
 * cycle pause when scrolled out of view. prefers-reduced-motion skips the
 * video and the cycle entirely, landing on the training-dawn photo static.
 */
import { useIntersectionObserver } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, useTemplateRef } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

const VIDEO_SRC_WEBM = '/media/home/story/training-dawn.webm';
const VIDEO_SRC_MP4 = '/media/home/story/training-dawn.mp4';
const VIDEO_POSTER = '/media/home/story/training-dawn-poster.webp';

const PHOTOS = [
    { src: '/media/home/story/training-dawn.jpeg', position: '62% 45%' },
    { src: '/media/home/story/race-effort.jpeg', position: '30% 25%' },
    { src: '/media/home/story/finish-emotion.jpeg', position: '55% 35%' },
    { src: '/media/home/story/medal-closeup.jpeg', position: '50% 12%' },
];

const prefersReducedMotion = useReducedMotion();
const rootEl = useTemplateRef<HTMLElement>('root');
const videoEl = useTemplateRef<HTMLVideoElement>('video');
const videoFailed = ref(false);
const videoDone = ref(prefersReducedMotion.value);
const activePos = ref(0);
let timer: ReturnType<typeof setInterval> | undefined;

const showingVideo = computed(
    () => !prefersReducedMotion.value && !videoFailed.value && !videoDone.value,
);

function startCycle() {
    if (timer || prefersReducedMotion.value) {
        return;
    }

    timer = setInterval(() => {
        activePos.value = (activePos.value + 1) % PHOTOS.length;
    }, 2600);
}

function stopCycle() {
    if (timer) {
        clearInterval(timer);
        timer = undefined;
    }
}

function handleVideoEnded() {
    videoDone.value = true;
    startCycle();
}

function handleVideoRejected() {
    // play() can reject (autoplay policy, decode failure) without ever
    // firing `error` — without this, showingVideo stays true forever and
    // the photo cycle never starts.
    videoFailed.value = true;
    videoDone.value = true;
    startCycle();
}

useIntersectionObserver(
    rootEl,
    ([entry]) => {
        if (entry?.isIntersecting) {
            if (showingVideo.value) {
                videoEl.value?.play().catch(handleVideoRejected);
            } else {
                startCycle();
            }
        } else {
            videoEl.value?.pause();
            stopCycle();
        }
    },
    { threshold: 0.3 },
);

onBeforeUnmount(stopCycle);
</script>

<template>
    <div
        ref="root"
        class="relative aspect-4/5 w-full overflow-hidden rounded-2xl border border-white/10 bg-fl-graphite/50"
    >
        <video
            v-if="showingVideo"
            ref="video"
            class="absolute inset-0 size-full object-cover"
            :poster="VIDEO_POSTER"
            muted
            playsinline
            preload="metadata"
            aria-hidden="true"
            @ended="handleVideoEnded"
            @error="videoFailed = true"
        >
            <source :src="VIDEO_SRC_WEBM" type="video/webm" />
            <source :src="VIDEO_SRC_MP4" type="video/mp4" />
        </video>

        <img
            v-for="(photo, pos) in PHOTOS"
            v-show="!showingVideo"
            :key="photo.src"
            :src="photo.src"
            alt=""
            loading="lazy"
            class="fl-story-photo absolute inset-0 size-full object-cover transition-opacity duration-700 ease-in-out"
            :class="
                pos === activePos ? 'fl-story-active opacity-100' : 'opacity-0'
            "
            :style="{ objectPosition: photo.position }"
        />

        <div
            class="pointer-events-none absolute inset-0 bg-gradient-to-t from-fl-black/60 via-transparent to-transparent"
        />
    </div>
</template>

<style scoped>
/* Slow Ken Burns drift on the active photo — presence, not a static
   thumbnail (brand system §7, "presencia editorial"). */
.fl-story-active {
    animation: fl-story-zoom 4s ease-out forwards;
}

@keyframes fl-story-zoom {
    from {
        transform: scale(1);
    }
    to {
        transform: scale(1.05);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-story-active {
        animation: none;
    }
}
</style>
