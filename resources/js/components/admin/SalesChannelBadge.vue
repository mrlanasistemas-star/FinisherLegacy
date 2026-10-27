<script setup lang="ts">
import { channelBadge, channelLabel, channelShort } from '@/lib/salesChannel';

withDefaults(
    defineProps<{ channel: string | null | undefined; short?: boolean }>(),
    { short: false },
);
</script>

<template>
    <span
        class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap"
        :class="channelBadge(channel)"
        :title="channelLabel(channel)"
    >
        {{ short ? channelShort(channel) : channelLabel(channel) }}
    </span>
</template>
