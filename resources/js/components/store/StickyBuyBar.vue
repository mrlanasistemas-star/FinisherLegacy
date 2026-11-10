<script setup lang="ts">
/**
 * Mobile sticky buy bar shell for product pages: product/variant label and
 * price on the left (`default` slot = price), the action on the right
 * (`action` slot). Slides up from the bottom, respects the home-indicator
 * safe area and is hidden from lg up. Visibility comes from
 * useStickyBuyBar; this component holds no purchase logic.
 */
defineProps<{
    visible: boolean;
    label?: string | null;
}>();
</script>

<template>
    <div
        class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-white/95 px-4 pt-3 shadow-[0_-10px_30px_-18px_rgb(11_11_12/0.35)] backdrop-blur-xl transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] motion-reduce:transition-none lg:hidden"
        :class="visible ? 'translate-y-0' : 'translate-y-[110%]'"
        :inert="!visible"
        :aria-hidden="!visible"
        style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))"
    >
        <div class="mx-auto flex max-w-xl items-center gap-3">
            <div class="min-w-0 flex-1">
                <p v-if="label" class="truncate text-xs text-muted-foreground">
                    {{ label }}
                </p>
                <p class="font-serif text-lg leading-tight font-semibold">
                    <slot />
                </p>
            </div>
            <slot name="action" />
        </div>
    </div>
</template>
