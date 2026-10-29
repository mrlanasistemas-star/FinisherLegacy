<script setup lang="ts">
/**
 * Comunidad — the Instagram-style sports feed. A centered post column
 * (people strip, composer, Para ti / Siguiendo / Eventos tabs, infinite
 * list) and, on desktop, a right rail with your mini profile,
 * "Sugerencias para ti" and upcoming events. Ranking and privacy come
 * from FeedQuery/SocialVisibility; the list grows with <InfiniteScroll>
 * (merged `posts` scroll prop) so the URL and scroll position stay put.
 * `?publicar=1` (navbar "+") opens the upload modal.
 */
import { InfiniteScroll, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, Sparkles, Users } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import AthleteSuggestions from '@/components/community/AthleteSuggestions.vue';
import CommunityPost from '@/components/community/CommunityPost.vue';
import CommunityReportDialog from '@/components/community/CommunityReportDialog.vue';
import Composer from '@/components/community/Composer.vue';
import type { ComposerData } from '@/components/community/Composer.vue';
import PeopleStrip from '@/components/community/PeopleStrip.vue';
import UpcomingEvents from '@/components/community/UpcomingEvents.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { useAuthGate } from '@/composables/useAuthGate';
import { index as communityIndex, people } from '@/routes/community';
import { connections as profileConnections } from '@/routes/profile';
import type {
    CommunityAthlete,
    CommunityPostPage,
    EventEditionCard,
    PeopleStripItem,
    PostKind,
    ReportReason,
    VisibilityOption,
} from '@/types';

type Tab = 'para-ti' | 'siguiendo' | 'eventos';

const props = defineProps<{
    tab: Tab;
    posts: CommunityPostPage;
    openComposer?: boolean;
    peopleStrip: PeopleStripItem[];
    me: { posts: number; followers: number; following: number } | null;
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
const { requireAuth, loginUrl } = useAuthGate();

const composerOpen = ref(false);
const composerKind = ref<PostKind>('photo');

function publish(kind: PostKind = 'photo') {
    if (!requireAuth(communityIndex({ query: { publicar: 1 } }).url)) {
        return;
    }

    composerKind.value = kind;
    composerOpen.value = true;
}

onMounted(() => {
    if (!props.openComposer) {
        return;
    }

    // Drop ?publicar=1 so a refresh / redirect back doesn't reopen it.
    const url = new URL(window.location.href);
    url.searchParams.delete('publicar');
    router.replace({
        url: url.pathname + url.search,
        preserveScroll: true,
        preserveState: true,
    });

    publish('photo');
});

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

function tabHref(key: Tab) {
    return communityIndex({ query: key === 'para-ti' ? {} : { tab: key } });
}

const emptyCopy = computed(() => {
    if (props.tab === 'siguiendo') {
        return {
            title: 'Tu feed de Siguiendo está vacío.',
            text: 'Sigue a otros atletas y aquí verás sus carreras, fotos y logros.',
        };
    }

    if (props.tab === 'eventos') {
        return {
            title: 'Todavía no hay publicaciones de eventos.',
            text: 'Comparte tu próxima carrera y etiqueta el evento.',
        };
    }

    return {
        title: 'Todavía no hay publicaciones.',
        text: '¡Comparte el primer logro de la comunidad!',
    };
});

const myProfileUrl = computed(() =>
    profile.value?.username ? `/@${profile.value.username}` : null,
);
</script>

<template>
    <SeoHead
        title="Comunidad deportiva"
        description="Resultados, fotos, entrenamientos y momentos compartidos por atletas de Finisher Legacy. Celebra, comenta y sigue a otros atletas."
    />

    <div class="pb-20">
        <div
            class="mx-auto flex max-w-[1010px] justify-center gap-10 sm:px-6 lg:px-8"
        >
            <!-- Feed column -->
            <section
                class="w-full max-w-[600px] min-w-0"
                aria-labelledby="community-title"
            >
                <h1 id="community-title" class="sr-only">Comunidad</h1>

                <!-- People strip -->
                <div class="pt-4 sm:pt-8">
                    <PeopleStrip :people="peopleStrip" @publish="publish()" />
                </div>

                <div class="mt-4 px-4 sm:px-0">
                    <Composer
                        v-model:open="composerOpen"
                        v-model:kind="composerKind"
                        :composer="composer"
                        :visibility-options="visibilityOptions"
                        :limits="limits"
                    />
                </div>

                <!-- Sticky tabs -->
                <nav
                    class="sticky top-16 z-20 mt-3 border-b border-border bg-background/90 backdrop-blur sm:mt-4"
                    aria-label="Filtrar publicaciones"
                >
                    <div class="flex px-2 sm:px-0">
                        <template v-for="item in tabs" :key="item.key">
                            <Link
                                v-if="!item.authOnly || user"
                                :href="tabHref(item.key)"
                                preserve-scroll
                                class="-mb-px inline-flex min-h-12 flex-1 items-center justify-center gap-2 border-b-2 text-sm font-semibold transition-colors"
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
                                    aria-hidden="true"
                                />
                                {{ item.label }}
                            </Link>
                            <button
                                v-else
                                type="button"
                                class="-mb-px inline-flex min-h-12 flex-1 items-center justify-center gap-2 border-b-2 border-transparent text-sm font-semibold text-muted-foreground hover:text-foreground"
                                @click="requireAuth(tabHref(item.key).url)"
                            >
                                <component
                                    :is="item.icon"
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{ item.label }}
                            </button>
                        </template>
                    </div>
                </nav>

                <InfiniteScroll
                    data="posts"
                    :buffer="800"
                    class="mt-3 sm:mt-5"
                    items-element="#community-feed-items"
                >
                    <div
                        id="community-feed-items"
                        class="space-y-2 sm:space-y-6"
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
                        class="mx-4 flex flex-col items-center rounded-card border border-dashed border-foreground/15 bg-card px-6 py-14 text-center sm:mx-0"
                    >
                        <span
                            class="flex size-14 items-center justify-center rounded-full bg-fl-cream"
                        >
                            <Sparkles class="size-6 text-fl-gold-ink" />
                        </span>
                        <p class="mt-4 font-semibold">{{ emptyCopy.title }}</p>
                        <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                            {{ emptyCopy.text }}
                        </p>
                        <div class="mt-5 flex flex-wrap justify-center gap-2">
                            <Button class="rounded-full" @click="publish()">
                                Publicar
                            </Button>
                            <Button
                                v-if="tab === 'siguiendo'"
                                as-child
                                variant="outline"
                                class="rounded-full"
                            >
                                <Link :href="people()">Encontrar personas</Link>
                            </Button>
                        </div>
                    </div>

                    <template #loading>
                        <div
                            class="mt-2 space-y-2 sm:mt-6 sm:space-y-6"
                            aria-live="polite"
                        >
                            <div
                                v-for="n in 2"
                                :key="n"
                                class="animate-pulse overflow-hidden bg-card sm:rounded-card sm:border sm:border-border"
                            >
                                <div class="flex items-center gap-3 p-4">
                                    <span
                                        class="size-9 rounded-full bg-muted"
                                    />
                                    <span class="space-y-2">
                                        <span
                                            class="block h-3 w-32 rounded bg-muted"
                                        />
                                        <span
                                            class="block h-2.5 w-20 rounded bg-muted"
                                        />
                                    </span>
                                </div>
                                <span class="block aspect-[4/5] bg-muted" />
                                <div class="space-y-2 p-4">
                                    <span
                                        class="block h-3 w-24 rounded bg-muted"
                                    />
                                    <span
                                        class="block h-3 w-3/4 rounded bg-muted"
                                    />
                                </div>
                            </div>
                            <span class="sr-only"
                                >Cargando más publicaciones…</span
                            >
                        </div>
                    </template>
                </InfiniteScroll>
            </section>

            <!-- Right rail — desktop only -->
            <aside class="hidden w-[320px] shrink-0 lg:block">
                <div class="sticky top-20 space-y-7 pt-10">
                    <!-- Mini profile -->
                    <div v-if="user" class="flex items-center gap-3">
                        <component
                            :is="myProfileUrl ? Link : 'div'"
                            :href="myProfileUrl ?? undefined"
                            class="rounded-full"
                        >
                            <AthleteAvatar
                                :name="user.name"
                                :photo-url="profile?.photo_url"
                                size="md"
                            />
                        </component>
                        <div class="min-w-0 flex-1 leading-tight">
                            <component
                                :is="myProfileUrl ? Link : 'p'"
                                :href="myProfileUrl ?? undefined"
                                class="block truncate text-sm font-semibold hover:opacity-70"
                                >{{ profile?.username ?? user.name }}</component
                            >
                            <p class="truncate text-sm text-muted-foreground">
                                {{ user.name }}
                            </p>
                        </div>
                        <Link
                            v-if="myProfileUrl"
                            :href="myProfileUrl"
                            class="text-xs font-semibold text-fl-gold-ink hover:text-foreground"
                            >Ver perfil</Link
                        >
                    </div>
                    <dl
                        v-if="user && me"
                        class="-mt-3 grid grid-cols-3 rounded-card border border-border bg-card py-3 text-center"
                    >
                        <div>
                            <dt class="text-[11px] text-muted-foreground">
                                Publicaciones
                            </dt>
                            <dd class="legacy-numeric text-base font-semibold">
                                {{ me.posts }}
                            </dd>
                        </div>
                        <component
                            :is="profile?.username ? Link : 'div'"
                            :href="
                                profile?.username
                                    ? profileConnections({
                                          athleteProfile: profile.username,
                                          list: 'seguidores',
                                      }).url
                                    : undefined
                            "
                            class="border-x border-border hover:bg-muted/40"
                        >
                            <dt class="text-[11px] text-muted-foreground">
                                Seguidores
                            </dt>
                            <dd class="legacy-numeric text-base font-semibold">
                                {{ me.followers }}
                            </dd>
                        </component>
                        <component
                            :is="profile?.username ? Link : 'div'"
                            :href="
                                profile?.username
                                    ? profileConnections({
                                          athleteProfile: profile.username,
                                          list: 'siguiendo',
                                      }).url
                                    : undefined
                            "
                            class="hover:bg-muted/40"
                        >
                            <dt class="text-[11px] text-muted-foreground">
                                Siguiendo
                            </dt>
                            <dd class="legacy-numeric text-base font-semibold">
                                {{ me.following }}
                            </dd>
                        </component>
                    </dl>
                    <div
                        v-if="!user"
                        class="rounded-card border border-border bg-card p-5"
                    >
                        <p class="font-serif text-xl leading-snug">
                            Tu historia también cuenta.
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Comparte tus carreras, celebra las de otros y sigue
                            a tus atletas favoritos.
                        </p>
                        <div class="mt-4 flex gap-2">
                            <Button as-child class="rounded-full">
                                <a :href="loginUrl(undefined, 'registro')"
                                    >Crear cuenta</a
                                >
                            </Button>
                            <Button
                                as-child
                                variant="outline"
                                class="rounded-full"
                            >
                                <a :href="loginUrl()">Iniciar sesión</a>
                            </Button>
                        </div>
                    </div>

                    <AthleteSuggestions :athletes="suggestedAthletes" />
                    <UpcomingEvents :events="upcomingEvents" />

                    <p class="text-[11px] text-muted-foreground/80">
                        © {{ new Date().getFullYear() }} Finisher Legacy
                    </p>
                </div>
            </aside>
        </div>
    </div>

    <CommunityReportDialog v-if="user" :reasons="reportReasons" />
</template>
