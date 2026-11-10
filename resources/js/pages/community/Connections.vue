<script setup lang="ts">
/**
 * Seguidores / Siguiendo of an athlete (/@username/seguidores|siguiendo).
 * Privacy is enforced server-side (SocialVisibility::scopeListableUsers);
 * each person carries mutuals + follow state (MutualConnections). Your
 * own lists (and Bloqueados) live in the Personas hub.
 */
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Search, Users, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import PersonCard from '@/components/community/PersonCard.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { useI18n } from '@/i18n';
import { connections as profileConnections } from '@/routes/profile';
import type { CommunityAthlete } from '@/types';

type Tab = 'siguiendo' | 'seguidores';

const props = defineProps<{
    tab: Tab;
    q?: string;
    owner: { username: string; name: string; photo_url?: string | null };
    athletes: {
        data: CommunityAthlete[];
        links: PaginationLink[];
        total: number;
    };
    counts: { siguiendo: number; seguidores: number };
}>();

const { t, regional } = useI18n();

const tabs = computed<{ key: Tab; label: string }[]>(() => [
    { key: 'seguidores', label: t('Seguidores') },
    { key: 'siguiendo', label: t('Siguiendo') },
]);

const seoTitle = computed(() =>
    props.tab === 'seguidores'
        ? t('{name} · Seguidores', { name: props.owner.name })
        : t('{name} · Siguiendo', { name: props.owner.name }),
);
const seoDescription = computed(() =>
    props.tab === 'seguidores'
        ? t('Seguidores de {name} en Finisher Legacy.', {
              name: props.owner.name,
          })
        : t('Atletas que sigue {name} en Finisher Legacy.', {
              name: props.owner.name,
          }),
);

function tabHref(key: Tab): string {
    return profileConnections({
        athleteProfile: props.owner.username,
        list: key,
    }).url;
}

const term = ref(props.q ?? '');

watchDebounced(
    term,
    (value) => {
        if (value.trim() === (props.q ?? '')) {
            return;
        }

        router.get(
            tabHref(props.tab),
            { q: value.trim() || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    },
    { debounce: 300 },
);

const emptyTitle = computed(() => {
    if (props.q) {
        return t('Nadie coincide con “{query}”.', { query: props.q });
    }

    return props.tab === 'siguiendo'
        ? t('{name} aún no sigue a nadie visible para ti.', {
              name: props.owner.name,
          })
        : t('{name} aún no tiene seguidores visibles para ti.', {
              name: props.owner.name,
          });
});
</script>

<template>
    <SeoHead :title="seoTitle" :description="seoDescription" noindex />

    <div class="fl-container max-w-2xl pt-6 pb-16 sm:pt-10">
        <Link
            :href="`/@${owner.username}`"
            class="inline-flex min-h-10 items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            <AthleteAvatar
                :name="owner.name"
                :photo-url="owner.photo_url"
                size="xs"
            />
            @{{ owner.username }}
        </Link>
        <h1 class="mt-2 font-serif text-3xl tracking-tight">
            {{ owner.name }}
        </h1>

        <nav
            class="mt-5 grid grid-cols-2 border-b border-border"
            :aria-label="$t('Listas')"
        >
            <Link
                v-for="item in tabs"
                :key="item.key"
                :href="tabHref(item.key)"
                preserve-scroll
                :aria-current="tab === item.key ? 'page' : undefined"
                class="-mb-px inline-flex min-h-12 items-center justify-center gap-1.5 border-b-2 text-sm font-semibold transition-colors"
                :class="
                    tab === item.key
                        ? 'border-foreground text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
            >
                <span class="legacy-numeric">{{
                    counts[item.key].toLocaleString(regional)
                }}</span>
                {{ item.label }}
            </Link>
        </nav>

        <div class="relative mt-4" role="search">
            <label for="connections-search" class="sr-only">{{
                $t('Buscar')
            }}</label>
            <Search
                class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <input
                id="connections-search"
                v-model="term"
                type="search"
                autocomplete="off"
                :placeholder="$t('Buscar')"
                class="h-10 w-full rounded-full border border-input bg-card pr-10 pl-11 text-sm placeholder:text-muted-foreground focus:border-foreground/30 focus:ring-2 focus:ring-ring/30 focus:outline-none [&::-webkit-search-cancel-button]:hidden"
            />
            <button
                v-if="term"
                type="button"
                class="absolute top-1/2 right-2 flex size-7 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground hover:bg-muted"
                :aria-label="$t('Limpiar búsqueda')"
                @click="term = ''"
            >
                <X class="size-4" />
            </button>
        </div>

        <div v-if="athletes.data.length" class="mt-2 divide-y divide-border">
            <PersonCard
                v-for="athlete in athletes.data"
                :key="athlete.username ?? athlete.name"
                :person="athlete"
            />
        </div>
        <div
            v-else
            class="mt-6 flex flex-col items-center gap-3 rounded-card border border-dashed border-foreground/15 bg-card px-6 py-14 text-center"
        >
            <span
                class="flex size-14 items-center justify-center rounded-full bg-fl-cream"
            >
                <Users class="size-6 text-fl-gold-ink" />
            </span>
            <p class="font-medium">{{ emptyTitle }}</p>
        </div>

        <div class="mt-6">
            <Pagination :links="athletes.links" />
        </div>
    </div>
</template>
