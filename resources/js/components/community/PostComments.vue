<script setup lang="ts">
/**
 * The comment thread of a post. Instagram rows (avatar + bold username +
 * text, time below, hover-revealed actions). Who may comment and who may
 * delete each comment is decided server-side. `split` (post detail on
 * desktop) puts the list in a scroll area and the box at the bottom.
 * Enter sends (Shift+Enter = new line).
 */
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import { EyeOff, Flag, Trash2 } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { toast } from 'vue-sonner';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import InputError from '@/components/InputError.vue';
import { useAuthGate } from '@/composables/useAuthGate';
import { useCommunityReport } from '@/composables/useCommunityReport';
import { timeAgo } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';
import { destroy as destroyComment } from '@/routes/community/comments';
import { store as storeComment } from '@/routes/community/posts/comments';
import type { CommunityComment } from '@/types';

const props = withDefaults(
    defineProps<{
        postUuid: string;
        comments: CommunityComment[];
        commentMax: number;
        canInteract: boolean;
        split?: boolean;
    }>(),
    { split: false },
);

const page = usePage();
const user = computed(() => page.props.auth.user);
const profile = computed(() => page.props.auth.profile);
const { openReport } = useCommunityReport();
const { loginUrl } = useAuthGate();

const form = useForm({ body: '' });
const input = ref<HTMLTextAreaElement | null>(null);
const list = ref<HTMLElement | null>(null);

function submit() {
    if (!form.body.trim() || form.processing) {
        return;
    }

    form.post(storeComment(props.postUuid).url, {
        preserveScroll: true,
        only: ['comments', 'post'],
        onSuccess: () => {
            form.reset();
            nextTick(() =>
                list.value?.scrollTo({
                    top: list.value.scrollHeight,
                    behavior: 'smooth',
                }),
            );
        },
        onError: (errors) => {
            if (!errors.body) {
                toast.error('No pudimos publicar tu comentario.');
            }
        },
    });
}

function onEnter(event: KeyboardEvent) {
    if (event.shiftKey || event.isComposing) {
        return;
    }

    event.preventDefault();
    submit();
}

async function remove(comment: CommunityComment) {
    if (
        !(await confirmAction({
            title: '¿Eliminar este comentario?',
            confirmButtonText: 'Eliminar',
        }))
    ) {
        return;
    }

    router.visit(destroyComment(comment.uuid), {
        preserveScroll: true,
        only: ['comments', 'post'],
        onError: () => toast.error('No pudimos eliminar el comentario.'),
    });
}

function focus() {
    input.value?.focus();
}

defineExpose({ focus });
</script>

<template>
    <div :class="split ? 'flex min-h-0 flex-1 flex-col' : ''">
        <div
            ref="list"
            :class="split ? 'min-h-0 flex-1 overflow-y-auto px-4 py-4' : ''"
        >
            <TransitionGroup
                tag="ul"
                class="space-y-4"
                enter-active-class="transition duration-300 ease-out motion-reduce:transition-none"
                enter-from-class="translate-y-1 opacity-0"
                leave-active-class="transition duration-200 ease-in motion-reduce:transition-none"
                leave-to-class="opacity-0"
            >
                <li
                    v-for="comment in comments"
                    :key="comment.uuid"
                    class="group flex gap-3"
                >
                    <Link
                        :href="
                            comment.author.username
                                ? `/@${comment.author.username}`
                                : '#'
                        "
                        class="shrink-0 rounded-full"
                        :aria-label="`Perfil de ${comment.author.name}`"
                    >
                        <AthleteAvatar
                            :name="comment.author.name"
                            :photo-url="comment.author.photo_url"
                            size="sm"
                        />
                    </Link>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-sm leading-relaxed break-words whitespace-pre-line"
                        >
                            <Link
                                v-if="comment.author.username"
                                :href="`/@${comment.author.username}`"
                                class="mr-1.5 font-semibold hover:opacity-70"
                                >{{ comment.author.username }}</Link
                            ><span v-else class="mr-1.5 font-semibold">{{
                                comment.author.name
                            }}</span
                            >{{ comment.body }}
                        </p>
                        <p
                            v-if="comment.is_hidden"
                            class="mt-1 flex items-center gap-1 text-xs text-amber-800"
                        >
                            <EyeOff class="size-3" /> Oculto por moderación:
                            solo tú lo ves.
                        </p>
                        <div
                            class="mt-1 flex items-center gap-3 text-xs text-muted-foreground"
                        >
                            <time :datetime="comment.created_at">{{
                                timeAgo(comment.created_at)
                            }}</time>
                            <button
                                v-if="user && !comment.can_delete"
                                type="button"
                                class="inline-flex items-center gap-1 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100 hover:text-foreground focus-visible:opacity-100"
                                @click="
                                    openReport({
                                        type: 'comment',
                                        id: comment.uuid,
                                    })
                                "
                            >
                                <Flag class="size-3" /> Reportar
                            </button>
                            <button
                                v-if="comment.can_delete"
                                type="button"
                                class="inline-flex items-center gap-1 opacity-0 transition-opacity group-focus-within:opacity-100 group-hover:opacity-100 hover:text-destructive focus-visible:opacity-100"
                                @click="remove(comment)"
                            >
                                <Trash2 class="size-3" /> Eliminar
                            </button>
                        </div>
                    </div>
                </li>
            </TransitionGroup>
            <div
                v-if="!comments.length"
                class="py-8 text-center"
                :class="split ? 'flex h-full flex-col justify-center' : ''"
            >
                <p class="font-semibold">Aún no hay comentarios.</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Inicia la conversación y celebra este logro.
                </p>
            </div>
        </div>

        <div :class="split ? 'border-t border-border px-4 py-3' : 'mt-6'">
            <form
                v-if="user && canInteract"
                class="flex items-start gap-3"
                @submit.prevent="submit"
            >
                <AthleteAvatar
                    :name="user.name"
                    :photo-url="profile?.photo_url"
                    size="sm"
                />
                <div class="min-w-0 flex-1">
                    <label for="comment-body" class="sr-only"
                        >Añade un comentario</label
                    >
                    <div class="flex items-end gap-2">
                        <textarea
                            id="comment-body"
                            ref="input"
                            v-model="form.body"
                            rows="1"
                            :maxlength="commentMax"
                            placeholder="Añade un comentario…"
                            class="field-sizing-content max-h-32 min-h-9 w-full resize-none bg-transparent py-2 text-sm placeholder:text-muted-foreground focus:outline-none"
                            aria-describedby="comment-body-error"
                            @keydown.enter="onEnter"
                        />
                        <button
                            type="submit"
                            class="min-h-9 shrink-0 text-sm font-semibold text-fl-gold-ink hover:text-foreground disabled:opacity-40"
                            :disabled="form.processing || !form.body.trim()"
                        >
                            Publicar
                        </button>
                    </div>
                    <InputError
                        id="comment-body-error"
                        :message="form.errors.body"
                    />
                </div>
            </form>
            <p v-else-if="!user" class="text-sm text-muted-foreground">
                <Link
                    :href="loginUrl()"
                    class="font-semibold text-foreground underline underline-offset-4"
                    >Inicia sesión</Link
                >
                para comentar y celebrar.
            </p>
        </div>
    </div>
</template>
