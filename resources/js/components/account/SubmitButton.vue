<script setup lang="ts">
/**
 * Primary submit for the account forms: the brand black pill with the gold
 * sheen on hover, a spinner + "working" label while processing, and the
 * button stays the same width so nothing jumps.
 */
import { ArrowRight } from '@lucide/vue';
import { Spinner } from '@/components/ui/spinner';

withDefaults(
    defineProps<{
        processing?: boolean;
        label: string;
        processingLabel?: string;
        arrow?: boolean;
        block?: boolean;
    }>(),
    {
        processing: false,
        processingLabel: 'Un momento…',
        arrow: true,
        block: true,
    },
);
</script>

<template>
    <button
        type="submit"
        class="fl-btn-primary group focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-wait disabled:opacity-80"
        :class="block ? 'h-13 w-full' : ''"
        :disabled="processing"
        :aria-busy="processing"
    >
        <Spinner v-if="processing" class="size-[18px]" />
        <span>{{ processing ? processingLabel : label }}</span>
        <ArrowRight
            v-if="arrow && !processing"
            class="size-4 transition-transform duration-300 group-hover:translate-x-0.5"
            aria-hidden="true"
        />
    </button>
</template>
