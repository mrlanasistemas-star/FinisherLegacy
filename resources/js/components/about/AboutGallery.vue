<script setup lang="ts">
import type { CompanyGalleryItem } from '@/types';

/** Admin-managed photos (company_gallery_items), lazy and aspect-locked. */
defineProps<{ items: CompanyGalleryItem[] }>();
</script>

<template>
    <ul class="columns-1 gap-4 sm:columns-2 lg:columns-3">
        <li
            v-for="item in items"
            :key="item.id"
            class="mb-4 break-inside-avoid overflow-hidden rounded-xl border border-border bg-card"
        >
            <figure>
                <img
                    :src="item.image_url"
                    :alt="item.title || 'Finisher Legacy'"
                    :width="item.width ?? undefined"
                    :height="item.height ?? undefined"
                    loading="lazy"
                    decoding="async"
                    class="h-auto w-full bg-muted object-cover"
                />
                <figcaption
                    v-if="item.title || item.description"
                    class="px-4 py-3"
                >
                    <p v-if="item.title" class="text-sm font-semibold">
                        {{ item.title }}
                    </p>
                    <p
                        v-if="item.description"
                        class="mt-0.5 text-sm text-muted-foreground"
                    >
                        {{ item.description }}
                    </p>
                </figcaption>
            </figure>
        </li>
    </ul>
</template>
