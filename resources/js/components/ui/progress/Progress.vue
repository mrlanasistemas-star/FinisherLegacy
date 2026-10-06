<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import {
    ProgressIndicator,
    ProgressRoot,
    type ProgressRootProps,
} from 'reka-ui';
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<
    ProgressRootProps & { class?: HTMLAttributes['class'] }
>();

const delegatedProps = computed(() => {
    const { class: _, ...delegated } = props;
    return delegated;
});
</script>

<template>
    <ProgressRoot
        data-slot="progress"
        v-bind="delegatedProps"
        :class="
            cn(
                'bg-muted relative h-2 w-full overflow-hidden rounded-full',
                props.class,
            )
        "
    >
        <ProgressIndicator
            data-slot="progress-indicator"
            class="h-full w-full flex-1 rounded-full bg-fl-gold transition-transform duration-500 ease-out"
            :style="`transform: translateX(-${100 - (props.modelValue ?? 0)}%)`"
        />
    </ProgressRoot>
</template>
