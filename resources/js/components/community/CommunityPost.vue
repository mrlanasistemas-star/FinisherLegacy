<script setup lang="ts">
/**
 * One Comunidad post, Instagram-style: author row, full-width media
 * carousel (double-tap to celebrar), action row, celebrations, caption,
 * latest comments + a quick comment box, timestamp. The structured
 * body (race result, Legacy Plate, gear, training numbers) follows
 * `post.kind` (spec §54). Who may see it was decided server-side
 * (SocialVisibility); the menu only offers what the viewer may do
 * (owner: visibility/delete — others: report).
 */
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    EyeOff,
    Flag,
    Globe2,
    Link2,
    Lock,
    MoreHorizontal,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref, toRef, watch } from 'vue';
import { toast } from 'vue-sonner';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import PostActions from '@/components/community/post/PostActions.vue';
import PostGearHighlight from '@/components/community/post/PostGearHighlight.vue';
import PostLegacyPlate from '@/components/community/post/PostLegacyPlate.vue';
import PostMediaCarousel from '@/components/community/post/PostMediaCarousel.vue';
import PostMomentDetails from '@/components/community/post/PostMomentDetails.vue';
import PostRaceResult from '@/components/community/post/PostRaceResult.vue';
import { useCelebrate } from '@/components/community/post/useCelebrate';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuthGate } from '@/composables/useAuthGate';
import { useCommunityReport } from '@/composables/useCommunityReport';
import { shortDate, timeAgo } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';
import { destroy, show, update } from '@/routes/community/posts';
import { store as storeComment } from '@/routes/community/posts/comments';
import type { CommentPreview, CommunityPost, MomentVisibility } from '@/types';

const props = withDefaults(
    defineProps<{
        post: CommunityPost;
        /** Detail page: no comment preview, delete goes back to the feed. */
        detail?: boolean;
        /** Split detail renders the media column separately — hide it here. */
        hideMedia?: boolean;
        /** Shared celebrate state (detail page passes its own). */
        celebration?: ReturnType<typeof useCelebrate>;
    }>(),
    { detail: false, hideMedia: false, celebration: undefined },
);

const emit = defineEmits<{ deleted: [uuid: string]; comment: [] }>();

const page = usePage();
const isSignedIn = computed(() => !!page.props.auth.user);
const { requireAuth } = useAuthGate();
const { openReport } = useCommunityReport();
const celebration = props.celebration ?? useCelebrate(toRef(props, 'post'));

const visibilityMeta: Record<
    MomentVisibility,
    { label: string; icon: typeof Globe2 }
> = {
    public: { label: 'Público', icon: Globe2 },
    followers: { label: 'Seguidores', icon: Users },
    private: { label: 'Solo yo', icon: Lock },
};

const authorHref = computed(() =>
    props.post.author.username ? `/@${props.post.author.username}` : null,
);
const detailHref = computed(() => show(props.post.uuid).url);
const hasMedia = computed(
    () => props.post.media.length > 0 && !props.hideMedia,
);

// Short text updates without photos read like a statement.
const isStatement = computed(
    () =>
        props.post.kind === 'text' &&
        (props.post.caption?.length ?? 0) <= 160 &&
        !props.post.media.length,
);

const showEventChip = computed(
    () => props.post.event && props.post.kind !== 'race_result',
);

const hasStructuredBody = computed(
    () =>
        !!(
            props.post.activity ||
            props.post.medal ||
            props.post.gear ||
            props.post.metrics
        ),
);

// --- Caption "… más" ------------------------------------------------------
const expanded = ref(props.detail);
const longCaption = computed(
    () =>
        (props.post.caption?.length ?? 0) > 140 ||
        (props.post.caption?.split('\n').length ?? 0) > 2,
);

// --- Visibility / delete --------------------------------------------------
const visibility = ref<MomentVisibility>(props.post.visibility);
watch(
    () => props.post.visibility,
    (value) => (visibility.value = value),
);

function changeVisibility(next: MomentVisibility) {
    const previous = visibility.value;
    visibility.value = next;

    router.visit(update(props.post.uuid), {
        data: { visibility: next },
        preserveScroll: true,
        preserveState: true,
        only: ['__post'],
        onError: () => {
            visibility.value = previous;
            toast.error('No pudimos cambiar la visibilidad.');
        },
    });
}

async function remove() {
    const confirmed = await confirmAction({
        title: '¿Eliminar publicación?',
        text: 'Se eliminará de tu perfil y de la comunidad. Esta acción no se puede deshacer.',
        confirmButtonText: 'Eliminar',
    });

    if (!confirmed) {
        return;
    }

    router.visit(destroy(props.post.uuid), {
        data: props.detail ? { from_detail: 1 } : {},
        preserveScroll: true,
        preserveState: !props.detail,
        only: props.detail ? undefined : ['__post'],
        onSuccess: () => emit('deleted', props.post.uuid),
        onError: () => toast.error('No pudimos eliminar la publicación.'),
    });
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(
            props.post.share_url ||
                new URL(detailHref.value, window.location.origin).href,
        );
        toast.success('Enlace copiado.');
    } catch {
        toast.error('No pudimos copiar el enlace.');
    }
}

// --- Comments preview + quick comment ------------------------------------
const localComments = ref<CommentPreview[]>([]);
const commentsCount = ref(props.post.comments_count);
watch(
    () => props.post.comments_count,
    (value) => (commentsCount.value = value),
);

const previewComments = computed(() => [
    ...(props.post.comments_preview ?? []),
    ...localComments.value,
]);

const commentForm = useForm({ body: '' });
const commentInput = ref<HTMLInputElement | null>(null);

function gateComment() {
    if (!isSignedIn.value) {
        commentInput.value?.blur();
        requireAuth();
    }
}

function submitComment() {
    const body = commentForm.body.trim();

    if (!body || commentForm.processing || !requireAuth()) {
        return;
    }

    commentForm.post(storeComment(props.post.uuid).url, {
        preserveScroll: true,
        preserveState: true,
        only: ['__comment'],
        onSuccess: () => {
            localComments.value = [
                ...localComments.value,
                {
                    uuid: `local-${Date.now()}`,
                    body,
                    author: {
                        username: page.props.auth.profile?.username ?? null,
                        name: page.props.auth.user?.name ?? 'Tú',
                    },
                },
            ];
            commentsCount.value++;
            commentForm.reset();
        },
        onError: (errors) =>
            toast.error(errors.body ?? 'No pudimos publicar tu comentario.'),
    });
}

defineExpose({ celebration });
</script>

<template>
    <article
        class="overflow-hidden bg-card sm:rounded-card sm:border sm:border-border sm:shadow-fl-micro"
        :aria-label="`Publicación de ${post.author.name}`"
    >
        <p
            v-if="post.is_hidden"
            class="flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-900"
        >
            <EyeOff class="size-3.5" />
            Oculta por moderación: solo tú la ves.
        </p>

        <!-- Author -->
        <header class="flex items-center gap-3 py-3 pr-2 pl-4">
            <component
                :is="authorHref ? Link : 'div'"
                :href="authorHref ?? undefined"
                class="rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                :aria-label="
                    authorHref ? `Perfil de ${post.author.name}` : undefined
                "
            >
                <AthleteAvatar
                    :name="post.author.name"
                    :photo-url="post.author.photo_url"
                    size="sm"
                />
            </component>

            <div class="min-w-0 flex-1 leading-tight">
                <p class="flex min-w-0 items-center gap-1.5 text-sm">
                    <component
                        :is="authorHref ? Link : 'span'"
                        :href="authorHref ?? undefined"
                        class="truncate font-semibold text-foreground hover:opacity-70"
                    >
                        {{ post.author.username ?? post.author.name }}
                    </component>
                    <span class="text-muted-foreground" aria-hidden="true"
                        >•</span
                    >
                    <Link
                        :href="detailHref"
                        class="shrink-0 text-muted-foreground hover:text-foreground"
                    >
                        <time :datetime="post.created_at">{{
                            timeAgo(post.created_at)
                        }}</time>
                    </Link>
                </p>
                <p
                    class="mt-0.5 flex min-w-0 items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <component
                        :is="visibilityMeta[visibility].icon"
                        class="size-3 shrink-0"
                        :aria-label="visibilityMeta[visibility].label"
                    />
                    <Link
                        v-if="showEventChip && post.event?.slug"
                        :href="`/events/${post.event.slug}`"
                        class="truncate hover:text-foreground"
                        >{{ post.event.name }}</Link
                    >
                    <span v-else class="truncate">{{ post.kind_label }}</span>
                </p>
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-full text-foreground hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Opciones de la publicación"
                    >
                        <MoreHorizontal class="size-5" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <template v-if="post.is_owner">
                        <DropdownMenuLabel class="text-xs text-muted-foreground"
                            >¿Quién puede verla?</DropdownMenuLabel
                        >
                        <DropdownMenuItem
                            v-for="(meta, key) in visibilityMeta"
                            :key="key"
                            :disabled="visibility === key"
                            @select="changeVisibility(key)"
                        >
                            <component :is="meta.icon" class="mr-2 size-4" />
                            {{ meta.label }}
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                    </template>
                    <DropdownMenuItem @select="copyLink">
                        <Link2 class="mr-2 size-4" />
                        Copiar enlace
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-if="post.is_owner"
                        class="text-destructive focus:text-destructive"
                        @select="remove"
                    >
                        <Trash2 class="mr-2 size-4" />
                        Eliminar
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        v-else
                        @select="
                            requireAuth() &&
                            openReport({ type: 'moment', id: post.uuid })
                        "
                    >
                        <Flag class="mr-2 size-4" />
                        Reportar publicación
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </header>

        <!-- Media -->
        <PostMediaCarousel
            v-if="hasMedia"
            :media="post.media"
            :author-name="post.author.name"
            :burst="celebration.burst.value"
            @double-tap="celebration.celebrateOnce()"
        />

        <!-- Text statement (no media) -->
        <component
            :is="detail ? 'div' : Link"
            v-else-if="isStatement && post.caption"
            :href="detail ? undefined : detailHref"
            class="mx-4 mb-1 flex min-h-44 items-center rounded-card bg-gradient-to-br from-fl-cream to-fl-paper px-6 py-8"
        >
            <p
                class="font-serif text-2xl leading-snug tracking-tight whitespace-pre-line text-foreground sm:text-[1.7rem]"
            >
                {{ post.caption }}
            </p>
        </component>

        <!-- Structured body (results, plate, gear, training) -->
        <div
            v-if="hasStructuredBody"
            class="space-y-3 px-4"
            :class="hasMedia ? 'pt-3' : 'pt-1'"
        >
            <PostRaceResult
                v-if="post.kind === 'race_result' && post.activity"
                :activity="post.activity"
            />
            <PostLegacyPlate
                v-else-if="post.kind === 'legacy_plate'"
                :medal="post.medal"
                :activity="post.activity"
                :gear="post.gear"
            />
            <PostGearHighlight
                v-else-if="post.kind === 'gear' && post.gear"
                :gear="post.gear"
            />
            <template v-else>
                <PostRaceResult
                    v-if="post.activity"
                    :activity="post.activity"
                />
                <PostMomentDetails
                    v-if="post.metrics || post.medal"
                    :metrics="post.metrics"
                    :medal="post.medal"
                />
            </template>
        </div>

        <div class="px-4 pt-1.5 pb-4">
            <PostActions
                :post="post"
                :celebration="celebration"
                :detail="detail"
                @comment="emit('comment')"
            />

            <!-- Caption -->
            <div
                v-if="post.caption && !isStatement"
                class="mt-1.5 text-sm leading-relaxed text-foreground"
            >
                <p
                    class="whitespace-pre-line"
                    :class="!expanded && longCaption ? 'line-clamp-2' : ''"
                >
                    <component
                        :is="authorHref ? Link : 'span'"
                        :href="authorHref ?? undefined"
                        class="mr-1.5 font-semibold hover:opacity-70"
                        >{{
                            post.author.username ?? post.author.name
                        }}</component
                    >{{ post.caption }}
                </p>
                <button
                    v-if="!expanded && longCaption"
                    type="button"
                    class="text-sm text-muted-foreground hover:text-foreground"
                    @click="expanded = true"
                >
                    más
                </button>
            </div>

            <Link
                v-if="showEventChip && post.event && !detail"
                :href="
                    post.event.slug ? `/events/${post.event.slug}` : detailHref
                "
                class="mt-2 inline-flex max-w-full items-center gap-1.5 rounded-full bg-fl-paper px-3 py-1 text-xs font-medium text-foreground ring-1 ring-border transition-colors hover:ring-foreground/25"
            >
                <CalendarDays class="size-3.5 shrink-0 text-fl-gold-ink" />
                <span class="truncate">{{ post.event.name }}</span>
                <span
                    v-if="post.event.date"
                    class="shrink-0 text-muted-foreground"
                    >· {{ shortDate(post.event.date) }}</span
                >
            </Link>

            <!-- Comments preview -->
            <template v-if="!detail">
                <Link
                    v-if="commentsCount > previewComments.length"
                    :href="`${detailHref}#comentarios`"
                    class="mt-1.5 block text-sm text-muted-foreground hover:text-foreground"
                >
                    {{
                        commentsCount === 1
                            ? 'Ver el comentario'
                            : `Ver los ${commentsCount} comentarios`
                    }}
                </Link>
                <ul v-if="previewComments.length" class="mt-1 space-y-0.5">
                    <li
                        v-for="comment in previewComments"
                        :key="comment.uuid"
                        class="line-clamp-2 text-sm text-foreground"
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
                    </li>
                </ul>
            </template>

            <Link
                v-if="!detail"
                :href="detailHref"
                class="mt-2 block text-[11px] tracking-wide text-muted-foreground uppercase hover:text-foreground"
            >
                <time :datetime="post.created_at">{{
                    timeAgo(post.created_at)
                }}</time>
            </Link>

            <!-- Quick comment -->
            <form
                v-if="!detail"
                class="mt-3 flex items-center gap-2 border-t border-border pt-3"
                @submit.prevent="submitComment"
            >
                <label :for="`quick-comment-${post.uuid}`" class="sr-only"
                    >Añade un comentario</label
                >
                <input
                    :id="`quick-comment-${post.uuid}`"
                    ref="commentInput"
                    v-model="commentForm.body"
                    type="text"
                    maxlength="500"
                    autocomplete="off"
                    placeholder="Añade un comentario…"
                    class="min-h-9 min-w-0 flex-1 bg-transparent text-sm placeholder:text-muted-foreground focus:outline-none"
                    @focus="gateComment"
                />
                <button
                    v-if="commentForm.body.trim()"
                    type="submit"
                    class="shrink-0 text-sm font-semibold text-fl-gold-ink hover:text-foreground disabled:opacity-50"
                    :disabled="commentForm.processing"
                >
                    Publicar
                </button>
            </form>
        </div>
    </article>
</template>
