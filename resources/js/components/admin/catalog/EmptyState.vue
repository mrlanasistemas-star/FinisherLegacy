<script setup lang="ts">
import { Inbox } from '@lucide/vue';
import type { Component } from 'vue';

withDefaults(
    defineProps<{ title?: string; text?: string; icon?: Component }>(),
    {
        title: 'Sin resultados',
        text: 'Ajusta la búsqueda o los filtros.',
        icon: () => Inbox,
    },
);
</script>

<template>
    <div
        class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-border bg-card/60 px-6 py-14 text-center"
    >
        <span
            class="flex size-12 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
        >
            <component :is="icon" class="size-5" />
        </span>
        <p class="font-semibold">{{ title }}</p>
        <p class="max-w-sm text-sm text-muted-foreground">{{ text }}</p>
        <div v-if="$slots.default" class="mt-1"><slot /></div>
    </div>
</template>
