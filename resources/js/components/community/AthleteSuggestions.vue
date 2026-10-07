<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import FollowButton from '@/components/community/FollowButton.vue';
import type { CommunityAthlete } from '@/types';

/** "Atletas sugeridos" — follow goes through FollowAthlete server-side. */
defineProps<{ athletes: CommunityAthlete[] }>();

const page = usePage();
const isSignedIn = computed(() => !!page.props.auth.user);
</script>

<template>
    <section class="fl-card p-5" aria-labelledby="sugeridos-title">
        <div class="flex items-center justify-between gap-2">
            <h2 id="sugeridos-title" class="font-semibold">
                Atletas sugeridos
            </h2>
            <Link
                v-if="isSignedIn"
                href="/comunidad/conexiones"
                class="text-xs font-medium text-muted-foreground hover:text-foreground"
                >Mis conexiones →</Link
            >
        </div>
        <ul v-if="athletes.length" class="mt-4 space-y-4">
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
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold">{{
                            athlete.name
                        }}</span>
                        <span
                            class="block truncate text-xs text-muted-foreground"
                            >{{
                                [athlete.sport, athlete.city]
                                    .filter(Boolean)
                                    .join(' · ') || `@${athlete.username}`
                            }}</span
                        >
                    </span>
                </Link>
                <FollowButton
                    v-if="athlete.username"
                    :username="athlete.username"
                    :name="athlete.name"
                    :following="athlete.is_following ?? false"
                    size="sm"
                />
            </li>
        </ul>
        <p v-else class="mt-3 text-sm text-muted-foreground">
            Cuando más atletas hagan público su perfil aparecerán aquí.
        </p>
    </section>
</template>
