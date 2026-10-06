<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { UserPlus } from '@lucide/vue';
import { computed, ref } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import { login } from '@/routes';
import { follow } from '@/routes/athletes';
import type { CommunityAthlete } from '@/types';

/** "Atletas sugeridos" — follow goes through FollowAthlete server-side. */
defineProps<{ athletes: CommunityAthlete[] }>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
const followed = ref<Set<string>>(new Set());

function followAthlete(username: string) {
    followed.value = new Set([...followed.value, username]);
    router.visit(follow(username), {
        preserveScroll: true,
        only: [],
        onError: () => {
            const next = new Set(followed.value);
            next.delete(username);
            followed.value = next;
        },
    });
}
</script>

<template>
    <section class="fl-card p-5" aria-labelledby="sugeridos-title">
        <h2 id="sugeridos-title" class="font-semibold">Atletas sugeridos</h2>
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
                <Link
                    v-if="isGuest"
                    :href="login()"
                    class="shrink-0 rounded-full border border-border px-3 py-1.5 text-xs font-semibold hover:border-foreground/30"
                    >Seguir</Link
                >
                <button
                    v-else
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1 rounded-full px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="
                        followed.has(athlete.username ?? '')
                            ? 'bg-muted text-muted-foreground'
                            : 'bg-foreground text-background hover:bg-fl-graphite'
                    "
                    :disabled="followed.has(athlete.username ?? '')"
                    @click="athlete.username && followAthlete(athlete.username)"
                >
                    <UserPlus
                        v-if="!followed.has(athlete.username ?? '')"
                        class="size-3"
                    />
                    {{
                        followed.has(athlete.username ?? '')
                            ? 'Siguiendo'
                            : 'Seguir'
                    }}
                </button>
            </li>
        </ul>
        <p v-else class="mt-3 text-sm text-muted-foreground">
            Cuando más atletas hagan público su perfil aparecerán aquí.
        </p>
    </section>
</template>
