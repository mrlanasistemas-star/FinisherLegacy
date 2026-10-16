<script setup lang="ts">
/**
 * One Comunidad post. The card family follows `post.kind` (spec §54):
 * moment · race_result · photo · gear · legacy_plate · text — one shell,
 * a small body component per kind. Who may see it was already decided
 * server-side (SocialVisibility); the menu only offers what the viewer
 * may do (owner: visibility/delete — others: report).
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    EyeOff,
    Flag,
    Globe2,
    Lock,
    MoreHorizontal,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import PostActions from '@/components/community/post/PostActions.vue';
import PostGearHighlight from '@/components/community/post/PostGearHighlight.vue';
import PostLegacyPlate from '@/components/community/post/PostLegacyPlate.vue';
import PostMediaGrid from '@/components/community/post/PostMediaGrid.vue';
import PostMomentDetails from '@/components/community/post/PostMomentDetails.vue';
import PostRaceResult from '@/components/community/post/PostRaceResult.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCommunityReport } from '@/composables/useCommunityReport';
import { shortDate, timeAgo } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';
import { destroy, show, update } from '@/routes/community/posts';
import type { CommunityPost, MomentVisibility } from '@/types';

const props = withDefaults(
    defineProps<{
        post: CommunityPost;
        /** Detail page: caption is not a link, delete goes back to the feed. */
        detail?: boolean;
    }>(),
    { detail: false },
);

const emit = defineEmits<{ deleted: [uuid: string] }>();

const page = usePage();
const isSignedIn = computed(() => !!page.props.auth.user);
const { openReport } = useCommunityReport();

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

// Short text updates read like a statement, not a paragraph.
const isStatement = computed(
    () =>
        props.post.kind === 'text' &&
        (props.post.caption?.length ?? 0) <= 140 &&
        !props.post.media.length,
);

const showEventChip = computed(
    () => props.post.event && props.post.kind !== 'race_result',
);

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
        onError: () => (visibility.value = previous),
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
    });
}
</script>

<template>
    <article
        class="fl-card overflow-hidden transition-shadow duration-300 hover:shadow-[0_18px_40px_-30px_rgb(23_23_20/0.35)]"
        :aria-label="`Publicación de ${post.author.name}`"
    >
        <p
            v-if="post.is_hidden"
            class="flex items-center gap-2 border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-900 sm:px-5"
        >
            <EyeOff class="size-3.5" />
            Oculta por moderación: solo tú la ves.
        </p>

        <header class="flex items-start gap-3 px-4 pt-4 sm:px-5 sm:pt-5">
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
                />
            </component>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <component
                        :is="authorHref ? Link : 'span'"
                        :href="authorHref ?? undefined"
                        class="truncate text-[15px] font-semibold text-foreground hover:underline"
                    >
                        {{ post.author.name }}
                    </component>
                    <span
                        class="rounded-full bg-fl-cream px-2 py-0.5 text-[10px] font-semibold tracking-[0.12em] text-fl-gold-ink uppercase"
                        >{{ post.kind_label }}</span
                    >
                </div>
                <p
                    class="mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground"
                >
                    <span v-if="post.author.username"
                        >@{{ post.author.username }}</span
                    >
                    <span aria-hidden="true">·</span>
                    <Link :href="detailHref" class="hover:underline">
                        <time :datetime="post.created_at">{{
                            timeAgo(post.created_at)
                        }}</time>
                    </Link>
                    <span aria-hidden="true">·</span>
                    <span
                        class="inline-flex items-center gap-1"
                        :title="visibilityMeta[visibility].label"
                    >
                        <component
                            :is="visibilityMeta[visibility].icon"
                            class="size-3"
                            aria-hidden="true"
                        />
                        <span class="sr-only sm:not-sr-only">{{
                            visibilityMeta[visibility].label
                        }}</span>
                    </span>
                </p>
            </div>

            <DropdownMenu v-if="isSignedIn">
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        class="-mr-2 flex size-11 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Opciones de la publicación"
                    >
                        <MoreHorizontal class="size-4" />
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
                        <DropdownMenuItem
                            class="text-destructive focus:text-destructive"
                            @select="remove"
                        >
                            <Trash2 class="mr-2 size-4" />
                            Eliminar
                        </DropdownMenuItem>
                    </template>
                    <DropdownMenuItem
                        v-else
                        @select="openReport({ type: 'moment', id: post.uuid })"
                    >
                        <Flag class="mr-2 size-4" />
                        Reportar publicación
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </header>

        <div class="space-y-3 px-4 pt-3 pb-1 sm:px-5">
            <component
                :is="detail ? 'p' : Link"
                v-if="post.caption"
                :href="detail ? undefined : detailHref"
                class="block whitespace-pre-line text-foreground"
                :class="
                    isStatement
                        ? 'font-serif text-2xl leading-snug tracking-tight sm:text-[1.7rem]'
                        : 'text-[15px] leading-relaxed'
                "
            >
                {{ post.caption }}
            </component>

            <Link
                v-if="showEventChip && post.event"
                :href="
                    post.event.slug ? `/events/${post.event.slug}` : detailHref
                "
                class="inline-flex max-w-full items-center gap-2 rounded-full border border-border bg-fl-paper px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:border-foreground/25"
            >
                <CalendarDays class="size-3.5 shrink-0 text-fl-gold-ink" />
                <span class="truncate">{{ post.event.name }}</span>
                <span
                    v-if="post.event.date"
                    class="shrink-0 text-muted-foreground"
                    >· {{ shortDate(post.event.date) }}</span
                >
            </Link>

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

        <PostMediaGrid
            v-if="post.media.length"
            class="mt-3"
            :media="post.media"
            :author-name="post.author.name"
            :href="detail ? null : detailHref"
        />

        <PostActions class="mt-2" :post="post" />
    </article>
</template>
