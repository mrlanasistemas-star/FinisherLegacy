import { useIntersectionObserver } from '@vueuse/core';
import { onBeforeUnmount, onMounted, unref } from 'vue';
import type { MaybeRef } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

/**
 * Scroll-linked parallax, done the cheap/correct way (UI System §9):
 * transform/opacity only, no per-frame Vue re-render, IntersectionObserver-gated
 * so the rAF work only runs while the element is actually on screen, and a
 * no-op under `prefers-reduced-motion`.
 *
 * It writes a single signed, normalised progress value into a CSS custom
 * property on the target element (default `--sp`), which is inherited by every
 * descendant. The number runs:
 *   +1  → element is just entering from the bottom of the viewport
 *    0  → element is vertically centred in the viewport
 *   -1  → element has just left past the top
 *
 * Components opt in declaratively, e.g.:
 *   transform: translateY(calc(var(--sp, 0) * 30px)) scale(1.1);
 * and gate it behind `@media (prefers-reduced-motion: no-preference)` so that,
 * with reduced motion (where this composable never sets `--sp`), the fallback
 * `var(--sp, 0)` resolves to a still frame.
 *
 * Content-safety: parallax is purely cosmetic. If JS never runs the property is
 * simply never set and `var(--sp, 0)` keeps everything at its resting state.
 */
export function useParallax(
    target: MaybeRef<HTMLElement | null | undefined>,
    options: { cssVar?: string } = {},
) {
    const cssVar = options.cssVar ?? '--sp';
    const reducedMotion = useReducedMotion();

    let frame = 0;
    let visible = false;
    let enabled = false;

    function update() {
        frame = 0;

        const el = unref(target);

        if (!el) {
            return;
        }

        const viewportH =
            window.innerHeight || document.documentElement.clientHeight || 0;

        if (viewportH === 0) {
            return;
        }

        const rect = el.getBoundingClientRect();
        const elementCentre = rect.top + rect.height / 2;
        const half = (viewportH + rect.height) / 2;
        let progress = (elementCentre - viewportH / 2) / half;
        progress = Math.max(-1, Math.min(1, progress));

        el.style.setProperty(cssVar, progress.toFixed(4));
    }

    function schedule() {
        if (frame !== 0 || !enabled) {
            return;
        }

        frame = requestAnimationFrame(update);
    }

    function onScrollOrResize() {
        if (visible) {
            schedule();
        }
    }

    useIntersectionObserver(target, ([entry]) => {
        visible = entry?.isIntersecting ?? false;

        if (visible) {
            schedule();
        }
    });

    onMounted(() => {
        if (reducedMotion.value) {
            return;
        }

        enabled = true;
        window.addEventListener('scroll', onScrollOrResize, { passive: true });
        window.addEventListener('resize', onScrollOrResize, { passive: true });
        // Prime the initial value for anything already on screen (the hero).
        schedule();
    });

    onBeforeUnmount(() => {
        enabled = false;
        window.removeEventListener('scroll', onScrollOrResize);
        window.removeEventListener('resize', onScrollOrResize);

        if (frame !== 0) {
            cancelAnimationFrame(frame);
            frame = 0;
        }
    });
}
