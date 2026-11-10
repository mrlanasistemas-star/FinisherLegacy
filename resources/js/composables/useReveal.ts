/**
 * `v-reveal` — the public site's entry animation as a directive (CSS lives
 * in resources/css/app.css, "motion system v2").
 *
 *   <section v-reveal>…</section>                 fade + slide up
 *   <li v-for="(x, i) in xs" v-reveal="{ index: i }">  staggered
 *   <div v-reveal="{ delay: 120, y: 28 }">        custom delay / distance
 *   <ul v-reveal.stagger>                          reveals each direct child
 *
 * Content safety (same contract as <Reveal>): the server/first render has
 * NO hidden state. The directive only hides the element once mounted with
 * JS alive, reveals it on intersection and force-reveals it after a
 * bounded fallback, so a silent observer can never leave content blank.
 * Under prefers-reduced-motion nothing is hidden at all.
 *
 * Import it in a <script setup> as `import { vReveal } from
 * '@/composables/useReveal'` — Vue picks it up as `v-reveal`.
 */
import type { Directive, DirectiveBinding } from 'vue';

type RevealOptions = {
    /** Extra delay in ms. */
    delay?: number;
    /** Position in a list: adds index × stagger (capped) to the delay. */
    index?: number;
    /** Travel distance in px (default 18). */
    y?: number;
};

type RevealValue = RevealOptions | number | undefined | null;

const STAGGER_MS = 70;
const MAX_STAGGER_STEPS = 6;
const FALLBACK_MS = 2500;

type State = { observer: IntersectionObserver | null; timer: number | null };

const states = new WeakMap<HTMLElement, State>();

function reducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

function options(value: RevealValue): RevealOptions {
    return typeof value === 'number' ? { delay: value } : (value ?? {});
}

function prepare(el: HTMLElement, opts: RevealOptions) {
    const steps = Math.min(opts.index ?? 0, MAX_STAGGER_STEPS);
    el.style.setProperty(
        '--fl-reveal-delay',
        `${(opts.delay ?? 0) + steps * STAGGER_MS}ms`,
    );

    if (opts.y !== undefined) {
        el.style.setProperty('--fl-reveal-y', `${opts.y}px`);
    }

    el.classList.add('fl-reveal');
}

function reveal(targets: HTMLElement[]) {
    targets.forEach((t) => t.classList.add('is-revealed'));
}

function mounted(el: HTMLElement, binding: DirectiveBinding<RevealValue>) {
    if (
        reducedMotion() ||
        typeof IntersectionObserver === 'undefined' ||
        el.classList.contains('is-revealed')
    ) {
        return;
    }

    const base = options(binding.value);
    const targets = binding.modifiers.stagger
        ? (Array.from(el.children) as HTMLElement[])
        : [el];

    targets.forEach((target, i) =>
        prepare(
            target,
            binding.modifiers.stagger ? { ...base, index: i } : base,
        ),
    );

    const state: State = { observer: null, timer: null };
    const done = () => {
        reveal(targets);
        state.observer?.disconnect();
        state.observer = null;

        if (state.timer !== null) {
            window.clearTimeout(state.timer);
            state.timer = null;
        }
    };

    state.observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                // Two frames so the hidden state paints before it animates.
                requestAnimationFrame(() => requestAnimationFrame(done));
            }
        },
        { threshold: 0.12, rootMargin: '0px 0px -6% 0px' },
    );
    state.observer.observe(el);
    state.timer = window.setTimeout(done, FALLBACK_MS);
    states.set(el, state);
}

function unmounted(el: HTMLElement) {
    const state = states.get(el);

    if (!state) {
        return;
    }

    state.observer?.disconnect();

    if (state.timer !== null) {
        window.clearTimeout(state.timer);
    }

    states.delete(el);
}

export const vReveal: Directive<HTMLElement, RevealValue> = {
    mounted,
    unmounted,
    // SSR: render nothing extra — visible by default.
    getSSRProps: () => ({}),
};
