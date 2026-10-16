<script setup lang="ts">
/**
 * Photos (and linked event videos) of a post — 1 big, 2 side by side,
 * 3 = one wide + two, 4 = grid. Lazy, with intrinsic sizes to avoid
 * layout shift.
 */
import { Link } from '@inertiajs/vue3';
import type { CommunityPost } from '@/types';

const props = defineProps<{
    media: CommunityPost['media'];
    authorName: string;
    /** Wrap images in a link to the post detail (feed cards). */
    href?: string | null;
}>();

function tileClass(index: number): string {
    const count = props.media.length;

    if (count === 1) {
        return 'max-h-[620px] min-h-48';
    }

    if (count === 3 && index === 0) {
        return 'col-span-2 aspect-[16/9]';
    }

    return 'aspect-square';
}
</script>

<template>
    <div
        class="grid gap-0.5 overflow-hidden bg-muted"
        :class="media.length === 1 ? 'grid-cols-1' : 'grid-cols-2'"
    >
        <template v-for="(item, index) in media" :key="item.url">
            <video
                v-if="item.type === 'video'"
                :src="item.url"
                controls
                playsinline
                preload="none"
                class="aspect-video w-full bg-black object-cover"
                :class="media.length === 3 && index === 0 ? 'col-span-2' : ''"
            />
            <component
                :is="href ? Link : 'div'"
                v-else
                :href="href ?? undefined"
                class="block overflow-hidden"
                :class="media.length === 3 && index === 0 ? 'col-span-2' : ''"
                :tabindex="href ? -1 : undefined"
            >
                <img
                    :src="item.url"
                    :alt="`Foto ${index + 1} de ${authorName}`"
                    loading="lazy"
                    decoding="async"
                    class="w-full object-cover transition-transform duration-500 ease-out hover:scale-[1.015] motion-reduce:transition-none motion-reduce:hover:scale-100"
                    :class="tileClass(index)"
                    :width="item.width ?? undefined"
                    :height="item.height ?? undefined"
                />
            </component>
        </template>
    </div>
</template>
