<script setup lang="ts">
/**
 * Compact read-only version of a publication for teasers (Home, profile
 * sidebars). Opens the full post in Comunidad.
 */
import { Link } from '@inertiajs/vue3';
import { MessageCircle, PartyPopper } from '@lucide/vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import { timeAgo } from '@/lib/datetime';
import { show } from '@/routes/community/posts';
import type { CommunityPost } from '@/types';

defineProps<{ post: CommunityPost }>();
</script>

<template>
    <Link
        :href="show(post.uuid)"
        class="group fl-card flex h-full flex-col overflow-hidden transition-colors hover:border-foreground/20 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
    >
        <div
            v-if="post.media[0] && post.media[0].type !== 'video'"
            class="aspect-[16/10] overflow-hidden bg-muted"
        >
            <img
                :src="post.media[0].url"
                :alt="`Foto de ${post.author.name}`"
                loading="lazy"
                decoding="async"
                class="size-full object-cover transition-transform duration-700 group-hover:scale-[1.03]"
            />
        </div>
        <div class="flex flex-1 flex-col p-5">
            <div class="flex items-center gap-3">
                <AthleteAvatar
                    :name="post.author.name"
                    :photo-url="post.author.photo_url"
                    size="sm"
                />
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold">
                        {{ post.author.name }}
                    </p>
                    <p class="truncate text-xs text-muted-foreground">
                        <template v-if="post.author.sport"
                            >{{ post.author.sport }} ·
                        </template>
                        {{ timeAgo(post.created_at) }}
                    </p>
                </div>
            </div>
            <p
                v-if="post.caption"
                class="mt-4 line-clamp-3 text-[15px] leading-relaxed text-foreground"
            >
                {{ post.caption }}
            </p>
            <p
                v-if="post.activity"
                class="mt-3 flex items-center justify-between gap-3 rounded-lg bg-background px-3 py-2 text-xs"
            >
                <span class="truncate font-medium">{{
                    post.activity.event
                }}</span>
                <span
                    v-if="post.activity.official_time"
                    class="legacy-numeric shrink-0 font-semibold"
                    >{{ post.activity.official_time }}</span
                >
            </p>
            <p
                class="mt-auto flex items-center gap-4 pt-4 text-xs text-muted-foreground"
            >
                <span class="inline-flex items-center gap-1.5">
                    <PartyPopper class="size-3.5" />
                    {{ post.reactions.cheer }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <MessageCircle class="size-3.5" />
                    {{ post.comments_count }}
                </span>
            </p>
        </div>
    </Link>
</template>
