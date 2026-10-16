<script setup lang="ts">
/**
 * Public Legacy Profile (/@username) — header, stats, legacy highlights,
 * posts (grid or list, infinite), races, medals, photos. Shows only what
 * this viewer may see (the controller filtered everything); a
 * followers-only profile seen by a non-follower arrives `restricted`
 * (header + Seguir, no content).
 */
import { Head, InfiniteScroll, Link, router, usePage } from '@inertiajs/vue3';
import {
    Award,
    Ban,
    Flag,
    LayoutGrid,
    List,
    Lock,
    MoreHorizontal,
    PenLine,
    Share2,
    Timer,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import CommunityPost from '@/components/community/CommunityPost.vue';
import CommunityReportDialog from '@/components/community/CommunityReportDialog.vue';
import FollowButton from '@/components/community/FollowButton.vue';
import LegacyTimelineList from '@/components/profile/LegacyTimelineList.vue';
import type { TimelineEntry } from '@/components/profile/LegacyTimelineList.vue';
import ProfileHeader from '@/components/profile/ProfileHeader.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCanonicalUrl } from '@/composables/useCanonicalUrl';
import { useCommunityReport } from '@/composables/useCommunityReport';
import { shortDate } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';
import { block } from '@/routes/athletes';
import { show as showPost } from '@/routes/community/posts';
import { edit as editProfile } from '@/routes/dashboard/profile';
import { connections as profileConnections } from '@/routes/profile';
import type {
    CommunityPost as CommunityPostData,
    CommunityPostPage,
    PublicAthleteMedal,
    PublicAthleteProfile,
    ReportReason,
} from '@/types';

type PublicEvent = {
    event: string | null;
    event_slug: string | null;
    edition: string | null;
    event_date: string | null;
    race: string | null;
    distance: string | null;
    official_time: string | null;
    pace: string | null;
};

const props = defineProps<{
    isOwner: boolean;
    restricted: boolean;
    profile: PublicAthleteProfile & {
        visibility?: 'public' | 'followers' | 'private';
    };
    stats: { medals: number; events: number; posts: number };
    medals: PublicAthleteMedal[];
    social: { followers: number; following: number; is_following: boolean };
    events: PublicEvent[];
    posts: CommunityPostPage;
    photos: { uuid: string; url: string; event: string | null }[];
    reportReasons: ReportReason[];
}>();

const page = usePage();
const signedIn = computed(() => !!page.props.auth.user);
const canonicalUrl = useCanonicalUrl();
const { openReport } = useCommunityReport();

type Tab = 'publicaciones' | 'historia' | 'logros' | 'fotos';
const tabs = computed(() =>
    (
        [
            {
                key: 'publicaciones',
                label: 'Publicaciones',
                count: props.stats.posts,
            },
            { key: 'historia', label: 'Historia', count: props.events.length },
            { key: 'logros', label: 'Logros', count: props.medals.length },
            { key: 'fotos', label: 'Fotos', count: props.photos.length },
        ] as { key: Tab; label: string; count: number }[]
    ).filter(
        (tab) =>
            tab.key === 'publicaciones' ||
            tab.key === 'historia' ||
            tab.count > 0,
    ),
);
const activeTab = ref<Tab>(
    props.stats.posts > 0 || props.events.length === 0
        ? 'publicaciones'
        : 'historia',
);
const layout = ref<'grid' | 'list'>('list');

const removed = ref(new Set<string>());
const visiblePosts = computed(() =>
    props.posts.data.filter((post) => !removed.value.has(post.uuid)),
);

const location = computed(() =>
    [props.profile.city, props.profile.state, props.profile.country]
        .filter(Boolean)
        .join(', '),
);

const headerStats = computed(() => [
    { label: 'Eventos', value: props.stats.events },
    { label: 'Medallas', value: props.stats.medals },
    { label: 'Publicaciones', value: props.stats.posts },
    {
        label: 'Seguidores',
        value: props.social.followers,
        href: props.restricted
            ? null
            : profileConnections({
                  athleteProfile: props.profile.username,
                  list: 'seguidores',
              }).url,
    },
    {
        label: 'Siguiendo',
        value: props.social.following,
        href: props.restricted
            ? null
            : profileConnections({
                  athleteProfile: props.profile.username,
                  list: 'siguiendo',
              }).url,
    },
    { label: 'Fotos', value: props.photos.length },
]);

// Legacy highlights: the three most recent races with an official time.
const highlights = computed(() =>
    props.events.filter((event) => event.official_time).slice(0, 3),
);

const timeline = computed<TimelineEntry[]>(() =>
    props.events.map((event, index) => ({
        key: `${event.event_slug}-${index}`,
        date: event.event_date,
        title: event.event ?? 'Evento',
        subtitle: event.race,
        distance: event.distance,
        time: event.official_time,
        pace: event.pace,
        href: event.event_slug ? `/events/${event.event_slug}` : null,
    })),
);

function tileImage(post: CommunityPostData): string | null {
    return (
        post.media.find((item) => item.type !== 'video')?.url ??
        post.medal?.image_url ??
        null
    );
}

async function share() {
    const url = canonicalUrl ?? window.location.href;

    try {
        if (typeof navigator.share === 'function') {
            await navigator.share({ title: props.profile.name, url });
        } else {
            await navigator.clipboard.writeText(url);
            toast.success('Enlace copiado.');
        }
    } catch {
        // Share sheet dismissed.
    }
}

async function blockAthlete() {
    const confirmed = await confirmAction({
        title: `¿Bloquear a ${props.profile.name}?`,
        text: 'Ya no verán el perfil ni las publicaciones del otro, y se quitarán los seguimientos entre ustedes. Puedes desbloquear cuando quieras desde Mis conexiones.',
        confirmButtonText: 'Bloquear',
    });

    if (confirmed) {
        router.visit(block(props.profile.username));
    }
}

const metaDescription = computed(
    () =>
        props.profile.bio ??
        `El perfil de ${props.profile.name} en Finisher Legacy: ${props.stats.events} eventos y ${props.stats.medals} medallas.`,
);

// ProfilePage/Person structured data — only fields the page renders.
const profileJsonLd = computed(() => {
    const person: Record<string, unknown> = {
        '@type': 'Person',
        name: props.profile.name,
        alternateName: `@${props.profile.username}`,
    };

    if (props.profile.photo_url) {
        person.image = props.profile.photo_url;
    }

    if (props.profile.bio) {
        person.description = props.profile.bio;
    }

    if (canonicalUrl) {
        person.url = canonicalUrl;
    }

    return {
        '@context': 'https://schema.org',
        '@type': 'ProfilePage',
        mainEntity: person,
        ...(canonicalUrl ? { url: canonicalUrl } : {}),
    };
});
</script>

<template>
    <SeoHead
        :title="`${profile.name} (@${profile.username})`"
        :description="metaDescription"
        :image="profile.photo_url"
        type="profile"
        :noindex="profile.visibility !== 'public'"
    />
    <Head>
        <meta property="profile:username" :content="profile.username" />
        <script type="application/ld+json">
            {{ JSON.stringify(profileJsonLd) }}
        </script>
    </Head>

    <div class="fl-container py-6 sm:py-8">
        <ProfileHeader
            :name="profile.name"
            :username="profile.username"
            :bio="restricted ? null : profile.bio"
            :location="restricted ? null : location"
            :sports="profile.sport ? [profile.sport] : []"
            :photo-url="profile.photo_url"
            :cover-url="restricted ? null : profile.cover_url"
            :stats="
                restricted
                    ? headerStats.filter(
                          (s) =>
                              s.label === 'Seguidores' ||
                              s.label === 'Siguiendo',
                      )
                    : headerStats
            "
        >
            <template #actions>
                <Button
                    v-if="isOwner"
                    as-child
                    variant="outline"
                    class="rounded-full"
                >
                    <Link :href="editProfile()">
                        <PenLine class="size-4" />
                        Editar perfil
                    </Link>
                </Button>
                <FollowButton
                    v-else
                    :username="profile.username"
                    :name="profile.name"
                    :following="social.is_following"
                    :only="restricted ? [] : ['social']"
                />
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-11 rounded-full"
                    aria-label="Compartir perfil"
                    @click="share"
                >
                    <Share2 class="size-4" />
                </Button>
                <DropdownMenu v-if="signedIn && !isOwner">
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="size-11 rounded-full"
                            aria-label="Más opciones"
                        >
                            <MoreHorizontal class="size-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-52">
                        <DropdownMenuItem
                            @select="
                                openReport({
                                    type: 'profile',
                                    id: profile.username,
                                })
                            "
                        >
                            <Flag class="mr-2 size-4" />
                            Reportar perfil
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            class="text-destructive focus:text-destructive"
                            @select="blockAthlete"
                        >
                            <Ban class="mr-2 size-4" />
                            Bloquear
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </template>
        </ProfileHeader>

        <!-- Followers-only, not following yet -->
        <section
            v-if="restricted"
            class="mx-auto mt-12 max-w-md rounded-2xl border border-border bg-card p-8 text-center"
        >
            <span
                class="mx-auto flex size-14 items-center justify-center rounded-full bg-fl-cream"
            >
                <Lock class="size-6 text-fl-gold-ink" />
            </span>
            <h2 class="fl-display mt-5 text-2xl">
                Su historia es para sus seguidores.
            </h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Sigue a {{ profile.name }} para ver sus carreras, medallas y
                publicaciones.
            </p>
        </section>

        <template v-else>
            <!-- Legacy highlights -->
            <section
                v-if="highlights.length"
                class="mt-10"
                aria-labelledby="highlights-title"
            >
                <h2 id="highlights-title" class="fl-eyebrow">
                    Destacados de su legado
                </h2>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    <component
                        :is="item.event_slug ? Link : 'div'"
                        v-for="(item, index) in highlights"
                        :key="`${item.event_slug}-${index}`"
                        :href="
                            item.event_slug
                                ? `/events/${item.event_slug}`
                                : undefined
                        "
                        class="fl-card fl-hover-lift block p-4"
                    >
                        <p class="truncate text-xs text-muted-foreground">
                            {{
                                [item.distance, shortDate(item.event_date)]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </p>
                        <p
                            class="mt-1 truncate font-serif text-lg leading-tight"
                        >
                            {{ item.event }}
                        </p>
                        <p
                            class="legacy-numeric mt-3 flex items-center gap-2 font-serif text-3xl"
                        >
                            <Timer
                                class="size-4 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            {{ item.official_time }}
                        </p>
                    </component>
                </div>
            </section>

            <div class="mt-10">
                <div class="flex items-center gap-1 border-b border-border">
                    <div
                        class="flex flex-1 gap-1 overflow-x-auto"
                        role="tablist"
                        aria-label="Secciones del perfil"
                    >
                        <button
                            v-for="tab in tabs"
                            :id="`tab-${tab.key}`"
                            :key="tab.key"
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === tab.key"
                            :aria-controls="`panel-${tab.key}`"
                            class="-mb-px min-h-11 shrink-0 border-b-2 px-4 text-sm font-medium transition-colors"
                            :class="
                                activeTab === tab.key
                                    ? 'border-foreground text-foreground'
                                    : 'border-transparent text-muted-foreground hover:text-foreground'
                            "
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                            <span
                                v-if="tab.count"
                                class="ml-1 text-xs text-muted-foreground"
                                >{{ tab.count }}</span
                            >
                        </button>
                    </div>
                    <div
                        v-if="
                            activeTab === 'publicaciones' && visiblePosts.length
                        "
                        class="flex shrink-0 gap-1 pb-1"
                        role="group"
                        aria-label="Vista"
                    >
                        <button
                            type="button"
                            class="flex size-10 items-center justify-center rounded-full"
                            :class="
                                layout === 'list'
                                    ? 'bg-muted text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-pressed="layout === 'list'"
                            aria-label="Ver como lista"
                            @click="layout = 'list'"
                        >
                            <List class="size-4" />
                        </button>
                        <button
                            type="button"
                            class="flex size-10 items-center justify-center rounded-full"
                            :class="
                                layout === 'grid'
                                    ? 'bg-muted text-foreground'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-pressed="layout === 'grid'"
                            aria-label="Ver como cuadrícula"
                            @click="layout = 'grid'"
                        >
                            <LayoutGrid class="size-4" />
                        </button>
                    </div>
                </div>

                <section
                    v-show="activeTab === 'publicaciones'"
                    id="panel-publicaciones"
                    role="tabpanel"
                    aria-labelledby="tab-publicaciones"
                    class="pt-6"
                >
                    <InfiniteScroll
                        data="posts"
                        :buffer="500"
                        items-element="#profile-posts"
                    >
                        <div
                            id="profile-posts"
                            :class="
                                layout === 'grid'
                                    ? 'grid grid-cols-3 gap-1 sm:gap-2'
                                    : 'mx-auto max-w-2xl space-y-5'
                            "
                        >
                            <template v-if="layout === 'grid'">
                                <Link
                                    v-for="post in visiblePosts"
                                    :key="post.uuid"
                                    :href="showPost(post.uuid)"
                                    class="group/tile relative block aspect-square overflow-hidden rounded-md bg-fl-cream sm:rounded-lg"
                                    :aria-label="`Publicación: ${post.kind_label}`"
                                >
                                    <img
                                        v-if="tileImage(post)"
                                        :src="tileImage(post)!"
                                        alt=""
                                        loading="lazy"
                                        class="size-full object-cover transition-transform duration-500 group-hover/tile:scale-105 motion-reduce:transition-none"
                                    />
                                    <span
                                        v-else
                                        class="flex size-full flex-col justify-between p-3 sm:p-4"
                                    >
                                        <span class="fl-eyebrow">{{
                                            post.kind_label
                                        }}</span>
                                        <span
                                            v-if="post.activity?.official_time"
                                            class="legacy-numeric font-serif text-xl sm:text-3xl"
                                            >{{
                                                post.activity.official_time
                                            }}</span
                                        >
                                        <span
                                            v-else
                                            class="line-clamp-4 font-serif text-sm leading-snug sm:text-lg"
                                            >{{ post.caption }}</span
                                        >
                                    </span>
                                </Link>
                            </template>
                            <template v-else>
                                <CommunityPost
                                    v-for="post in visiblePosts"
                                    :key="post.uuid"
                                    :post="post"
                                    @deleted="
                                        (uuid) =>
                                            (removed = new Set([
                                                ...removed,
                                                uuid,
                                            ]))
                                    "
                                />
                            </template>
                        </div>
                        <p
                            v-if="!visiblePosts.length"
                            class="py-10 text-center text-muted-foreground"
                        >
                            {{
                                isOwner
                                    ? 'Aún no publicas nada. Comparte tu primer momento en Comunidad.'
                                    : `${profile.name} aún no tiene publicaciones visibles.`
                            }}
                        </p>
                    </InfiniteScroll>
                </section>

                <section
                    v-show="activeTab === 'historia'"
                    id="panel-historia"
                    role="tabpanel"
                    aria-labelledby="tab-historia"
                    class="max-w-4xl pt-8"
                >
                    <h2 class="fl-display text-3xl sm:text-4xl">
                        Cada logro cuenta.
                    </h2>
                    <div v-if="timeline.length" class="mt-8">
                        <LegacyTimelineList :entries="timeline" />
                    </div>
                    <p v-else class="mt-4 text-muted-foreground">
                        {{ profile.name }} aún no tiene participaciones
                        públicas.
                    </p>
                </section>

                <section
                    v-show="activeTab === 'logros'"
                    id="panel-logros"
                    role="tabpanel"
                    aria-labelledby="tab-logros"
                    class="pt-8"
                >
                    <div
                        class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4"
                    >
                        <div
                            v-for="medal in medals"
                            :key="medal.id"
                            class="fl-card overflow-hidden"
                        >
                            <div class="aspect-square bg-fl-cream">
                                <img
                                    v-if="medal.thumbnail_url"
                                    :src="medal.thumbnail_url"
                                    :alt="medal.title"
                                    loading="lazy"
                                    class="size-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex size-full items-center justify-center"
                                >
                                    <Award class="size-8 text-fl-gold-ink/60" />
                                </div>
                            </div>
                            <div class="p-3">
                                <p class="truncate text-sm font-semibold">
                                    {{ medal.title }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{
                                        [
                                            medal.distance_label,
                                            shortDate(medal.event_date),
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    v-show="activeTab === 'fotos'"
                    id="panel-fotos"
                    role="tabpanel"
                    aria-labelledby="tab-fotos"
                    class="pt-8"
                >
                    <div
                        class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4"
                    >
                        <img
                            v-for="photo in photos"
                            :key="photo.uuid"
                            :src="photo.url"
                            :alt="
                                photo.event
                                    ? `${profile.name} en ${photo.event}`
                                    : `Foto de ${profile.name}`
                            "
                            loading="lazy"
                            class="aspect-square w-full rounded-lg bg-muted object-cover"
                        />
                    </div>
                </section>
            </div>
        </template>
    </div>

    <CommunityReportDialog
        v-if="signedIn && !isOwner"
        :reasons="reportReasons"
    />
</template>
