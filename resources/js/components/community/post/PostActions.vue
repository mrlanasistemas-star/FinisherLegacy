<script setup lang="ts">
/**
 * Celebrar · Comentar · Compartir. Celebrar is optimistic (flips and
 * counts instantly, rolls back on error) and reloads no page props, so an
 * infinite feed never jumps. Share uses the Web Share API when present,
 * otherwise copies the link.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import { MessageCircle, PartyPopper, Share2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { login } from '@/routes';
import { celebrate, show, uncelebrate } from '@/routes/community/posts';
import type { CommunityPost } from '@/types';

const props = defineProps<{ post: CommunityPost }>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);

const celebrated = ref(props.post.my_reactions.length > 0);
const count = ref(props.post.reactions.total ?? props.post.reactions.cheer);
const busy = ref(false);
const burst = ref(false);

watch(
    () => props.post,
    (post) => {
        celebrated.value = post.my_reactions.length > 0;
        count.value = post.reactions.total ?? post.reactions.cheer;
    },
);

function toggle() {
    if (busy.value) {
        return;
    }

    const was = celebrated.value;
    celebrated.value = !was;
    count.value = Math.max(0, count.value + (was ? -1 : 1));
    burst.value = !was;
    busy.value = true;

    router.visit(
        was ? uncelebrate(props.post.uuid) : celebrate(props.post.uuid),
        {
            preserveScroll: true,
            preserveState: true,
            // No page prop depends on this — keep the feed exactly as it is.
            only: ['__reaction'],
            onError: () => {
                celebrated.value = was;
                count.value = Math.max(0, count.value + (was ? 1 : -1));
                toast.error(
                    'No pudimos registrar tu celebración. Intenta de nuevo.',
                );
            },
            onFinish: () => {
                busy.value = false;
                window.setTimeout(() => (burst.value = false), 500);
            },
        },
    );
}

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
    <footer
        class="flex items-center gap-1 border-t border-border px-2 py-1.5 sm:px-3"
    >
        <component
            :is="isGuest ? Link : 'button'"
            :href="isGuest ? login().url : undefined"
            :type="isGuest ? undefined : 'button'"
            class="inline-flex min-h-11 items-center gap-2 rounded-full px-3 text-sm font-medium transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            :class="celebrated ? 'text-fl-gold-ink' : 'text-muted-foreground'"
            :aria-pressed="isGuest ? undefined : celebrated"
            :aria-label="celebrated ? 'Quitar celebración' : 'Celebrar'"
            @click="isGuest ? undefined : toggle()"
        >
            <PartyPopper
                class="size-[18px] transition-transform"
                :class="[
                    celebrated ? 'fill-fl-gold/30' : '',
                    burst ? 'fl-celebrate-pop' : '',
                ]"
            />
            <span>Celebrar</span>
            <span
                v-if="count > 0"
                class="legacy-numeric text-xs tabular-nums"
                >{{ count }}</span
            >
        </component>

        <Link
            :href="`${show(post.uuid).url}#comentarios`"
            class="inline-flex min-h-11 items-center gap-2 rounded-full px-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted"
        >
            <MessageCircle class="size-[18px]" />
            <span>Comentar</span>
            <span
                v-if="post.comments_count > 0"
                class="legacy-numeric text-xs"
                >{{ post.comments_count }}</span
            >
        </Link>

        <button
            type="button"
            class="ml-auto inline-flex min-h-11 items-center gap-2 rounded-full px-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted"
            aria-label="Compartir publicación"
            @click="share"
        >
            <Share2 class="size-[18px]" />
            <span class="hidden sm:inline">Compartir</span>
        </button>
    </footer>
</template>

<style scoped>
.fl-celebrate-pop {
    animation: fl-celebrate-pop 0.45s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes fl-celebrate-pop {
    0% {
        transform: scale(1) rotate(0);
    }
    45% {
        transform: scale(1.3) rotate(-12deg);
    }
    100% {
        transform: scale(1) rotate(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-celebrate-pop {
        animation: none;
    }
}
</style>
