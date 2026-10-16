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

    <div class="fl-container py-8 sm:py-12">
        <div class="grid gap-8 lg:grid-cols-12">
            <!-- Feed -->
            <section
                class="min-w-0 lg:col-span-8 xl:col-span-7 xl:col-start-2"
                aria-labelledby="community-title"
            >
                <p class="fl-eyebrow">Comunidad</p>
                <h1
                    id="community-title"
                    class="fl-display mt-2 text-4xl sm:text-5xl"
                >
                    Lo que la comunidad está corriendo.
                </h1>

                <div class="mt-6">
                    <Composer
                        v-if="composer"
                        :composer="composer"
                        :visibility-options="visibilityOptions"
                        :limits="limits"
                    />
                    <div
                        v-else
                        class="flex flex-col gap-4 rounded-xl border border-border bg-fl-cream/60 p-6 sm:flex-row sm:items-center sm:justify-between"
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

                <nav
                    class="sticky top-16 z-10 -mx-4 mt-6 flex gap-1 border-b border-border bg-background/90 px-4 backdrop-blur sm:mx-0 sm:px-0"
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
                            class="-mb-px inline-flex min-h-11 items-center gap-2 border-b-2 px-4 text-sm font-medium transition-colors"
                            :class="
                                tab === item.key
                                    ? 'border-foreground text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            "
                            :aria-current="
                                tab === item.key ? 'page' : undefined
                            "
                        >
                            <component
                                :is="item.icon"
                                class="size-4"
                                :class="
                                    tab === item.key ? 'text-fl-gold-ink' : ''
                                "
                            />
                            {{ item.label }}
                        </Link>
                    </template>
                    <Link
                        v-if="user"
                        :href="connections()"
                        class="ml-auto hidden min-h-11 items-center text-xs text-muted-foreground hover:text-foreground sm:inline-flex"
                    >
                        Mis conexiones
                    </Link>
                </nav>

                <InfiniteScroll
                    data="posts"
                    :buffer="600"
                    class="mt-6 space-y-5"
                    items-element="#community-feed-items"
                >
                    <div id="community-feed-items" class="space-y-5">
                        <CommunityPost
                            v-for="post in visiblePosts"
                            :key="post.uuid"
                            :post="post"
                            @deleted="onDeleted"
                        />
                    </div>

                    <div
                        v-if="!visiblePosts.length"
                        class="rounded-xl border border-dashed border-foreground/15 bg-card p-10 text-center text-muted-foreground"
                    >
                        {{ emptyCopy }}
                    </div>

                    <template #loading>
                        <div class="space-y-5" aria-live="polite">
                            <div
                                v-for="n in 2"
                                :key="n"
                                class="fl-card animate-pulse p-5"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="size-11 rounded-full bg-muted"
                                    />
                                    <span class="h-3 w-40 rounded bg-muted" />
                                </div>
                                <span
                                    class="mt-4 block h-3 w-3/4 rounded bg-muted"
                                />
                                <span
                                    class="mt-2 block h-40 rounded-lg bg-muted"
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

            <!-- Right rail -->
            <aside class="space-y-5 lg:col-span-4 xl:col-span-3">
                <div class="space-y-5 lg:sticky lg:top-24">
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
