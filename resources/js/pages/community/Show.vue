<script setup lang="ts">
/**
 * One post with its comments, Instagram-style. With photos it splits on
 * desktop into one card: media left (swipe, double-tap to celebrar),
 * author + caption + comments scrolling on the right with the action row
 * and the comment box pinned at the bottom. On phones (and for text-only
 * posts) it stacks in a single column. Visibility, who may comment and
 * who may delete a comment are decided server-side.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, toRef } from 'vue';
import CommunityPost from '@/components/community/CommunityPost.vue';
import CommunityReportDialog from '@/components/community/CommunityReportDialog.vue';
import PostMediaCarousel from '@/components/community/post/PostMediaCarousel.vue';
import { useCelebrate } from '@/components/community/post/useCelebrate';
import PostComments from '@/components/community/PostComments.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { index as communityIndex } from '@/routes/community';
import type {
    CommunityComment,
    CommunityPost as CommunityPostData,
    ReportReason,
} from '@/types';

const props = defineProps<{
    post: CommunityPostData;
    comments: CommunityComment[];
    commentMax: number;
    canInteract: boolean;
    reportReasons: ReportReason[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const isDesktop = useMediaQuery('(min-width: 1024px)');

const hasMedia = computed(() => props.post.media.length > 0);
const celebration = useCelebrate(toRef(props, 'post'));
const thread = ref<InstanceType<typeof PostComments> | null>(null);

function focusComment() {
    thread.value?.focus();
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

    <div class="mx-auto max-w-[1100px] pt-3 pb-16 sm:px-6 sm:pt-8 lg:px-8">
        <Link
            :href="communityIndex()"
            class="mx-4 inline-flex min-h-10 items-center gap-2 text-sm text-muted-foreground hover:text-foreground sm:mx-0"
        >
            <ArrowLeft class="size-4" />
            Comunidad
        </Link>

        <!-- Desktop split -->
        <div
            v-if="hasMedia && isDesktop"
            class="mt-3 grid h-[min(86svh,820px)] grid-cols-[minmax(0,1fr)_400px] overflow-hidden rounded-card border border-border bg-card shadow-fl-soft"
        >
            <div class="min-h-0 bg-fl-cream">
                <PostMediaCarousel
                    fill
                    :media="post.media"
                    :author-name="post.author.name"
                    :burst="celebration.burst.value"
                    @double-tap="celebration.celebrateOnce()"
                />
            </div>
            <div class="flex min-h-0 flex-col border-l border-border">
                <div class="max-h-[55%] shrink-0 overflow-y-auto">
                    <CommunityPost
                        :post="post"
                        detail
                        hide-media
                        :celebration="celebration"
                        class="sm:rounded-none sm:border-0 sm:shadow-none"
                        @comment="focusComment"
                    />
                </div>
                <div
                    id="comentarios"
                    class="flex min-h-0 flex-1 flex-col border-t border-border"
                >
                    <h2 class="sr-only">Comentarios</h2>
                    <PostComments
                        ref="thread"
                        split
                        :post-uuid="post.uuid"
                        :comments="comments"
                        :comment-max="commentMax"
                        :can-interact="canInteract"
                    />
                </div>
            </div>
        </div>

        <!-- Stacked (phones, text-only posts) -->
        <div v-else class="mx-auto mt-3 max-w-[600px]">
            <CommunityPost
                :post="post"
                detail
                :celebration="celebration"
                @comment="focusComment"
            />

            <section
                id="comentarios"
                class="mt-2 scroll-mt-24 bg-card px-4 py-5 sm:mt-4 sm:rounded-card sm:border sm:border-border"
                aria-labelledby="comentarios-title"
            >
                <h2 id="comentarios-title" class="mb-4 text-sm font-semibold">
                    Comentarios
                    <span class="font-normal text-muted-foreground">{{
                        post.comments_count
                    }}</span>
                </h2>
                <PostComments
                    ref="thread"
                    :post-uuid="post.uuid"
                    :comments="comments"
                    :comment-max="commentMax"
                    :can-interact="canInteract"
                />
            </section>
        </div>
    </div>

    <CommunityReportDialog v-if="user" :reasons="reportReasons" />
</template>
