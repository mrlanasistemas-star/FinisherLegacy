/**
 * Visibility of the mobile sticky buy bar on product pages (visual only —
 * it never touches cart logic). The bar shows once the page's own buy
 * button has scrolled out of view and hides again when the site footer
 * comes in, so it never covers either. While it is up, `--fl-bottom-bar`
 * lifts the floating buttons (BackToTop, guide) above it.
 */
import { useMediaQuery } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { Ref } from 'vue';

export function useStickyBuyBar(
    anchor: Ref<HTMLElement | null>,
    enabled: Ref<boolean>,
) {
    const anchorVisible = ref(true);
    const footerVisible = ref(false);
    const belowLg = useMediaQuery('(max-width: 1023.98px)');
    let observer: IntersectionObserver | null = null;
    let footer: Element | null = null;

    const visible = computed(
        () => enabled.value && !anchorVisible.value && !footerVisible.value,
    );

    onMounted(() => {
        observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.target === footer) {
                    footerVisible.value = entry.isIntersecting;
                } else {
                    anchorVisible.value = entry.isIntersecting;
                }
            }
        });

        footer = document.querySelector('footer');

        if (footer) {
            observer.observe(footer);
        }

        watch(
            anchor,
            (el, previous) => {
                if (previous) {
                    observer?.unobserve(previous);
                }

                if (el) {
                    observer?.observe(el);
                }
            },
            { immediate: true },
        );

        watch(
            [visible, belowLg],
            () =>
                document.documentElement.style.setProperty(
                    '--fl-bottom-bar',
                    visible.value && belowLg.value ? '72px' : '0px',
                ),
            { immediate: true },
        );
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
        document.documentElement.style.removeProperty('--fl-bottom-bar');
    });

    return { visible };
}
