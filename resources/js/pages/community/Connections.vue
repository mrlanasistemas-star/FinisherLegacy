<script setup lang="ts">
/**
 * Conexiones — who follows whom. Two modes:
 *  - mine (`owner` null, /comunidad/conexiones): Siguiendo · Seguidores ·
 *    Bloqueados (with Desbloquear).
 *  - an athlete's (/@username/seguidores|siguiendo).
 * Privacy is enforced server-side (SocialVisibility::scopeListableUsers).
 */
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Users } from '@lucide/vue';
import { computed } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import FollowButton from '@/components/community/FollowButton.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { unblock } from '@/routes/athletes';
import type { CommunityAthlete } from '@/types';

type Tab = 'siguiendo' | 'seguidores' | 'bloqueados';

const props = defineProps<{
    tab: Tab;
    owner: { username: string; name: string } | null;
    athletes: {
        data: CommunityAthlete[];
        links: PaginationLink[];
        total: number;
    };
    counts: { siguiendo: number; seguidores: number; bloqueados?: number };
}>();

const page = usePage();
const me = computed(() => page.props.auth.profile?.username ?? null);

const tabs = computed(() => {
    const base = [
        { key: 'siguiendo' as Tab, label: 'Siguiendo' },
        { key: 'seguidores' as Tab, label: 'Seguidores' },
    ];

    return props.owner
        ? base
        : [...base, { key: 'bloqueados' as Tab, label: 'Bloqueados' }];
});

function tabHref(key: Tab): string {
    return props.owner
        ? `/@${props.owner.username}/${key}`
        : `/comunidad/conexiones?tab=${key}`;
}

const title = computed(() =>
    props.owner ? props.owner.name : 'Mis conexiones',
);

function doUnblock(username: string) {
    router.visit(unblock(username), { preserveScroll: true });
}

const emptyTitle = computed(() => {
    if (props.tab === 'bloqueados') {
        return 'No has bloqueado a nadie.';
    }

    if (props.owner) {
        return props.tab === 'siguiendo'
            ? 'Aún no sigue a nadie visible para ti.'
            : 'Aún no tiene seguidores visibles para ti.';
    }

    return props.tab === 'siguiendo'
        ? 'Todavía no sigues a nadie.'
        : 'Aún no tienes seguidores.';
});
</script>

<template>
    <Head
        :title="
            owner
                ? `${owner.name} · ${tab === 'seguidores' ? 'Seguidores' : 'Siguiendo'}`
                : 'Mis conexiones'
        "
    />

    <div class="fl-container max-w-3xl py-8 sm:py-12">
        <Link
            :href="owner ? `/@${owner.username}` : '/comunidad'"
            class="inline-flex min-h-11 items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            {{ owner ? `@${owner.username}` : 'Comunidad' }}
        </Link>
        <h1 class="fl-display mt-2 text-3xl sm:text-4xl">{{ title }}</h1>

        <nav
            class="mt-6 inline-flex flex-wrap rounded-full border border-border bg-card p-1"
            aria-label="Listas"
        >
            <Link
                v-for="t in tabs"
                :key="t.key"
                :href="tabHref(t.key)"
                preserve-scroll
                :aria-current="tab === t.key ? 'page' : undefined"
                class="inline-flex min-h-10 items-center rounded-full px-4 text-sm font-semibold transition-colors"
                :class="
                    tab === t.key
                        ? 'bg-foreground text-background'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                {{ t.label }}
                <span class="legacy-numeric ml-1 opacity-70">{{
                    counts[t.key] ?? 0
                }}</span>
            </Link>
        </nav>

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
                <Button
                    v-if="tab === 'bloqueados' && athlete.username"
                    variant="outline"
                    size="sm"
                    class="rounded-full"
                    @click="doUnblock(athlete.username)"
                >
                    Desbloquear
                </Button>
                <FollowButton
                    v-else-if="athlete.username && athlete.username !== me"
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
            <p class="font-medium">{{ emptyTitle }}</p>
            <Link
                v-if="!owner && tab !== 'bloqueados'"
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
