<script setup lang="ts">
/**
 * Page header for the catalog / inventory / tags pages — same anatomy as
 * Comercio → Inventario: gold eyebrow, title with a cream icon tile, one
 * line of context and the page actions on the right (stacked on phones).
 */
import type { Component } from 'vue';

defineProps<{
    eyebrow: string;
    title: string;
    description?: string;
    icon: Component;
}>();
</script>

<template>
    <header
        class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="flex min-w-0 items-center gap-3">
            <span
                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
            >
                <component :is="icon" class="size-5" />
            </span>
            <div class="min-w-0">
                <p class="fl-eyebrow">{{ eyebrow }}</p>
                <h1 class="truncate text-xl font-bold text-foreground">
                    <slot name="title">{{ title }}</slot>
                </h1>
                <p v-if="description" class="text-sm text-muted-foreground">
                    {{ description }}
                </p>
            </div>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
