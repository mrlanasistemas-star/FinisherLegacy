<script setup lang="ts">
/**
 * Comunidad — the sports feed. Posts, tabs and sidebars all come from
 * CommunityController (MomentQuery + SocialVisibility); "Cargar más"
 * appends the next cursor page without touching the URL.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Camera,
    Compass,
    Home,
    LayoutGrid,
    Loader2,
    ShoppingBag,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import AthleteSuggestions from '@/components/community/AthleteSuggestions.vue';
import Composer from '@/components/community/Composer.vue';
import type { ComposerData } from '@/components/community/Composer.vue';
import PostCard from '@/components/community/PostCard.vue';
import UpcomingEvents from '@/components/community/UpcomingEvents.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { dashboard, home, login, register } from '@/routes';
import { index as communityIndex } from '@/routes/community';
import { index as eventsIndex } from '@/routes/events';
import { index as photosIndex } from '@/routes/photos';
import { index as storeIndex } from '@/routes/store/products';
import type {
    CommunityAthlete,
    CommunityPost,
    EventEditionCard,
    VisibilityOption,
} from '@/types';

type Tab = 'para-ti' | 'siguiendo' | 'mis-deportes';

const props = defineProps<{
    tab: Tab;
    tabSport: string | null;
    posts: {
        data: CommunityPost[];
        next_cursor: string | null;
        is_continuation: boolean;
    };
    suggestedAthletes: CommunityAthlete[];
    upcomingEvents: EventEditionCard[];
    composer: ComposerData | null;
    visibilityOptions: VisibilityOption[];
    limits: { caption_max: number; max_photos: number };
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const profile = computed(() => page.props.auth.profile);

const feed = ref<CommunityPost[]>([...props.posts.data]);
const nextCursor = ref<string | null>(props.posts.next_cursor);
const loadingMore = ref(false);

watch(
    () => props.posts,
    (posts) => {
        if (posts.is_continuation) {
            const known = new Set(feed.value.map((p) => p.uuid));
            feed.value = [
                ...feed.value,
                ...posts.data.filter((p) => !known.has(p.uuid)),
            ];
        } else {
            feed.value = [...posts.data];
        }

        nextCursor.value = posts.next_cursor;
    },
);

function loadMore() {
    if (!nextCursor.value || loadingMore.value) {
        return;
    }

    loadingMore.value = true;
    router.get(
        communityIndex().url,
        { tab: props.tab, cursor: nextCursor.value },
        {
            only: ['posts'],
            preserveState: true,
            preserveScroll: true,
            preserveUrl: true,
            onFinish: () => (loadingMore.value = false),
        },
    );
}

const tabs: { key: Tab; label: string; authOnly: boolean }[] = [
    { key: 'para-ti', label: 'Para ti', authOnly: false },
    { key: 'siguiendo', label: 'Siguiendo', authOnly: true },
    { key: 'mis-deportes', label: 'Mis deportes', authOnly: true },
];

const sideLinks = [
    { label: 'Inicio', href: home().url, icon: Home },
    { label: 'Mi legado', href: dashboard().url, icon: LayoutGrid },
    { label: 'Eventos', href: eventsIndex().url, icon: Compass },
    { label: 'Mis fotos', href: photosIndex().url, icon: Camera },
    { label: 'Tienda', href: storeIndex().url, icon: ShoppingBag },
];

const emptyCopy = computed(() => {
    if (props.tab === 'siguiendo') {
        return 'Aún no sigues a nadie. Descubre atletas en “Para ti” o en las sugerencias.';
    }

    if (props.tab === 'mis-deportes') {
        return props.tabSport
            ? `Todavía no hay publicaciones de ${props.tabSport}.`
            : 'Elige tu deporte principal en tu perfil para ver publicaciones de tu disciplina.';
    }

    return 'Todavía no hay publicaciones públicas. ¡Comparte el primer logro!';
});
</script>

<template>
    <SeoHead
        title="Comunidad deportiva"
        description="Logros, carreras, entrenamientos y momentos compartidos por atletas de Finisher Legacy. Celebra, comenta y sigue a otros atletas."
    />

    <div class="fl-container py-8 sm:py-10">
        <div class="grid gap-8 lg:grid-cols-12">
            <!-- Left rail -->
            <aside class="hidden lg:col-span-3 lg:block">
                <div class="sticky top-24 space-y-4">
                    <div
                        v-if="user"
                        class="fl-card flex items-center gap-3 p-4"
                    >
                        <AthleteAvatar
                            :name="user.name"
                            :photo-url="profile?.photo_url"
                            size="sm"
                        />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">
                                {{ user.name }}
                            </p>
                            <Link
                                v-if="profile?.username"
                                :href="`/@${profile.username}`"
                                class="truncate text-xs text-muted-foreground hover:text-foreground"
                                >@{{ profile.username }}</Link
                            >
                        </div>
                    </div>

                    <nav
                        class="rounded-xl bg-foreground p-2 text-background"
                        aria-label="Atajos"
                    >
                        <Link
                            v-for="link in sideLinks"
                            :key="link.label"
                            :href="link.href"
                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-white/75 transition-colors hover:bg-white/10 hover:text-white"
                        >
                            <component
                                :is="link.icon"
                                class="size-4 text-fl-gold"
                            />
                            {{ link.label }}
                        </Link>
                    </nav>
                </div>
            </aside>

            <!-- Feed -->
            <section class="min-w-0 lg:col-span-6" aria-label="Publicaciones">
                <h1 class="fl-display text-4xl sm:text-5xl">Comunidad</h1>
                <p class="mt-2 text-muted-foreground">
                    Historias de atletas como tú. Inspírate, comparte y celebra.
                </p>

                <div class="mt-6">
                    <Composer
                        v-if="composer"
                        :composer="composer"
                        :visibility-options="visibilityOptions"
                        :limits="limits"
                    />
                    <div
                        v-else
                        class="flex flex-col gap-4 rounded-xl bg-foreground p-6 text-background sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="font-serif text-xl">
                                Únete a la comunidad.
                            </p>
                            <p class="mt-1 text-sm text-white/70">
                                Comparte tus logros, celebra los de otros y
                                sigue a tus atletas favoritos.
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Button
                                as-child
                                class="rounded-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                            >
                                <Link :href="register()">Crear mi perfil</Link>
                            </Button>
                            <Button
                                as-child
                                variant="ghost"
                                class="rounded-full text-white hover:bg-white/10 hover:text-white"
                            >
                                <Link :href="login()">Iniciar sesión</Link>
                            </Button>
                        </div>
                    </div>
                </div>

                <nav
                    class="mt-6 flex gap-1 border-b border-border"
                    aria-label="Filtrar publicaciones"
                >
                    <template v-for="item in tabs" :key="item.key">
                        <Link
                            v-if="!item.authOnly || user"
                            :href="
                                communityIndex({
                                    query:
                                        item.key === 'para-ti'
                                            ? {}
                                            : { tab: item.key },
                                })
                            "
                            preserve-scroll
                            class="-mb-px border-b-2 px-4 py-3 text-sm font-medium transition-colors"
                            :class="
                                tab === item.key
                                    ? 'border-foreground text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            "
                            :aria-current="
                                tab === item.key ? 'page' : undefined
                            "
                        >
                            {{ item.label }}
                        </Link>
                    </template>
                </nav>

                <div class="mt-6 space-y-5">
                    <PostCard
                        v-for="post in feed"
                        :key="post.uuid"
                        :post="post"
                    />

                    <div
                        v-if="!feed.length"
                        class="rounded-xl border border-dashed border-foreground/15 bg-card p-10 text-center text-muted-foreground"
                    >
                        {{ emptyCopy }}
                    </div>

                    <div v-if="nextCursor" class="flex justify-center pt-2">
                        <Button
                            variant="outline"
                            class="rounded-full"
                            :disabled="loadingMore"
                            @click="loadMore"
                        >
                            <Loader2
                                v-if="loadingMore"
                                class="size-4 animate-spin"
                            />
                            Cargar más publicaciones
                        </Button>
                    </div>
                </div>
            </section>

            <!-- Right rail -->
            <aside class="space-y-5 lg:col-span-3">
                <div class="space-y-5 lg:sticky lg:top-24">
                    <UpcomingEvents :events="upcomingEvents" />
                    <AthleteSuggestions :athletes="suggestedAthletes" />
                </div>
            </aside>
        </div>
    </div>
</template>
