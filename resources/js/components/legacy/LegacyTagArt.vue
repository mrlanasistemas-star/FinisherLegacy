<script setup lang="ts">
/**
 * The physical Finisher Legacy Tag of a story / activation, drawn by the
 * shared TagVisualizer (illustrative render, no product photo invented).
 * Maps StoryPresenter's tag summary onto the visualizer's props.
 */
import { computed } from 'vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { useI18n } from '@/i18n';
import type { TagSummary } from '@/types/legacy-tags';

const props = withDefaults(
    defineProps<{
        tag: TagSummary | null;
        tilt?: boolean;
    }>(),
    { tilt: true },
);

const { t } = useI18n();

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
const label = computed(() =>
    collection.value
        ? t('Tu Finisher Legacy Tag de la colección {name}', {
              name: collection.value.name,
          })
        : t('Tu Finisher Legacy Tag'),
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
