<script setup lang="ts">
import { tagStatusClass, tagStatusDot, tagStatusLabel } from '@/lib/tagStatus';

defineProps<{ status: string }>();
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :class="tagStatusClass(status)"
    >
        <span class="size-1.5 rounded-full" :class="tagStatusDot(status)" />
        {{ tagStatusLabel(status) }}
    </span>
</template>
