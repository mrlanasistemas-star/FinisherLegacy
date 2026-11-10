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
import { useI18n } from '@/i18n';
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

const { t, regional } = useI18n();

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
                title: t('{name} en Finisher Legacy', {
                    name: props.post.author.name,
                }),
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
        toast.success(t('Enlace copiado.'));
    } catch {
        toast.error(t('No pudimos copiar el enlace.'));
    }
}
</script>

<template>
    <div>
        <div class="-ml-2 flex items-center gap-0.5">
            <button
                type="button"
                class="relative flex size-10 items-center justify-center rounded-full transition-[background-color,transform] duration-150 hover:bg-muted focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none active:scale-90"
                :aria-pressed="celebration.celebrated.value"
                :aria-label="celebration.label.value"
                :title="
                    celebration.celebrated.value
                        ? $t('Quitar celebración')
                        : $t('Celebrar')
                "
                @click="celebration.toggle()"
            >
                <span
                    v-if="pop && celebration.celebrated.value"
                    class="fl-heart-ring pointer-events-none absolute inset-0 m-auto size-6 rounded-full"
                    aria-hidden="true"
                />
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
                :aria-label="$t('Comentar')"
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
                        ? $tc(
                              'Comentar · {count} comentario|Comentar · {count} comentarios',
                              post.comments_count,
                          )
                        : $t('Comentar')
                "
            >
                <MessageCircle class="size-6 -scale-x-100" aria-hidden="true" />
            </Link>

            <button
                type="button"
                class="flex size-10 items-center justify-center rounded-full transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :aria-label="$t('Compartir publicación')"
                :title="$t('Compartir')"
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
                    celebration.count.value.toLocaleString(regional)
                }}</span>
                {{ $tc('celebración|celebraciones', celebration.count.value) }}
            </template>
            <span v-else class="font-normal text-muted-foreground">{{
                $t('Sé el primero en celebrarlo')
            }}</span>
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

/* Ring that bursts out of the heart when it turns on. */
.fl-heart-ring {
    border: 2px solid rgb(244 63 94 / 0.55);
    animation: fl-heart-ring 0.55s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fl-heart-ring {
    from {
        transform: scale(0.6);
        opacity: 1;
    }
    to {
        transform: scale(2.1);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-heart-pop,
    .fl-heart-ring {
        animation: none;
    }
    .fl-heart-ring {
        display: none;
    }
}
</style>
