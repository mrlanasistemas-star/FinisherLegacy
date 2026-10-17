<script setup lang="ts">
/**
 * One post with its comments ("mensajes de apoyo"). Visibility, who may
 * comment and who may delete a comment are decided server-side
 * (LegacyMomentPolicy / LegacyMomentCommentPolicy). When the post has
 * media the detail splits on desktop (media left, caption + actions +
 * comments right); text-only posts stay centered in a reading measure.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import CommunityPost from '@/components/community/CommunityPost.vue';
import CommunityReportDialog from '@/components/community/CommunityReportDialog.vue';
import PostMediaGrid from '@/components/community/post/PostMediaGrid.vue';
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

const hasMedia = computed(() => props.post.media.length > 0);

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

    <div class="fl-container py-6 sm:py-10">
        <Link
            :href="communityIndex()"
            class="inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Volver a la comunidad
        </Link>

        <!-- Split detail: media left, content + comments right. -->
        <div
            v-if="hasMedia"
            class="mt-4 lg:grid lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:items-start lg:gap-6"
        >
            <div
                class="overflow-hidden bg-card sm:rounded-media sm:border sm:border-border lg:sticky lg:top-20"
            >
                <PostMediaGrid
                    :media="post.media"
                    :author-name="post.author.name"
                />
            </div>

            <div class="mt-4 min-w-0 lg:mt-0">
                <CommunityPost :post="post" detail hide-media />

                <section
                    id="comentarios"
                    class="mt-6 scroll-mt-24"
                    aria-labelledby="comentarios-title"
                >
                    <h2 id="comentarios-title" class="fl-h3 font-serif">
                        Mensajes de apoyo
                        <span class="text-base text-muted-foreground">{{
                            comments.length
                        }}</span>
                    </h2>
                    <PostComments
                        class="mt-4"
                        :post-uuid="post.uuid"
                        :comments="comments"
                        :comment-max="commentMax"
                        :can-interact="canInteract"
                    />
                </section>
            </div>
        </div>

        <!-- Text-only: centered reading measure. -->
        <div v-else class="mx-auto mt-4 max-w-2xl">
            <CommunityPost :post="post" detail />

            <section
                id="comentarios"
                class="mt-8 scroll-mt-24"
                aria-labelledby="comentarios-title-text"
            >
                <h2 id="comentarios-title-text" class="fl-h3 font-serif">
                    Mensajes de apoyo
                    <span class="text-base text-muted-foreground">{{
                        comments.length
                    }}</span>
                </h2>
                <PostComments
                    class="mt-4"
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
