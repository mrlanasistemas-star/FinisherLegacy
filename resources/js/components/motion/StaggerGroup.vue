<script setup lang="ts">
/**
 * Wrap a grid/list of cards with this to reveal them with a staggered
 * fade+translate as the group enters the viewport, instead of adding
 * per-card Reveal delays by hand.
 *
 * Rewritten 2026-08-31 (cross-PC home bug investigation): the previous
 * version hid children via a CSS class (`fl-stagger-item`) applied through
 * `class="[&>*]:fl-stagger-item"` on the wrapper — but `fl-stagger-item`
 * isn't a real Tailwind utility, so Tailwind silently dropped that
 * candidate and never generated the compound selector. Confirmed by
 * building and grepping the output CSS: `.fl-stagger-item{opacity:0;...}`
 * existed as a standalone rule, but no `[&>*]` variant of it was ever
 * emitted, and the class was never applied to any child. Net effect: the
 * entrance animation was dead code (children were always plain visible,
 * never actually staggered in) — not a content-visibility bug, but a
 * confirmed instance of "the animation just doesn't run."
 *
 * Fixed by dropping the CSS-class/selector indirection entirely and doing
 * the same imperative, content-safe approach as Reveal.vue: children are
 * visible by default (nothing hides them until JS confirms it's alive in
 * onMounted), and a bounded fallback timer reveals everything even if the
 * IntersectionObserver callback never fires.
 */
import { useIntersectionObserver } from '@vueuse/core';
import { onBeforeUnmount, onMounted, useTemplateRef } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

const props = withDefaults(
    defineProps<{
        as?: string;
        itemSelector?: string;
        staggerMs?: number;
        durationMs?: number;
    }>(),
    {
        as: 'div',
        itemSelector: ':scope > *',
        staggerMs: 60,
        durationMs: 450,
    },
);

const REVEAL_FALLBACK_MS = 2500;

const prefersReducedMotion = useReducedMotion();
const el = useTemplateRef<HTMLElement>('el');
let fallbackTimer: ReturnType<typeof setTimeout> | null = null;
let revealed = false;

function items(): HTMLElement[] {
    return el.value
        ? Array.from(el.value.querySelectorAll<HTMLElement>(props.itemSelector))
        : [];
}

function clearFallback() {
    if (fallbackTimer !== null) {
        clearTimeout(fallbackTimer);
        fallbackTimer = null;
    }
}

function reveal() {
    if (revealed) {
        return;
    }

    revealed = true;
    clearFallback();

    items().forEach((item, index) => {
        item.style.transitionDelay = `${index * props.staggerMs}ms`;
        item.style.opacity = '1';
        item.style.transform = 'translateY(0)';
    });
}

function hide() {
    items().forEach((item) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(16px)';
        item.style.transitionProperty = 'opacity, transform';
        item.style.transitionDuration = `${props.durationMs}ms`;
        item.style.transitionTimingFunction = 'cubic-bezier(0.16, 1, 0.3, 1)';
    });

    clearFallback();
    fallbackTimer = setTimeout(reveal, REVEAL_FALLBACK_MS);
}

onMounted(() => {
    if (prefersReducedMotion.value) {
        return;
    }

    hide();
});

onBeforeUnmount(clearFallback);

useIntersectionObserver(
    el,
    ([entry]) => {
        if (entry?.isIntersecting && !prefersReducedMotion.value) {
            reveal();
        }
    },
    { threshold: 0.1 },
);
</script>

<template>
    <component :is="as" ref="el">
        <slot />
    </component>
</template>
