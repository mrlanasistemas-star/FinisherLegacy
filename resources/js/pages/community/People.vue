<script setup lang="ts">
/**
 * Personas — the people hub of Comunidad (/comunidad/personas):
 * Sugeridos · Amigos de amigos · Amigos · Seguidores · Siguiendo ·
 * Bloqueados, plus a name/@username filter. Lists, privacy and mutuals
 * come from the server (PeopleQuery + MutualConnections); the list grows
 * with <InfiniteScroll> (merged `people` scroll prop).
 */
import { InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Search, UserRoundSearch, X } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref } from 'vue';
import PersonCard from '@/components/community/PersonCard.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { unblock } from '@/routes/athletes';
import {
    index as communityIndex,
    people as peopleRoute,
} from '@/routes/community';
import type { CommunityAthlete } from '@/types';

type Tab =
    | 'sugeridos'
    | 'amigos-de-amigos'
    | 'amigos'
    | 'seguidores'
    | 'siguiendo'
    | 'bloqueados';

const props = defineProps<{
    tab: Tab;
    q: string;
    people: { data: CommunityAthlete[]; meta?: { total?: number } };
    counts: {
        amigos: number;
        seguidores: number;
        siguiendo: number;
        bloqueados: number;
    };
}>();

const tabs: { key: Tab; label: string; count?: keyof typeof props.counts }[] = [
    { key: 'sugeridos', label: 'Sugeridos para ti' },
    { key: 'amigos-de-amigos', label: 'Amigos de amigos' },
    { key: 'amigos', label: 'Amigos', count: 'amigos' },
    { key: 'seguidores', label: 'Seguidores', count: 'seguidores' },
    { key: 'siguiendo', label: 'Siguiendo', count: 'siguiendo' },
    { key: 'bloqueados', label: 'Bloqueados', count: 'bloqueados' },
];

const isGrid = computed(
    () => props.tab === 'sugeridos' || props.tab === 'amigos-de-amigos',
);

const term = ref(props.q);
const searching = ref(false);

function tabHref(key: Tab): string {
    return peopleRoute({
        query: {
            tab: key === 'sugeridos' ? undefined : key,
            q: term.value.trim() || undefined,
        },
    }).url;
}

watchDebounced(
    term,
    (value) => {
        if (value.trim() === props.q) {
            return;
        }

        router.get(
            peopleRoute().url,
            {
                tab: props.tab === 'sugeridos' ? undefined : props.tab,
                q: value.trim() || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => (searching.value = true),
                onFinish: () => (searching.value = false),
            },
        );
    },
    { debounce: 300 },
);

// Unblocked people leave the list right away.
const unblocked = ref(new Set<string>());
const rows = computed(() =>
    props.people.data.filter(
        (person) => !person.username || !unblocked.value.has(person.username),
    ),
);

function doUnblock(username: string) {
    router.visit(unblock(username), {
        preserveScroll: true,
        preserveState: true,
        only: ['counts'],
        onSuccess: () => {
            unblocked.value = new Set([...unblocked.value, username]);
        },
    });
}

const empty = computed(() => {
    if (props.q) {
        return {
            title: `Nadie coincide con “${props.q}”.`,
            text: 'Prueba con otro nombre o @usuario.',
        };
    }

    switch (props.tab) {
        case 'amigos-de-amigos':
            return {
                title: 'Aún no hay amigos de amigos.',
                text: 'Sigue a más atletas y aquí verás a quién siguen ellos.',
            };
        case 'amigos':
            return {
                title: 'Todavía no tienes amigos.',
                text: 'Cuando tú y otro atleta se sigan mutuamente, aparecerá aquí.',
            };
        case 'seguidores':
            return {
                title: 'Aún no tienes seguidores.',
                text: 'Comparte tus carreras en la comunidad para que te encuentren.',
            };
        case 'siguiendo':
            return {
                title: 'Todavía no sigues a nadie.',
                text: 'Empieza por las sugerencias: atletas con perfil público.',
            };
        case 'bloqueados':
            return {
                title: 'No has bloqueado a nadie.',
                text: 'Las personas que bloquees aparecerán aquí para que puedas desbloquearlas.',
            };
        default:
            return {
                title: 'Sin sugerencias por ahora.',
                text: 'Cuando más atletas hagan público su perfil aparecerán aquí.',
            };
    }
});
</script>

<template>
    <SeoHead
        title="Personas"
        description="Encuentra atletas en Finisher Legacy."
        noindex
    />

    <div class="fl-container max-w-4xl pt-6 pb-16 sm:pt-10">
        <Link
            :href="communityIndex()"
            class="inline-flex min-h-10 items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Comunidad
        </Link>

        <header class="mt-1">
            <h1 class="font-serif text-3xl tracking-tight sm:text-4xl">
                Personas
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Encuentra a tus compañeros de carrera y a quienes siguen tus
                amigos.
            </p>
        </header>

        <!-- Search -->
        <div class="relative mt-5" role="search">
            <label for="people-search" class="sr-only"
                >Buscar por nombre o @usuario</label
            >
            <Search
                class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <input
                id="people-search"
                v-model="term"
                type="search"
                autocomplete="off"
                placeholder="Buscar por nombre o @usuario"
                class="h-11 w-full rounded-full border border-input bg-card pr-10 pl-11 text-sm shadow-fl-micro placeholder:text-muted-foreground focus:border-foreground/30 focus:ring-2 focus:ring-ring/30 focus:outline-none [&::-webkit-search-cancel-button]:hidden"
            />
            <button
                v-if="term"
                type="button"
                class="absolute top-1/2 right-2 flex size-8 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-label="Limpiar búsqueda"
                @click="term = ''"
            >
                <X class="size-4" />
            </button>
        </div>

        <!-- Tabs -->
        <nav
            class="sticky top-16 z-20 -mx-4 mt-4 border-b border-border bg-background/90 px-4 backdrop-blur sm:mx-0 sm:px-0"
            aria-label="Listas de personas"
        >
            <div
                class="flex [scrollbar-width:none] gap-1 overflow-x-auto [&::-webkit-scrollbar]:hidden"
            >
                <Link
                    v-for="item in tabs"
                    :key="item.key"
                    :href="tabHref(item.key)"
                    preserve-scroll
                    :aria-current="tab === item.key ? 'page' : undefined"
                    class="-mb-px inline-flex min-h-12 shrink-0 items-center gap-1.5 border-b-2 px-3 text-sm font-semibold whitespace-nowrap transition-colors"
                    :class="
                        tab === item.key
                            ? 'border-foreground text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                >
                    {{ item.label }}
                    <span
                        v-if="item.count"
                        class="legacy-numeric text-xs font-medium text-muted-foreground"
                        >{{ counts[item.count] }}</span
                    >
                </Link>
            </div>
        </nav>

        <InfiniteScroll
            data="people"
            :buffer="500"
            items-element="#people-items"
            class="mt-4 transition-opacity"
            :class="searching ? 'opacity-60' : ''"
        >
            <div
                id="people-items"
                :class="
                    isGrid
                        ? 'grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4'
                        : 'divide-y divide-border'
                "
            >
                <PersonCard
                    v-for="person in rows"
                    :key="person.username ?? person.name"
                    :person="person"
                    :variant="isGrid ? 'tile' : 'row'"
                    :hide-follow="tab === 'bloqueados'"
                >
                    <template v-if="tab === 'bloqueados'" #action>
                        <Button
                            v-if="person.username"
                            variant="outline"
                            size="sm"
                            class="rounded-full"
                            @click="doUnblock(person.username)"
                        >
                            Desbloquear
                        </Button>
                    </template>
                </PersonCard>
            </div>

            <div
                v-if="!rows.length"
                class="mt-4 flex flex-col items-center rounded-card border border-dashed border-foreground/15 bg-card px-6 py-14 text-center"
            >
                <span
                    class="flex size-14 items-center justify-center rounded-full bg-fl-cream"
                >
                    <UserRoundSearch class="size-6 text-fl-gold-ink" />
                </span>
                <p class="mt-4 font-semibold">{{ empty.title }}</p>
                <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                    {{ empty.text }}
                </p>
                <Button
                    v-if="tab !== 'sugeridos' && tab !== 'bloqueados' && !q"
                    as-child
                    class="mt-5 rounded-full"
                >
                    <Link :href="peopleRoute()">Ver sugerencias</Link>
                </Button>
            </div>

            <template #loading>
                <div
                    :class="
                        isGrid
                            ? 'mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4'
                            : 'divide-y divide-border'
                    "
                    aria-live="polite"
                >
                    <div
                        v-for="n in isGrid ? 4 : 3"
                        :key="n"
                        class="animate-pulse"
                        :class="
                            isGrid
                                ? 'flex flex-col items-center rounded-card border border-border bg-card p-4'
                                : 'flex items-center gap-3 py-3'
                        "
                    >
                        <span
                            class="rounded-full bg-muted"
                            :class="isGrid ? 'size-16' : 'size-11'"
                        />
                        <span
                            class="flex-1 space-y-2"
                            :class="isGrid ? 'mt-3 w-full' : ''"
                        >
                            <span
                                class="block h-3 rounded bg-muted"
                                :class="isGrid ? 'mx-auto w-2/3' : 'w-32'"
                            />
                            <span
                                class="block h-3 rounded bg-muted"
                                :class="isGrid ? 'mx-auto w-1/2' : 'w-48'"
                            />
                        </span>
                    </div>
                    <span class="sr-only">Cargando más personas…</span>
                </div>
            </template>
        </InfiniteScroll>
    </div>
</template>
