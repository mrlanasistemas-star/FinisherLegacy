<script setup lang="ts">
/**
 * One publication with its comments ("mensajes de apoyo"). Visibility,
 * who may comment and who may delete a comment are decided server-side
 * (LegacyMomentPolicy / LegacyMomentCommentPolicy).
 */
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import PostCard from '@/components/community/PostCard.vue';
import InputError from '@/components/InputError.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { timeAgo } from '@/lib/datetime';
import { login } from '@/routes';
import { index as communityIndex } from '@/routes/community';
import { destroy as destroyComment } from '@/routes/community/comments';
import { store as storeComment } from '@/routes/community/posts/comments';
import type { CommunityComment, CommunityPost } from '@/types';

const props = defineProps<{
    post: CommunityPost;
    comments: CommunityComment[];
    commentMax: number;
    canInteract: boolean;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const profile = computed(() => page.props.auth.profile);

const form = useForm({ body: '' });

function submit() {
    form.post(storeComment(props.post.uuid).url, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function remove(comment: CommunityComment) {
    router.visit(destroyComment(comment.uuid), { preserveScroll: true });
}

const description = computed(
    () =>
        props.post.caption?.slice(0, 160) ||
        `Publicación de ${props.post.author.name} en la comunidad de Finisher Legacy.`,
);
</script>

<template>
    <SeoHead
        :title="`${post.author.name} en la comunidad`"
        :description="description"
        :image="post.media[0]?.url ?? null"
        type="article"
        :noindex="post.visibility !== 'public'"
    />

    <div class="fl-container max-w-2xl py-8 sm:py-10">
        <Link
            :href="communityIndex()"
            class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Volver a la comunidad
        </Link>

        <div class="mt-6">
            <PostCard :post="post" detail />
        </div>

        <section class="mt-8" aria-labelledby="comentarios-title">
            <h2 id="comentarios-title" class="font-serif text-2xl">
                Mensajes de apoyo
                <span class="text-base text-muted-foreground">{{
                    comments.length
                }}</span>
            </h2>

            <form
                v-if="user && canInteract"
                class="mt-5 flex gap-3"
                @submit.prevent="submit"
            >
                <AthleteAvatar
                    :name="user.name"
                    :photo-url="profile?.photo_url"
                    size="sm"
                />
                <div class="flex-1">
                    <label for="comment-body" class="sr-only"
                        >Escribe un mensaje</label
                    >
                    <textarea
                        id="comment-body"
                        v-model="form.body"
                        rows="2"
                        :maxlength="commentMax"
                        placeholder="Escribe un mensaje de apoyo…"
                        class="w-full rounded-xl border border-input bg-card px-4 py-3 text-sm focus:border-foreground/30 focus:ring-2 focus:ring-ring/30 focus:outline-none"
                    />
                    <InputError :message="form.errors.body" />
                    <div class="mt-2 flex justify-end">
                        <Button
                            type="submit"
                            size="sm"
                            class="rounded-full px-5"
                            :disabled="form.processing || !form.body.trim()"
                        >
                            Comentar
                        </Button>
                    </div>
                </div>
            </form>
            <p
                v-else-if="!user"
                class="mt-5 rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground"
            >
                <Link
                    :href="login()"
                    class="font-semibold text-foreground underline underline-offset-4"
                    >Inicia sesión</Link
                >
                para dejar un mensaje de apoyo.
            </p>

            <ul class="mt-6 space-y-4">
                <li
                    v-for="comment in comments"
                    :key="comment.uuid"
                    class="flex gap-3"
                >
                    <Link
                        :href="
                            comment.author.username
                                ? `/@${comment.author.username}`
                                : '#'
                        "
                    >
                        <AthleteAvatar
                            :name="comment.author.name"
                            :photo-url="comment.author.photo_url"
                            size="sm"
                        />
                    </Link>
                    <div
                        class="min-w-0 flex-1 rounded-xl border border-border bg-card px-4 py-3"
                    >
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-semibold">
                                {{ comment.author.name }}
                            </p>
                            <time
                                :datetime="comment.created_at"
                                class="text-xs text-muted-foreground"
                                >{{ timeAgo(comment.created_at) }}</time
                            >
                            <button
                                v-if="comment.can_delete"
                                type="button"
                                class="ml-auto text-muted-foreground hover:text-destructive"
                                aria-label="Eliminar mensaje"
                                @click="remove(comment)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                        <p
                            class="mt-1 text-sm leading-relaxed whitespace-pre-line"
                        >
                            {{ comment.body }}
                        </p>
                    </div>
                </li>
            </ul>
            <p
                v-if="!comments.length"
                class="mt-6 text-sm text-muted-foreground"
            >
                Aún no hay mensajes. ¡Celebra este logro!
            </p>
        </section>
    </div>
</template>
