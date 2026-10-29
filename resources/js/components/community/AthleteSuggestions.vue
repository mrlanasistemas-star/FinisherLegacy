<script setup lang="ts">
/**
 * "Sugerencias para ti" — right rail of the feed. Follow goes through
 * FollowAthlete server-side; "Ver todo" opens the Personas hub (session
 * required → guests sign in first and come back).
 */
import { Link } from '@inertiajs/vue3';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import FollowButton from '@/components/community/FollowButton.vue';
import { mutualsLabel } from '@/components/community/mutuals';
import { useAuthGate } from '@/composables/useAuthGate';
import { people } from '@/routes/community';
import type { CommunityAthlete } from '@/types';

defineProps<{ athletes: CommunityAthlete[] }>();

const { isAuthenticated, loginUrl } = useAuthGate();
</script>

<template>
    <section aria-labelledby="sugeridos-title">
        <div class="flex items-center justify-between gap-2">
            <h2
                id="sugeridos-title"
                class="text-sm font-semibold text-muted-foreground"
            >
                Sugerencias para ti
            </h2>
            <Link
                :href="isAuthenticated ? people().url : loginUrl(people().url)"
                class="text-xs font-semibold text-foreground hover:text-muted-foreground"
                >Ver todo</Link
            >
        </div>
        <ul v-if="athletes.length" class="mt-3 space-y-3">
            <li
                v-for="athlete in athletes"
                :key="athlete.username ?? athlete.name"
                class="flex items-center gap-3"
            >
                <Link
                    :href="`/@${athlete.username}`"
                    class="flex min-w-0 flex-1 items-center gap-3"
                >
                    <AthleteAvatar
                        :name="athlete.name"
                        :photo-url="athlete.photo_url"
                        size="sm"
                    />
                    <span class="min-w-0 leading-tight">
                        <span
                            class="block truncate text-sm font-semibold hover:opacity-70"
                            >{{ athlete.username ?? athlete.name }}</span
                        >
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{{
                                mutualsLabel(athlete) ??
                                ([athlete.sport, athlete.city]
                                    .filter(Boolean)
                                    .join(' · ') ||
                                    athlete.name)
                            }}</span
                        >
                    </span>
                </Link>
                <FollowButton
                    v-if="athlete.username"
                    :username="athlete.username"
                    :name="athlete.name"
                    :following="athlete.is_following ?? false"
                    :follows-you="athlete.follows_you ?? false"
                    size="sm"
                />
            </li>
        </ul>
        <p v-else class="mt-3 text-sm text-muted-foreground">
            Cuando más atletas hagan público su perfil aparecerán aquí.
        </p>
    </section>
</template>
