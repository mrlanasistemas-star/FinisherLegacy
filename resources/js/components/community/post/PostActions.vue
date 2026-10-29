<script setup lang="ts">
/**
 * Celebrar (heart) · Comentar · Compartir — the Instagram action row,
 * plus the celebrations count. The celebrate state lives in
 * useCelebrate() (shared with the double-tap on the photos). Share uses
 * the Web Share API when present, otherwise copies the link.
 */
import { Link } from '@inertiajs/vue3';
import { Heart, MessageCircle, Send } from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { show } from '@/routes/community/posts';
import type { CommunityPost } from '@/types';
import type { Celebration } from './useCelebrate';

const props = defineProps<{
    post: CommunityPost;
    celebration: Celebration;
    /** Detail page: "Comentar" focuses the box instead of navigating. */
    detail?: boolean;
}>();

const emit = defineEmits<{ comment: [] }>();

const pop = ref(false);

watch(
    () => props.celebration.burst.value,
    () => {
        pop.value = false;
        requestAnimationFrame(() => (pop.value = true));
        window.setTimeout(() => (pop.value = false), 500);
    },
);

async function share() {
    const url =
        props.post.share_url ||
        new URL(show(props.post.uuid).url, window.location.origin).href;

    if (typeof navigator.share === 'function') {
        try {
            await navigator.share({
                title: `${props.post.author.name} en Finisher Legacy`,
                url,
            });

            return;
        } catch (error) {
            if ((error as DOMException)?.name === 'AbortError') {
                return;
            }
        }
    }

    try {
        await navigator.clipboard.writeText(url);
        toast.success('Enlace copiado.');
    } catch {
        toast.error('No pudimos copiar el enlace.');
    }
}
</script>

<template>
    <div>
        <div class="-ml-2 flex items-center gap-0.5">
            <button
                type="button"
                class="flex size-10 items-center justify-center rounded-full transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-90"
                :aria-pressed="celebration.celebrated.value"
                :aria-label="celebration.label.value"
                :title="
                    celebration.celebrated.value
                        ? 'Quitar celebración'
                        : 'Celebrar'
                "
                @click="celebration.toggle()"
            >
                <Heart
                    class="size-6 transition-colors"
                    :class="[
                        celebration.celebrated.value
                            ? 'fill-rose-500 text-rose-500'
                            : 'text-foreground',
                        pop ? 'fl-heart-pop' : '',
                    ]"
                    aria-hidden="true"
                />
            </button>

            <button
                v-if="detail"
                type="button"
                class="flex size-10 items-center justify-center rounded-full transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Comentar"
                @click="emit('comment')"
            >
                <MessageCircle class="size-6 -scale-x-100" aria-hidden="true" />
            </button>
            <Link
                v-else
                :href="`${show(post.uuid).url}#comentarios`"
                class="flex size-10 items-center justify-center rounded-full transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :aria-label="
                    post.comments_count > 0
                        ? `Comentar · ${post.comments_count} ${post.comments_count === 1 ? 'comentario' : 'comentarios'}`
                        : 'Comentar'
                "
            >
                <MessageCircle class="size-6 -scale-x-100" aria-hidden="true" />
            </Link>

            <button
                type="button"
                class="flex size-10 items-center justify-center rounded-full transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-label="Compartir publicación"
                title="Compartir"
                @click="share"
            >
                <Send class="size-[22px]" aria-hidden="true" />
            </button>
        </div>

        <p
            class="mt-1 text-sm font-semibold text-foreground"
            aria-live="polite"
        >
            <template v-if="celebration.count.value > 0">
                <span class="legacy-numeric">{{
                    celebration.count.value.toLocaleString('es-MX')
                }}</span>
                {{
                    celebration.count.value === 1
                        ? 'celebración'
                        : 'celebraciones'
                }}
            </template>
            <span v-else class="font-normal text-muted-foreground"
                >Sé el primero en celebrarlo</span
            >
        </p>
    </div>
</template>

<style scoped>
.fl-heart-pop {
    animation: fl-heart-pop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes fl-heart-pop {
    0% {
        transform: scale(1);
    }
    40% {
        transform: scale(1.3);
    }
    100% {
        transform: scale(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-heart-pop {
        animation: none;
    }
}
</style>
