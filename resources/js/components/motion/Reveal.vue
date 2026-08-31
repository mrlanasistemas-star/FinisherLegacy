<script setup lang="ts">
/**
 * Fades + translates its content in once it enters the viewport.
 * Wrap sections/cards with this instead of hand-rolling IntersectionObserver
 * logic per component. Respects prefers-reduced-motion (shows content
 * instantly, no motion).
 *
 * Content-safety guarantee (cross-PC home bug fix, 2026-08-31): the hidden
 * starting state used to be applied unconditionally in the template/SSR
 * render — meaning every section wrapped in <Reveal> was invisible by
 * default and depended entirely on a client-side IntersectionObserver
 * callback firing to ever appear. Any interruption in that chain on a given
 * machine (JS execution timing, extension/security-suite interference with
 * observers, an unrelated error earlier in the tree) left real page content
 * permanently blank, and no amount of cache-clearing or hard refresh could
 * fix it since it isn't a caching problem. Fixed by flipping the model:
 * - The template renders with NO hidden style at all, so if JS never runs
 *   (or fails before this component mounts), the content is just plain
 *   visible HTML — animation is strictly additive, never a prerequisite.
 * - The hidden starting state is applied imperatively in onMounted, only
 *   once we know JS is alive.
 * - A bounded fallback timer force-reveals the element even if the
 *   IntersectionObserver callback never fires for any reason. Animation
 *   must never be a requirement for seeing content (brand system §5/§15).
 */
import { useIntersectionObserver } from '@vueuse/core';
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

const props = withDefaults(
    defineProps<{
        as?: string;
        direction?: 'up' | 'down' | 'left' | 'right' | 'none';
        delayMs?: number;
        durationMs?: number;
        once?: boolean;
    }>(),
    {
        as: 'div',
        direction: 'up',
        delayMs: 0,
        durationMs: 500,
        once: true,
    },
);

// Long enough that a working IntersectionObserver always wins the race
// (it fires within a frame or two of becoming visible), short enough that
// a broken/never-firing observer can't leave content hidden for long.
const REVEAL_FALLBACK_MS = 2500;

const prefersReducedMotion = useReducedMotion();
const el = useTemplateRef<HTMLElement>('el');

const offsets: Record<string, string> = {
    up: 'translateY(16px)',
    down: 'translateY(-16px)',
    left: 'translateX(16px)',
    right: 'translateX(-16px)',
    none: 'translateY(0)',
};

let fallbackTimer: ReturnType<typeof setTimeout> | null = null;

function clearFallback() {
    if (fallbackTimer !== null) {
        clearTimeout(fallbackTimer);
        fallbackTimer = null;
    }
}

function reveal(node: HTMLElement) {
    clearFallback();
    node.style.opacity = '1';
    node.style.transform = 'translate(0, 0)';
}

function hide(node: HTMLElement) {
    node.style.opacity = '0';
    node.style.transform = offsets[props.direction];
    node.style.transitionProperty = 'opacity, transform';
    node.style.transitionDuration = `${props.durationMs}ms`;
    node.style.transitionTimingFunction = 'cubic-bezier(0.16, 1, 0.3, 1)';
    node.style.transitionDelay = `${props.delayMs}ms`;
    clearFallback();
    fallbackTimer = setTimeout(() => reveal(node), REVEAL_FALLBACK_MS);
}

onMounted(() => {
    const node = el.value;

    if (!node || prefersReducedMotion.value) {
        return;
    }

    hide(node);
});

onBeforeUnmount(clearFallback);

useIntersectionObserver(
    el,
    ([entry]) => {
        if (!entry) {
            return;
        }

        const node = el.value;

        if (!node) {
            return;
        }

        if (entry.isIntersecting) {
            reveal(node);
        } else if (!props.once) {
            hide(node);
        }
    },
    { threshold: 0.15 },
);
</script>

<template>
    <component :is="as" ref="el">
        <slot />
    </component>
</template>
