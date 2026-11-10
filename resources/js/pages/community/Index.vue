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
import {
    ArrowRight,
    CalendarDays,
    Check,
    Medal,
    Sparkles,
    Timer,
    UserRoundPlus,
    Users,
} from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import AthleteSuggestions from '@/components/community/AthleteSuggestions.vue';
import CommunityPost from '@/components/community/CommunityPost.vue';
import CommunityReportDialog from '@/components/community/CommunityReportDialog.vue';
import Composer from '@/components/community/Composer.vue';
import type { ComposerData } from '@/components/community/Composer.vue';
import FeedSkeleton from '@/components/community/FeedSkeleton.vue';
import PeopleStrip from '@/components/community/PeopleStrip.vue';
import UpcomingEvents from '@/components/community/UpcomingEvents.vue';
import FeatureGrid from '@/components/marketing/FeatureGrid.vue';
import PageHero from '@/components/marketing/PageHero.vue';
import PhoneFrame from '@/components/marketing/PhoneFrame.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useAuthGate } from '@/composables/useAuthGate';
import { vReveal } from '@/composables/useReveal';
import { useI18n } from '@/i18n';
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
const { t } = useI18n();

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

const tabs = computed<
    { key: Tab; label: string; icon: typeof Sparkles; authOnly: boolean }[]
>(() => [
    { key: 'para-ti', label: t('Para ti'), icon: Sparkles, authOnly: false },
    { key: 'siguiendo', label: t('Siguiendo'), icon: Users, authOnly: true },
    {
        key: 'eventos',
        label: t('Eventos'),
        icon: CalendarDays,
        authOnly: false,
    },
]);

function tabHref(key: Tab) {
    return communityIndex({ query: key === 'para-ti' ? {} : { tab: key } });
}

const emptyCopy = computed(() => {
    if (props.tab === 'siguiendo') {
        return {
            title: t('Tu feed de Siguiendo está vacío.'),
            text: t(
                'Sigue a otros atletas y aquí verás sus carreras, fotos y logros.',
            ),
        };
    }

    if (props.tab === 'eventos') {
        return {
            title: t('Todavía no hay publicaciones de eventos.'),
            text: t('Comparte tu próxima carrera y etiqueta el evento.'),
        };
    }

    return {
        title: t('Todavía no hay publicaciones.'),
        text: t('¡Comparte el primer logro de la comunidad!'),
    };
});

// Guest teaser: what the community is, before the real public feed.
const signUpHref = computed(() => loginUrl(page.url, 'registro'));
const signInHref = computed(() => loginUrl(page.url));

const guestBenefits = computed(() => [
    {
        icon: Sparkles,
        title: t('Un feed de puro deporte'),
        text: t(
            'Carreras, entrenamientos, fotos y momentos de atletas de running, triatlón, ciclismo, HYROX y más.',
        ),
    },
    {
        icon: Timer,
        title: t('Tus resultados, en tu perfil'),
        text: t(
            'Cada carrera que terminas queda en tu historial, con su tiempo y su distancia.',
        ),
    },
    {
        icon: Medal,
        title: t('Tu vitrina de medallas'),
        text: t(
            'Guarda cada medalla con su foto y su historia, y decide quién puede verla.',
        ),
    },
    {
        icon: UserRoundPlus,
        title: t('Sigue y celebra'),
        text: t(
            'Sigue a tus amigos y a tus atletas favoritos, celebra sus logros y coméntalos.',
        ),
    },
]);

const myProfileUrl = computed(() =>
    profile.value?.username ? `/@${profile.value.username}` : null,
);
</script>

<template>
    <SeoHead
        :title="$t('Comunidad deportiva')"
        :description="
            $t(
                'Resultados, fotos, entrenamientos y momentos compartidos por atletas de Finisher Legacy. Celebra, comenta y sigue a otros atletas.',
            )
        "
    />

    <!-- Guests: explain the community before showing the public feed. -->
    <template v-if="!user">
        <PageHero
            :eyebrow="$t('Comunidad')"
            :title="$t('Tu deporte,')"
            :accent="$t('en buena compañía.')"
            :lede="
                $t(
                    'La red social de Finisher Legacy: comparte tus carreras y entrenamientos, guarda tus resultados y medallas, y sigue a quienes corren, nadan y ruedan como tú.',
                )
            "
        >
            <template #actions>
                <a :href="signUpHref" class="fl-btn-primary">
                    {{ $t('Crear mi cuenta') }}
                    <ArrowRight class="size-4" aria-hidden="true" />
                </a>
                <a :href="signInHref" class="fl-btn-secondary">{{
                    $t('Entrar')
                }}</a>
            </template>
            <p class="text-sm text-muted-foreground">
                {{
                    $t('Crear tu cuenta es gratis. Tú decides qué es público.')
                }}
            </p>
            <template #visual>
                <div class="relative flex items-end justify-center gap-5">
                    <PhoneFrame
                        src="/images/app/eventos.webp"
                        :alt="
                            $t('Pantalla de eventos en la app Finisher Legacy')
                        "
                        tilt="left"
                        class="hidden w-52 translate-y-6 sm:block"
                    />
                    <PhoneFrame
                        src="/images/app/inicio.webp"
                        :alt="
                            $t('Pantalla de inicio de la app Finisher Legacy')
                        "
                        float
                        eager
                        class="w-60"
                    />
                </div>
            </template>
        </PageHero>

        <section class="fl-container pt-8 pb-6 sm:pt-10 sm:pb-10">
            <FeatureGrid :items="guestBenefits" :columns="4" />
        </section>

        <div class="fl-container pt-8 sm:pt-12">
            <div
                v-reveal
                class="mx-auto flex max-w-[1010px] flex-col gap-1 border-t border-border pt-8 sm:px-6 lg:px-8"
            >
                <p class="fl-eyebrow">{{ $t('En la comunidad') }}</p>
                <h2 class="fl-display text-2xl sm:text-3xl">
                    {{ $t('Lo que se está compartiendo') }}
                </h2>
            </div>
        </div>
    </template>

    <div class="pb-20">
        <div
            class="mx-auto flex max-w-[1010px] justify-center gap-10 sm:px-6 lg:px-8"
        >
            <!-- Feed column -->
            <section
                class="w-full max-w-[600px] min-w-0"
                aria-labelledby="community-title"
            >
                <h1 id="community-title" class="sr-only">
                    {{ $t('Comunidad') }}
                </h1>

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
                    class="sticky top-14 z-20 mt-3 border-b border-border bg-background/90 backdrop-blur sm:mt-4"
                    :aria-label="$t('Filtrar publicaciones')"
                >
                    <div class="relative flex">
                        <!-- Sliding indicator (same component across tabs, so it animates) -->
                        <span
                            class="pointer-events-none absolute bottom-0 left-0 h-0.5 w-1/3 transition-transform duration-500 ease-[var(--legacy-ease)] motion-reduce:transition-none"
                            :style="{
                                transform: `translateX(${
                                    Math.max(
                                        0,
                                        tabs.findIndex(
                                            (entry) => entry.key === tab,
                                        ),
                                    ) * 100
                                }%)`,
                            }"
                            aria-hidden="true"
                        >
                            <span
                                class="mx-auto block h-full w-14 rounded-full bg-foreground"
                            />
                        </span>
                        <template v-for="item in tabs" :key="item.key">
                            <Link
                                v-if="!item.authOnly || user"
                                :href="tabHref(item.key)"
                                preserve-scroll
                                class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 text-sm font-semibold transition-colors duration-300 focus-visible:bg-fl-gold/10 focus-visible:outline-none"
                                :class="
                                    tab === item.key
                                        ? 'text-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
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
                                class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 text-sm font-semibold text-muted-foreground transition-colors duration-300 hover:text-foreground focus-visible:bg-fl-gold/10 focus-visible:outline-none"
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
                            v-reveal="{ y: 14 }"
                            :post="post"
                            @deleted="onDeleted"
                        />
                    </div>

                    <div
                        v-if="!visiblePosts.length"
                        v-reveal
                        class="mx-4 flex flex-col items-center rounded-card border border-dashed border-fl-gold/40 bg-card bg-[radial-gradient(70%_60%_at_50%_0%,rgb(201_174_139/0.14),transparent_70%)] px-6 py-14 text-center sm:mx-0"
                    >
                        <span
                            class="fl-empty-float flex size-16 items-center justify-center rounded-full bg-fl-cream shadow-fl-soft"
                        >
                            <Sparkles class="size-7 text-fl-gold-ink" />
                        </span>
                        <p class="mt-4 font-semibold">{{ emptyCopy.title }}</p>
                        <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                            {{ emptyCopy.text }}
                        </p>
                        <div class="mt-5 flex flex-wrap justify-center gap-2">
                            <Button class="rounded-full" @click="publish()">
                                {{ $t('Publicar') }}
                            </Button>
                            <Button
                                v-if="tab === 'siguiendo'"
                                as-child
                                variant="outline"
                                class="rounded-full"
                            >
                                <Link :href="people()">{{
                                    $t('Encontrar personas')
                                }}</Link>
                            </Button>
                        </div>
                    </div>

                    <template #next="{ loading, hasMore }">
                        <div
                            v-if="loading"
                            class="mt-2 sm:mt-6"
                            aria-live="polite"
                        >
                            <FeedSkeleton />
                            <p
                                class="flex items-center justify-center gap-2 py-5 text-sm text-muted-foreground"
                            >
                                <Spinner class="text-fl-gold-dim" />
                                {{ $t('Cargando más publicaciones…') }}
                            </p>
                        </div>
                        <div
                            v-else-if="!hasMore && visiblePosts.length > 2"
                            class="flex flex-col items-center gap-2 px-6 py-10 text-center"
                        >
                            <span
                                class="flex size-11 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                            >
                                <Check class="size-5" aria-hidden="true" />
                            </span>
                            <p class="text-sm font-semibold">
                                {{ $t('Estás al día') }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    $t(
                                        'Ya viste todas las publicaciones de esta sección.',
                                    )
                                }}
                            </p>
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
                            >{{ $t('Ver perfil') }}</Link
                        >
                    </div>
                    <dl
                        v-if="user && me"
                        class="-mt-3 grid grid-cols-3 rounded-card border border-border bg-card py-3 text-center"
                    >
                        <div>
                            <dt class="text-[11px] text-muted-foreground">
                                {{ $t('Publicaciones') }}
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
                                {{ $t('Seguidores') }}
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
                                {{ $t('Siguiendo') }}
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
                            {{ $t('Tu historia también cuenta.') }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                $t(
                                    'Comparte tus carreras, celebra las de otros y sigue a tus atletas favoritos.',
                                )
                            }}
                        </p>
                        <div class="mt-4 flex gap-2">
                            <Button as-child class="rounded-full">
                                <a :href="signUpHref">{{
                                    $t('Crear cuenta')
                                }}</a>
                            </Button>
                            <Button
                                as-child
                                variant="outline"
                                class="rounded-full"
                            >
                                <a :href="signInHref">{{ $t('Entrar') }}</a>
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

<style scoped>
.fl-empty-float {
    animation: fl-empty-float 4s ease-in-out infinite alternate;
}

@keyframes fl-empty-float {
    to {
        transform: translateY(-6px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-empty-float {
        animation: none;
    }
}
</style>
