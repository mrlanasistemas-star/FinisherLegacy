<script setup lang="ts">
/**
 * Comunidad → Conexiones: the athletes I follow and the ones who follow
 * me, each with the same animated Seguir / Siguiendo button. Privacy is
 * enforced server-side (public profiles only, blocked users hidden).
 */
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Users } from '@lucide/vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import FollowButton from '@/components/community/FollowButton.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import type { CommunityAthlete } from '@/types';

defineProps<{
    tab: 'siguiendo' | 'seguidores';
    athletes: {
        data: CommunityAthlete[];
        links: PaginationLink[];
        total: number;
    };
    counts: { siguiendo: number; seguidores: number };
}>();
</script>

<template>
    <Head title="Mis conexiones" />

    <div class="fl-container max-w-3xl py-8 sm:py-12">
        <Link
            href="/comunidad"
            class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Comunidad
        </Link>
        <h1 class="fl-display mt-4 text-3xl sm:text-4xl">Mis conexiones</h1>

        <div
            class="mt-6 inline-flex rounded-full border border-border bg-card p-1"
            role="tablist"
        >
            <Link
                v-for="t in [
                    { key: 'siguiendo', label: 'Siguiendo' },
                    { key: 'seguidores', label: 'Seguidores' },
                ] as const"
                :key="t.key"
                :href="`/comunidad/conexiones?tab=${t.key}`"
                preserve-scroll
                role="tab"
                :aria-selected="tab === t.key"
                class="rounded-full px-4 py-2 text-sm font-semibold transition-colors"
                :class="
                    tab === t.key
                        ? 'bg-foreground text-background'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                {{ t.label }}
                <span class="legacy-numeric ml-1 opacity-70">{{
                    counts[t.key]
                }}</span>
            </Link>
        </div>

        <ul v-if="athletes.data.length" class="mt-6 divide-y divide-border">
            <li
                v-for="athlete in athletes.data"
                :key="athlete.username ?? athlete.name"
                class="fl-list-in flex items-center gap-3 py-3"
            >
                <Link
                    :href="`/@${athlete.username}`"
                    class="flex min-w-0 flex-1 items-center gap-3"
                >
                    <AthleteAvatar
                        :name="athlete.name"
                        :photo-url="athlete.photo_url"
                    />
                    <span class="min-w-0">
                        <span class="block truncate font-semibold">{{
                            athlete.name
                        }}</span>
                        <span
                            class="block truncate text-sm text-muted-foreground"
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
        <div
            v-else
            class="fl-card mt-6 flex flex-col items-center gap-3 px-6 py-14 text-center"
        >
            <Users class="size-8 text-muted-foreground" />
            <p class="font-medium">
                {{
                    tab === 'siguiendo'
                        ? 'Todavía no sigues a nadie.'
                        : 'Aún no tienes seguidores.'
                }}
            </p>
            <p class="max-w-sm text-sm text-muted-foreground">
                {{
                    tab === 'siguiendo'
                        ? 'Encuentra atletas en la comunidad y sigue sus historias.'
                        : 'Publica tus momentos y celebra a otros: así te encuentran.'
                }}
            </p>
            <Link
                href="/comunidad"
                class="mt-2 rounded-full bg-foreground px-5 py-2 text-sm font-semibold text-background"
                >Ir a la comunidad</Link
            >
        </div>

        <div class="mt-6">
            <Pagination :links="athletes.links" />
        </div>
    </div>
</template>

<style scoped>
.fl-list-in {
    animation: fl-list-in 0.35s ease-out both;
}

@keyframes fl-list-in {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-list-in {
        animation: none;
    }
}
</style>
