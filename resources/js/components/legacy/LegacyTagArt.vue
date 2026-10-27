<script setup lang="ts">
/**
 * The physical Finisher Legacy Tag of a story / activation, drawn by the
 * shared TagVisualizer (illustrative render, no product photo invented).
 * Maps StoryPresenter's tag summary onto the visualizer's props.
 */
import { computed } from 'vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import type { TagSummary } from '@/types/legacy-tags';

const props = withDefaults(
    defineProps<{
        tag: TagSummary | null;
        tilt?: boolean;
    }>(),
    { tilt: true },
);

const collection = computed(() => props.tag?.collection ?? null);
const name = computed(
    () =>
        collection.value?.destination ??
        collection.value?.name ??
        props.tag?.product_name ??
        'Finisher Legacy',
);
const subtitle = computed(
    () =>
        [collection.value?.discipline, collection.value?.destination]
            .filter(Boolean)
            .join(' · ') || null,
);
const label = computed(
    () =>
        `Tu Finisher Legacy Tag${collection.value ? ` de la colección ${collection.value.name}` : ''}`,
);
</script>

<template>
    <TagVisualizer
        :name="name"
        :subtitle="subtitle"
        :year="collection?.year ?? null"
        :accent="collection?.accent_color ?? null"
        :tilt="tilt"
        :label="label"
    />
</template>
