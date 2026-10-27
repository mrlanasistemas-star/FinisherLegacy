import { useIntersectionObserver } from '@vueuse/core';
import type { MaybeRefOrGetter } from 'vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

/**
 * `true` once the element has entered the viewport (one-shot) — the
 * trigger for the public pages' story motion (tag appears, phone
 * approaches, NFC pulse…). Content must never depend on it: with reduced
 * motion, no observer support or a silent observer, it flips to `true`
 * (immediately / after a bounded fallback), mirroring <Reveal>.
 */
export function useInView(
    target: MaybeRefOrGetter<HTMLElement | null | undefined>,
    options: { threshold?: number; fallbackMs?: number } = {},
) {
    const inView = ref(false);
    const reducedMotion = useReducedMotion();
    let timer: ReturnType<typeof setTimeout> | null = null;

    const { stop } = useIntersectionObserver(
        target,
        ([entry]) => {
            if (entry?.isIntersecting) {
                inView.value = true;
                stop();
            }
        },
        { threshold: options.threshold ?? 0.3 },
    );

    onMounted(() => {
        if (reducedMotion.value || !('IntersectionObserver' in window)) {
            inView.value = true;

            return;
        }

        timer = setTimeout(() => {
            inView.value = true;
        }, options.fallbackMs ?? 6000);
    });

    onBeforeUnmount(() => {
        if (timer !== null) {
            clearTimeout(timer);
        }
    });

    return { inView, reducedMotion };
}
