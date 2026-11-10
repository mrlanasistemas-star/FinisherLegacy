<script setup lang="ts">
/**
 * Accessible FAQ accordion (native <details>/<summary>: keyboard and
 * screen-reader friendly without JS). The answer eases in when opened;
 * static under prefers-reduced-motion.
 */
import { Plus } from '@lucide/vue';
import { vReveal } from '@/composables/useReveal';

defineProps<{
    items: { q: string; a: string }[];
}>();
</script>

<template>
    <ul class="divide-y divide-border border-y border-border">
        <li v-for="(item, index) in items" :key="item.q" v-reveal="{ index }">
            <details class="mk-faq group">
                <summary
                    class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-6 py-4 font-serif text-[1.05rem] font-medium text-foreground transition-colors hover:text-fl-gold-ink sm:text-lg [&::-webkit-details-marker]:hidden"
                >
                    {{ item.q }}
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-full border border-border bg-white text-fl-gold-ink transition-transform duration-300 group-open:rotate-45 motion-reduce:transition-none"
                        aria-hidden="true"
                    >
                        <Plus class="size-4" />
                    </span>
                </summary>
                <p
                    class="mk-faq__answer max-w-2xl pr-10 pb-5 text-[0.95rem] leading-relaxed text-muted-foreground"
                >
                    {{ item.a }}
                </p>
            </details>
        </li>
    </ul>
</template>

<style scoped>
.mk-faq[open] .mk-faq__answer {
    animation: mk-faq-in 360ms cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes mk-faq-in {
    from {
        opacity: 0;
        transform: translate3d(0, -6px, 0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .mk-faq[open] .mk-faq__answer {
        animation: none;
    }
}
</style>
