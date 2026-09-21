<script setup lang="ts">
/**
 * `finisher-hero-desktop.{mp4,webm}` and their posters are real,
 * confirmed-present assets (see public/media/home/hero/README.md) — no
 * useAssetExists probe needed (brand system §20: don't HEAD-check a static
 * path we already know exists).
 *
 * Plays on every viewport width (parity request, 2026-08-21 polish pass —
 * mobile used to get the CSS scene only). `preload="metadata"` keeps the
 * initial fetch light, and the video pauses itself once the Hero scrolls
 * out of view (brand system §P6: never keep playing what isn't visible) —
 * it's `muted`+`loop` so resuming on re-entry is seamless.
 *
 * Fallback order is video -> poster -> CSS scene, never straight to CSS:
 * a real photo (poster) beats a drawn scene whenever we have one. `stage`
 * moves to 'poster' if the file 404s (`@error`) OR if play() itself
 * rejects — some browsers (autoplay policy, decode failure) reject the
 * play() promise without ever firing `error`, and without handling that
 * case `stage` would stay 'video' forever while nothing actually renders.
 * prefers-reduced-motion skips straight to the poster too — a still frame,
 * not the CSS scene, since it's just as static and more real.
 */
import { useIntersectionObserver, useMediaQuery } from '@vueuse/core';
import { computed, ref, useTemplateRef } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

const DESKTOP_VIDEO_WEBM = '/media/home/hero/finisher-hero-desktop.webm';
const DESKTOP_VIDEO_MP4 = '/media/home/hero/finisher-hero-desktop.mp4';
const POSTER_DESKTOP = '/media/home/hero/finisher-hero-poster.webp';
const POSTER_MOBILE = '/media/home/hero/finisher-hero-poster-mobile.webp';

const prefersReducedMotion = useReducedMotion();
const isMobile = useMediaQuery('(max-width: 640px)');
const videoFailed = ref(false);
const posterFailed = ref(false);
const rootEl = useTemplateRef<HTMLElement>('root');
const videoEl = useTemplateRef<HTMLVideoElement>('video');

const poster = computed(() =>
    isMobile.value ? POSTER_MOBILE : POSTER_DESKTOP,
);

const stage = computed<'video' | 'poster' | 'css'>(() => {
    if (posterFailed.value) {
        return 'css';
    }

    if (prefersReducedMotion.value) {
        return 'poster';
    }

    return videoFailed.value ? 'poster' : 'video';
});

useIntersectionObserver(
    rootEl,
    ([entry]) => {
        if (!videoEl.value) {
            return;
        }

        if (entry?.isIntersecting) {
            videoEl.value.play().catch(() => {
                videoFailed.value = true;
            });
        } else {
            videoEl.value.pause();
        }
    },
    { threshold: 0 },
);
</script>

<template>
    <div
        ref="root"
        class="pointer-events-none absolute inset-0 overflow-hidden"
    >
        <video
            v-if="stage === 'video'"
            ref="video"
            class="absolute inset-0 size-full object-cover"
            :poster="poster"
            autoplay
            muted
            loop
            playsinline
            preload="metadata"
            aria-hidden="true"
            @error="videoFailed = true"
        >
            <source :src="DESKTOP_VIDEO_WEBM" type="video/webm" />
            <source :src="DESKTOP_VIDEO_MP4" type="video/mp4" />
        </video>

        <img
            v-else-if="stage === 'poster'"
            :src="poster"
            alt=""
            aria-hidden="true"
            class="absolute inset-0 size-full object-cover"
            @error="posterFailed = true"
        />

        <!-- CSS fallback scene: track lanes + amber dawn light. Only
             reached if the poster image itself also fails to load. -->
        <div v-else class="absolute inset-0">
            <div
                class="absolute inset-0"
                style="
                    background:
                        radial-gradient(
                            ellipse 70% 55% at 18% 15%,
                            color-mix(in srgb, var(--fl-gold) 22%, transparent),
                            transparent 60%
                        ),
                        radial-gradient(
                            ellipse 60% 50% at 85% 85%,
                            color-mix(
                                in srgb,
                                var(--fl-gold-soft) 10%,
                                transparent
                            ),
                            transparent 60%
                        ),
                        linear-gradient(
                            180deg,
                            var(--fl-black) 0%,
                            var(--fl-graphite) 55%,
                            var(--fl-black) 100%
                        );
                "
            />
            <div
                class="absolute inset-0 opacity-[0.07]"
                style="
                    background-image: repeating-linear-gradient(
                        115deg,
                        rgba(255, 255, 255, 0.6) 0px,
                        rgba(255, 255, 255, 0.6) 1px,
                        transparent 1px,
                        transparent 64px
                    );
                "
            />
        </div>

        <!-- Legibility overlay — only needed over real photo/video, the CSS
             scene is already tuned dark enough on its own. -->
        <div v-if="stage !== 'css'" class="absolute inset-0 bg-fl-black/55" />
        <div class="absolute inset-x-0 bottom-0 h-px bg-white/10" />
    </div>
</template>
