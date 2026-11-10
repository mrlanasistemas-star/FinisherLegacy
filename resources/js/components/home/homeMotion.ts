/**
 * Motion helpers for the Home sections (2026-11 redesign).
 *
 *   v-stage        adds `.is-in` while the element is on screen and
 *                  `.has-entered` the first time it is. Home.vue pauses
 *                  every CSS animation inside a `.home-stage` that is not
 *                  `.is-in`, so looping illustrations never run offscreen.
 *   v-parallax     moves the element a few px against the scroll (transform
 *                  only, rAF-throttled, only while on screen).
 *   useCountUp()   a number that counts up to its target once started.
 *   useOnScreen()  reactive "is this element on screen" flag.
 *
 * Under prefers-reduced-motion nothing moves: v-parallax is a no-op and
 * count-ups jump straight to their final value.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue';
import type { Directive, Ref } from 'vue';

export function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

const stageObservers = new WeakMap<HTMLElement, IntersectionObserver>();

export const vStage: Directive<HTMLElement, undefined> = {
    mounted(el) {
        el.classList.add('home-stage');

        if (typeof IntersectionObserver === 'undefined') {
            el.classList.add('is-in', 'has-entered');

            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    el.classList.toggle('is-in', entry.isIntersecting);

                    if (entry.isIntersecting) {
                        el.classList.add('has-entered');
                    }
                }
            },
            { rootMargin: '80px 0px 80px 0px' },
        );
        observer.observe(el);
        stageObservers.set(el, observer);
    },
    unmounted(el) {
        stageObservers.get(el)?.disconnect();
        stageObservers.delete(el);
    },
    getSSRProps: () => ({}),
};

type ParallaxState = {
    observer: IntersectionObserver;
    onScroll: () => void;
};

const parallaxStates = new WeakMap<HTMLElement, ParallaxState>();

/** `v-parallax="24"` — maximum travel in px (default 20). */
export const vParallax: Directive<HTMLElement, number | undefined> = {
    mounted(el, binding) {
        if (
            prefersReducedMotion() ||
            typeof IntersectionObserver === 'undefined'
        ) {
            return;
        }

        const travel = binding.value ?? 20;
        let visible = false;
        let frame = 0;

        const update = () => {
            frame = 0;

            if (!visible) {
                return;
            }

            const rect = el.getBoundingClientRect();
            const viewport = Math.min(window.innerHeight, 1400);
            // -1 (below the fold) … 0 (centred) … 1 (above the fold)
            const progress = Math.max(
                -1,
                Math.min(
                    1,
                    (viewport / 2 - (rect.top + rect.height / 2)) /
                        (viewport / 2 + rect.height / 2),
                ),
            );
            el.style.transform = `translate3d(0, ${(progress * travel).toFixed(1)}px, 0)`;
        };

        const onScroll = () => {
            if (!frame && visible) {
                frame = requestAnimationFrame(update);
            }
        };

        const observer = new IntersectionObserver((entries) => {
            visible = entries.some((e) => e.isIntersecting);
            onScroll();
        });
        observer.observe(el);
        window.addEventListener('scroll', onScroll, { passive: true });
        parallaxStates.set(el, { observer, onScroll });
    },
    unmounted(el) {
        const state = parallaxStates.get(el);

        if (state) {
            state.observer.disconnect();
            window.removeEventListener('scroll', state.onScroll);
            parallaxStates.delete(el);
        }
    },
    getSSRProps: () => ({}),
};

/** True while the referenced element intersects the viewport. */
export function useOnScreen(
    target: Ref<HTMLElement | null>,
    options: IntersectionObserverInit = {},
) {
    const onScreen = ref(false);
    const entered = ref(false);
    let observer: IntersectionObserver | null = null;

    onMounted(() => {
        if (!target.value || typeof IntersectionObserver === 'undefined') {
            onScreen.value = true;
            entered.value = true;

            return;
        }

        observer = new IntersectionObserver((entries) => {
            onScreen.value = entries.some((e) => e.isIntersecting);

            if (onScreen.value) {
                entered.value = true;
            }
        }, options);
        observer.observe(target.value);
    });

    onBeforeUnmount(() => observer?.disconnect());

    return { onScreen, entered };
}

/**
 * A value that eases from 0 to `target` in `duration` ms once `start()` is
 * called (idempotent). The initial value IS the target, so a page rendered
 * without JS — or with reduced motion — always shows the real number.
 */
export function useCountUp(target: number, duration = 1400) {
    const value = ref(target);
    let started = false;
    let frame = 0;

    function start() {
        if (started) {
            return;
        }

        started = true;

        if (prefersReducedMotion()) {
            return;
        }

        const t0 = performance.now();
        value.value = 0;

        const tick = (now: number) => {
            const p = Math.min(1, (now - t0) / duration);
            const eased = 1 - Math.pow(1 - p, 3);
            value.value = Math.round(target * eased);

            if (p < 1) {
                frame = requestAnimationFrame(tick);
            }
        };
        frame = requestAnimationFrame(tick);
    }

    onBeforeUnmount(() => cancelAnimationFrame(frame));

    return { value, start };
}
