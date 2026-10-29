<script setup lang="ts">
/**
 * The 3-column square grid of a profile's posts (Instagram). Hover shows
 * celebrations + comments; a stacked icon marks multi-photo posts. On
 * desktop a tile opens the post in a modal (the full card: carousel,
 * celebrar, comments preview); on phones it goes to the post page.
 * Text-only posts become editorial cream tiles — real caption / time.
 */
import { router } from '@inertiajs/vue3';
import { Copy, Heart, MessageCircle, Timer } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref } from 'vue';
import CommunityPost from '@/components/community/CommunityPost.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { show as showPost } from '@/routes/community/posts';
import type { CommunityPost as CommunityPostData } from '@/types';

const props = defineProps<{ posts: CommunityPostData[] }>();

const emit = defineEmits<{ deleted: [uuid: string] }>();

const isDesktop = useMediaQuery('(min-width: 1024px)');
const openUuid = ref<string | null>(null);
const current = computed(
    () => props.posts.find((post) => post.uuid === openUuid.value) ?? null,
);
const open = computed({
    get: () => current.value !== null,
    set: (value: boolean) => {
        if (!value) {
            openUuid.value = null;
        }
    },
});

function cover(post: CommunityPostData): string | null {
    return (
        post.media.find((item) => item.type !== 'video')?.url ??
        post.medal?.image_url ??
        null
    );
}

function onTile(event: MouseEvent, post: CommunityPostData) {
    if (
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.button !== 0
    ) {
        return;
    }

    event.preventDefault();

    if (isDesktop.value) {
        openUuid.value = post.uuid;

        return;
    }

    router.visit(showPost(post.uuid));
}

function onDeleted(uuid: string) {
    openUuid.value = null;
    emit('deleted', uuid);
}
</script>

<template>
    <div class="grid grid-cols-3 gap-0.5 sm:gap-1">
        <a
            v-for="post in posts"
            :key="post.uuid"
            :href="showPost(post.uuid).url"
            class="group/tile relative block aspect-square overflow-hidden bg-fl-cream focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
            :aria-label="`Publicación de ${post.author.name}: ${post.kind_label}`"
            @click="onTile($event, post)"
        >
            <img
                v-if="cover(post)"
                :src="cover(post)!"
                alt=""
                loading="lazy"
                decoding="async"
                class="size-full object-cover"
            />
            <span
                v-else
                class="flex size-full flex-col justify-between bg-gradient-to-br from-fl-cream to-fl-paper p-3 sm:p-4"
            >
                <span
                    class="text-[10px] font-semibold tracking-[0.14em] text-fl-gold-ink uppercase sm:text-[11px]"
                    >{{ post.kind_label }}</span
                >
                <span v-if="post.activity?.official_time" class="flex flex-col">
                    <span
                        class="legacy-numeric font-serif text-lg leading-none sm:text-3xl"
                        >{{ post.activity.official_time }}</span
                    >
                    <span
                        class="mt-1 inline-flex items-center gap-1 truncate text-[10px] text-muted-foreground sm:text-xs"
                    >
                        <Timer class="size-3 shrink-0" aria-hidden="true" />
                        {{ post.activity.event ?? 'Tiempo oficial' }}
                    </span>
                </span>
                <span
                    v-else
                    class="line-clamp-4 font-serif text-xs leading-snug sm:text-base"
                    >{{ post.caption }}</span
                >
            </span>

            <Copy
                v-if="post.media.length > 1"
                class="absolute top-2 right-2 size-4 rotate-180 text-white drop-shadow-[0_1px_3px_rgb(0_0_0/0.5)] sm:size-5"
                aria-label="Varias fotos"
            />

            <span
                class="absolute inset-0 hidden items-center justify-center gap-6 bg-black/35 text-sm font-semibold text-white opacity-0 transition-opacity group-hover/tile:opacity-100 sm:flex"
                aria-hidden="true"
            >
                <span class="inline-flex items-center gap-1.5">
                    <Heart class="size-5 fill-white" />
                    {{ post.reactions.total }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <MessageCircle class="size-5 -scale-x-100 fill-white" />
                    {{ post.comments_count }}
                </span>
            </span>
        </a>
    </div>

    <Dialog v-model:open="open">
        <DialogContent
            :show-close-button="false"
            class="max-h-[92vh] overflow-y-auto rounded-card border-0 bg-transparent p-0 shadow-none sm:max-w-[560px]"
        >
            <DialogTitle class="sr-only">
                Publicación de {{ current?.author.name }}
            </DialogTitle>
            <DialogDescription class="sr-only">
                Detalle de la publicación con sus celebraciones y comentarios.
            </DialogDescription>
            <CommunityPost
                v-if="current"
                :key="current.uuid"
                :post="current"
                class="shadow-fl-overlay"
                @deleted="onDeleted"
            />
        </DialogContent>
    </Dialog>
</template>
