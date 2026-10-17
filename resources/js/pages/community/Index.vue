<script setup lang="ts">
/**
 * Comunidad — the sports feed. Tabs Para ti / Siguiendo / Eventos come
 * from FeedQuery (ranked, cursor-paginated); the list grows with Inertia
 * <InfiniteScroll> (IntersectionObserver, merged `posts` scroll prop) so
 * the URL and scroll position stay put.
 */
import { InfiniteScroll, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, Loader2, Sparkles, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import AthleteSuggestions from '@/components/community/AthleteSuggestions.vue';
import CommunityPost from '@/components/community/CommunityPost.vue';
import CommunityReportDialog from '@/components/community/CommunityReportDialog.vue';
import Composer from '@/components/community/Composer.vue';
import type { ComposerData } from '@/components/community/Composer.vue';
import UpcomingEvents from '@/components/community/UpcomingEvents.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';
import { connections, index as communityIndex } from '@/routes/community';
import type {
    CommunityAthlete,
    CommunityPostPage,
    EventEditionCard,
    ReportReason,
    VisibilityOption,
} from '@/types';

type Tab = 'para-ti' | 'siguiendo' | 'eventos';

const props = defineProps<{
    tab: Tab;
    posts: CommunityPostPage;
    suggestedAthletes: CommunityAthlete[];
    upcomingEvents: EventEditionCard[];
    composer: ComposerData | null;
    visibilityOptions: VisibilityOption[];
    reportReasons: ReportReason[];
    limits: { caption_max: number; max_photos: number; photo_max_kb: number };
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const profile = computed(() => page.props.auth.profile);

// Deleted posts disappear locally — no reload of an already-merged feed.
const removed = ref(new Set<string>());
const visiblePosts = computed(() =>
    props.posts.data.filter((post) => !removed.value.has(post.uuid)),
);

function onDeleted(uuid: string) {
    removed.value = new Set([...removed.value, uuid]);
}

const tabs = [
    { key: 'para-ti', label: 'Para ti', icon: Sparkles, authOnly: false },
    { key: 'siguiendo', label: 'Siguiendo', icon: Users, authOnly: true },
    { key: 'eventos', label: 'Eventos', icon: CalendarDays, authOnly: false },
] as const;

const emptyCopy = computed(() => {
    if (props.tab === 'siguiendo') {
        return 'Aún no sigues a nadie. Descubre atletas en “Para ti” o en las sugerencias.';
    }

    if (props.tab === 'eventos') {
        return 'Todavía no hay publicaciones de eventos. Comparte tu próxima carrera.';
    }

    return 'Todavía no hay publicaciones. ¡Comparte el primer logro!';
});
</script>

<template>
    <SeoHead
        title="Comunidad deportiva"
        description="Resultados, fotos, entrenamientos y momentos compartidos por atletas de Finisher Legacy. Celebra, comenta y sigue a otros atletas."
    />

    <div class="pb-16">
        <div
            class="mx-auto flex max-w-5xl justify-center gap-8 sm:px-6 lg:px-8"
        >
            <!-- Feed -->
            <section
                class="w-full max-w-[600px] min-w-0"
                aria-labelledby="community-title"
            >
                <!-- Compact app-style header -->
                <header class="px-4 pt-6 sm:px-0 sm:pt-10">
                    <p class="fl-eyebrow">Comunidad</p>
                    <h1 id="community-title" class="fl-h2 mt-1 font-serif">
                        Lo que la comunidad corre hoy.
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Resultados, fotos y momentos de la pista a la meta.
                    </p>
                </header>

                <div class="mt-5 px-4 sm:px-0">
                    <Composer
                        v-if="composer"
                        :composer="composer"
                        :visibility-options="visibilityOptions"
                        :limits="limits"
                    />
                    <div
                        v-else
                        class="flex flex-col gap-4 rounded-card border border-border bg-fl-cream/60 p-5 shadow-fl-soft sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <p class="font-serif text-xl">
                                Tu historia también cuenta.
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Comparte tus carreras, celebra las de otros y
                                sigue a tus atletas favoritos.
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <Button as-child class="rounded-full">
                                <Link :href="register()">Crear cuenta</Link>
                            </Button>
                            <Button
                                as-child
                                variant="outline"
                                class="rounded-full"
                            >
                                <Link :href="login()">Iniciar sesión</Link>
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Sticky segmented tabs -->
                <nav
                    class="sticky top-16 z-20 mt-4 bg-background/90 px-4 py-2.5 backdrop-blur sm:px-0"
                    aria-label="Filtrar publicaciones"
                >
                    <div
                        class="flex [scrollbar-width:none] items-center gap-2 overflow-x-auto"
                    >
                        <div
                            class="inline-flex shrink-0 rounded-full border border-border bg-card p-1"
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
                                    class="inline-flex min-h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold transition-colors"
                                    :class="
                                        tab === item.key
                                            ? 'bg-fl-cream text-fl-gold-ink shadow-fl-micro'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    :aria-current="
                                        tab === item.key ? 'page' : undefined
                                    "
                                >
                                    <component
                                        :is="item.icon"
                                        class="size-4"
                                        :class="
                                            tab === item.key
                                                ? 'text-fl-gold-ink'
                                                : ''
                                        "
                                        aria-hidden="true"
                                    />
                                    {{ item.label }}
                                </Link>
                            </template>
                        </div>
                        <Link
                            v-if="user"
                            :href="connections()"
                            class="ml-auto hidden min-h-10 shrink-0 items-center text-xs text-muted-foreground hover:text-foreground sm:inline-flex"
                        >
                            Mis conexiones
                        </Link>
                    </div>
                </nav>

                <InfiniteScroll
                    data="posts"
                    :buffer="600"
                    class="mt-2 space-y-3 sm:mt-4 sm:space-y-5"
                    items-element="#community-feed-items"
                >
                    <div
                        id="community-feed-items"
                        class="space-y-3 sm:space-y-5"
                    >
                        <CommunityPost
                            v-for="post in visiblePosts"
                            :key="post.uuid"
                            :post="post"
                            @deleted="onDeleted"
                        />
                    </div>

                    <div
                        v-if="!visiblePosts.length"
                        class="mx-4 rounded-card border border-dashed border-foreground/15 bg-card p-10 text-center text-muted-foreground sm:mx-0"
                    >
                        {{ emptyCopy }}
                    </div>

                    <template #loading>
                        <div class="space-y-3 sm:space-y-5" aria-live="polite">
                            <div
                                v-for="n in 2"
                                :key="n"
                                class="animate-pulse bg-card p-5 sm:rounded-card sm:border sm:border-border sm:shadow-fl-soft"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="size-9 rounded-full bg-muted"
                                    />
                                    <span class="h-3 w-40 rounded bg-muted" />
                                </div>
                                <span
                                    class="mt-4 block h-3 w-3/4 rounded bg-muted"
                                />
                                <span
                                    class="mt-2 block h-40 rounded-media bg-muted"
                                />
                            </div>
                            <p class="sr-only">
                                <Loader2 class="size-4 animate-spin" /> Cargando
                                más publicaciones…
                            </p>
                        </div>
                    </template>
                </InfiniteScroll>
            </section>

            <!-- Right rail — desktop only (hidden on tablet + phone). -->
            <aside class="hidden w-80 shrink-0 lg:block">
                <div class="sticky top-24 space-y-5 pt-10">
                    <div
                        v-if="user"
                        class="flex items-center gap-3 rounded-card border border-border bg-card p-4 shadow-fl-soft"
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
                                >Ver mi perfil</Link
                            >
                        </div>
                    </div>
                    <UpcomingEvents :events="upcomingEvents" />
                    <AthleteSuggestions :athletes="suggestedAthletes" />
                </div>
            </aside>
        </div>
    </div>

    <CommunityReportDialog v-if="user" :reasons="reportReasons" />
</template>
