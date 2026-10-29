<script setup lang="ts">
/**
 * One person in a list (Personas hub, seguidores/siguiendo, búsqueda,
 * sugerencias): avatar, name, @username, sport · city, "Seguido por …"
 * mutuals, "Te sigue" badge and the follow / unblock action.
 * `variant="tile"` is the compact vertical card used in grids/strips.
 */
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import FollowButton from '@/components/community/FollowButton.vue';
import { mutualsLabel } from '@/components/community/mutuals';
import type { CommunityAthlete } from '@/types';

const props = withDefaults(
    defineProps<{
        person: CommunityAthlete;
        variant?: 'row' | 'tile';
        /** Replace the follow button (e.g. "Desbloquear"). */
        hideFollow?: boolean;
    }>(),
    { variant: 'row', hideFollow: false },
);

const page = usePage();
const isMe = computed(
    () =>
        !!props.person.username &&
        props.person.username === page.props.auth.profile?.username,
);
const href = computed(() =>
    props.person.username ? `/@${props.person.username}` : null,
);
const meta = computed(() =>
    [props.person.sport, props.person.city].filter(Boolean).join(' · '),
);
const mutuals = computed(() => mutualsLabel(props.person));
</script>

<template>
    <div v-if="variant === 'row'" class="flex items-center gap-3 py-3">
        <component
            :is="href ? Link : 'div'"
            :href="href ?? undefined"
            class="flex min-w-0 flex-1 items-center gap-3"
        >
            <AthleteAvatar
                :name="person.name"
                :photo-url="person.photo_url"
                size="md"
            />
            <span class="min-w-0 leading-tight">
                <span class="flex items-center gap-2">
                    <span
                        class="truncate text-sm font-semibold text-foreground"
                        >{{ person.username ?? person.name }}</span
                    >
                    <span
                        v-if="person.follows_you && !isMe"
                        class="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground"
                        >Te sigue</span
                    >
                </span>
                <span class="block truncate text-sm text-muted-foreground"
                    >{{ person.name
                    }}<template v-if="meta"> · {{ meta }}</template></span
                >
                <span
                    v-if="mutuals"
                    class="mt-0.5 block truncate text-xs text-muted-foreground"
                    >{{ mutuals }}</span
                >
            </span>
        </component>
        <slot name="action">
            <FollowButton
                v-if="!hideFollow && person.username && !isMe"
                :username="person.username"
                :name="person.name"
                :following="person.is_following ?? false"
                :follows-you="person.follows_you ?? false"
                size="sm"
            />
        </slot>
    </div>

    <div
        v-else
        class="flex h-full flex-col items-center rounded-card border border-border bg-card p-4 text-center transition-shadow hover:shadow-fl-soft"
    >
        <component
            :is="href ? Link : 'div'"
            :href="href ?? undefined"
            class="flex min-w-0 flex-col items-center"
        >
            <AthleteAvatar
                :name="person.name"
                :photo-url="person.photo_url"
                size="lg"
            />
            <span
                class="mt-3 block w-full truncate text-sm font-semibold text-foreground"
                >{{ person.name }}</span
            >
            <span class="block w-full truncate text-xs text-muted-foreground"
                >@{{ person.username }}</span
            >
        </component>
        <span
            class="mt-1 line-clamp-2 min-h-8 text-[11px] leading-4 text-muted-foreground"
            >{{
                mutuals || meta || (person.follows_you ? 'Te sigue' : '')
            }}</span
        >
        <div class="mt-3 w-full">
            <slot name="action">
                <FollowButton
                    v-if="!hideFollow && person.username && !isMe"
                    class="w-full"
                    :username="person.username"
                    :name="person.name"
                    :following="person.is_following ?? false"
                    :follows-you="person.follows_you ?? false"
                    size="sm"
                />
            </slot>
        </div>
    </div>
</template>
