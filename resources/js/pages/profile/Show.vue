<script setup lang="ts">
/**
 * Public Legacy Profile (/@username), Instagram-style: cover + avatar,
 * @username + Seguir / Editar perfil, stats (publicaciones · seguidores
 * · siguiendo → lists), bio, "Seguido por …", then tabs: Publicaciones
 * (3-column grid, infinite), Legado (highlights + race timeline),
 * Medallas and Fotos when there are any. Shows only what this viewer may
 * see (the controller filtered everything); a followers-only profile
 * seen by a non-follower arrives `restricted` (header + Seguir only).
 */
import { Head, InfiniteScroll, Link, router, usePage } from '@inertiajs/vue3';
import {
    Award,
    Ban,
    Camera,
    Flag,
    Grid3x3,
    Images,
    Lock,
    MoreHorizontal,
    Share2,
    Timer,
    Trophy,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import CommunityReportDialog from '@/components/community/CommunityReportDialog.vue';
import FollowButton from '@/components/community/FollowButton.vue';
import { mutualsLabel } from '@/components/community/mutuals';
import LegacyTimelineList from '@/components/profile/LegacyTimelineList.vue';
import type { TimelineEntry } from '@/components/profile/LegacyTimelineList.vue';
import ProfilePostGrid from '@/components/profile/ProfilePostGrid.vue';
import PublicProfileHeader from '@/components/profile/PublicProfileHeader.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuthGate } from '@/composables/useAuthGate';
import { useCanonicalUrl } from '@/composables/useCanonicalUrl';
import { useCommunityReport } from '@/composables/useCommunityReport';
import { shortDate } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';
import { block } from '@/routes/athletes';
import { index as communityIndex } from '@/routes/community';
import { edit as editProfile } from '@/routes/dashboard/profile';
import { connections as profileConnections } from '@/routes/profile';
import type {
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
    social: {
        followers: number;
        following: number;
        is_following: boolean;
        follows_you?: boolean;
        mutual_count?: number;
        mutual_names?: string[];
    };
    events: PublicEvent[];
    posts: CommunityPostPage;
    photos: { uuid: string; url: string; event: string | null }[];
    reportReasons: ReportReason[];
}>();

const page = usePage();
const signedIn = computed(() => !!page.props.auth.user);
const canonicalUrl = useCanonicalUrl();
const { openReport } = useCommunityReport();
const { requireAuth } = useAuthGate();

type Tab = 'publicaciones' | 'legado' | 'medallas' | 'fotos';
const tabs = computed(() =>
    (
        [
            {
                key: 'publicaciones',
                label: 'Publicaciones',
                icon: Grid3x3,
                show: true,
            },
            { key: 'legado', label: 'Legado', icon: Trophy, show: true },
            {
                key: 'medallas',
                label: 'Medallas',
                icon: Award,
                show: props.medals.length > 0,
            },
            {
                key: 'fotos',
                label: 'Fotos',
                icon: Images,
                show: props.photos.length > 0,
            },
        ] as { key: Tab; label: string; icon: typeof Grid3x3; show: boolean }[]
    ).filter((tab) => tab.show),
);
const activeTab = ref<Tab>(
    props.stats.posts > 0 || props.events.length === 0
        ? 'publicaciones'
        : 'legado',
);

const removed = ref(new Set<string>());
const visiblePosts = computed(() =>
    props.posts.data.filter((post) => !removed.value.has(post.uuid)),
);

function onDeleted(uuid: string) {
    removed.value = new Set([...removed.value, uuid]);
}

const location = computed(() =>
    [props.profile.city, props.profile.state, props.profile.country]
        .filter(Boolean)
        .join(', '),
);

const formatter = new Intl.NumberFormat('es-MX', { notation: 'compact' });

const headerStats = computed(() => [
    {
        label: 'Publicaciones',
        value: props.restricted ? '—' : formatter.format(props.stats.posts),
    },
    {
        label: 'Seguidores',
        value: formatter.format(props.social.followers),
        href: props.restricted
            ? null
            : profileConnections({
                  athleteProfile: props.profile.username,
                  list: 'seguidores',
              }).url,
    },
    {
        label: 'Siguiendo',
        value: formatter.format(props.social.following),
        href: props.restricted
            ? null
            : profileConnections({
                  athleteProfile: props.profile.username,
                  list: 'siguiendo',
              }).url,
    },
]);

const mutuals = computed(() =>
    props.isOwner
        ? null
        : mutualsLabel({
              mutual_count: props.social.mutual_count,
              mutual_names: props.social.mutual_names,
          }),
);

// Legacy highlights: the three most recent races with an official time.
const highlights = computed(() =>
    props.events.filter((event) => event.official_time).slice(0, 3),
);

// A real photo per event, when the athlete made one public (never invented).
const photosByEvent = computed(() => {
    const map = new Map<string, { url: string; count: number }>();

    for (const photo of props.photos) {
        if (!photo.event) {
            continue;
        }

        const existing = map.get(photo.event);

        if (existing) {
            existing.count += 1;
        } else {
            map.set(photo.event, { url: photo.url, count: 1 });
        }
    }

    return map;
});

const timeline = computed<TimelineEntry[]>(() =>
    props.events.map((event, index) => {
        const eventPhotos = event.event
            ? photosByEvent.value.get(event.event)
            : undefined;

        return {
            key: `${event.event_slug}-${index}`,
            date: event.event_date,
            title: event.event ?? 'Evento',
            subtitle: event.race,
            distance: event.distance,
            time: event.official_time,
            pace: event.pace,
            href: event.event_slug ? `/events/${event.event_slug}` : null,
            imageUrl: eventPhotos?.url ?? null,
            photoCount: eventPhotos?.count,
        };
    }),
);

async function share() {
    const url = canonicalUrl ?? window.location.href;

    try {
        if (typeof navigator.share === 'function') {
            await navigator.share({ title: props.profile.name, url });
        } else {
            await navigator.clipboard.writeText(url);
            toast.success('Enlace del perfil copiado.');
        }
    } catch {
        // Share sheet dismissed.
    }
}

async function blockAthlete() {
    const confirmed = await confirmAction({
        title: `¿Bloquear a ${props.profile.name}?`,
        text: 'Ya no verán el perfil ni las publicaciones del otro, y se quitarán los seguimientos entre ustedes. Puedes desbloquear cuando quieras desde Personas → Bloqueados.',
        confirmButtonText: 'Bloquear',
    });

    if (confirmed) {
        router.visit(block(props.profile.username), {
            onError: () => toast.error('No pudimos bloquear a este atleta.'),
        });
    }
}

function report() {
    if (requireAuth()) {
        openReport({ type: 'profile', id: props.profile.username });
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
        <!--
            Dynamic component (not a literal <script>) on purpose: a literal
            script tag in an SFC template trips Vue's "side-effect tags are
            ignored in client templates" compiler error, which breaks this
            page's client hydration. :is="'script'" emits a real ld+json script.
        -->
        <!-- eslint-disable vue/no-v-text-v-html-on-component -- v-text is correct here: :is="'script'" is a native element, and v-text sets raw ld+json text (a slot child would HTML-escape "<" and corrupt the JSON). -->
        <component
            :is="'script'"
            type="application/ld+json"
            v-text="JSON.stringify(profileJsonLd)"
        />
        <!-- eslint-enable vue/no-v-text-v-html-on-component -->
    </Head>

    <div class="mx-auto max-w-[935px] pb-16 sm:px-6 sm:pt-6 lg:px-8">
        <PublicProfileHeader
            :name="profile.name"
            :username="profile.username"
            :bio="restricted ? null : profile.bio"
            :location="restricted ? null : location"
            :sport="profile.sport"
            :photo-url="profile.photo_url"
            :cover-url="restricted ? null : profile.cover_url"
            :is-finisher="!restricted && stats.medals > 0"
            :stats="headerStats"
            :mutuals="mutuals"
            :follows-you="!isOwner && social.follows_you"
        >
            <template #actions>
                <template v-if="isOwner">
                    <Button
                        as-child
                        variant="secondary"
                        class="h-9 flex-1 rounded-full px-5 sm:flex-none"
                    >
                        <Link :href="editProfile()">Editar perfil</Link>
                    </Button>
                    <Button
                        variant="secondary"
                        class="h-9 flex-1 rounded-full px-5 sm:flex-none"
                        @click="share"
                    >
                        Compartir perfil
                    </Button>
                </template>
                <template v-else>
                    <FollowButton
                        class="flex-1 sm:flex-none"
                        :username="profile.username"
                        :name="profile.name"
                        :following="social.is_following"
                        :follows-you="social.follows_you ?? false"
                        :only="restricted ? [] : ['social']"
                    />
                    <Button
                        variant="secondary"
                        size="icon"
                        class="size-10 rounded-full"
                        aria-label="Compartir perfil"
                        @click="share"
                    >
                        <Share2 class="size-4" />
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-10 rounded-full"
                                aria-label="Más opciones"
                            >
                                <MoreHorizontal class="size-5" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-52">
                            <DropdownMenuItem @select="report">
                                <Flag class="mr-2 size-4" />
                                Reportar perfil
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="signedIn"
                                class="text-destructive focus:text-destructive"
                                @select="blockAthlete"
                            >
                                <Ban class="mr-2 size-4" />
                                Bloquear
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
            </template>
        </PublicProfileHeader>

        <!-- Followers-only, not following yet -->
        <section
            v-if="restricted"
            class="mt-10 border-t border-border px-6 pt-12 text-center"
        >
            <span
                class="mx-auto flex size-16 items-center justify-center rounded-full border-2 border-foreground"
            >
                <Lock class="size-7" />
            </span>
            <h2 class="mt-5 text-lg font-semibold">
                Esta cuenta es solo para sus seguidores
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Sigue a {{ profile.name }} para ver sus carreras, medallas y
                publicaciones.
            </p>
        </section>

        <template v-else>
            <!-- Tabs -->
            <div
                class="sticky top-16 z-30 mt-8 border-t border-border bg-background/90 backdrop-blur-sm"
                role="tablist"
                aria-label="Secciones del perfil"
            >
                <div class="flex justify-center gap-2 sm:gap-12">
                    <button
                        v-for="tab in tabs"
                        :id="`tab-${tab.key}`"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.key"
                        :aria-controls="`panel-${tab.key}`"
                        class="-mt-px inline-flex min-h-12 flex-1 items-center justify-center gap-1.5 border-t text-xs font-semibold tracking-[0.12em] uppercase transition-colors sm:flex-none"
                        :class="
                            activeTab === tab.key
                                ? 'border-foreground text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="activeTab = tab.key"
                    >
                        <component
                            :is="tab.icon"
                            class="size-4 sm:size-3.5"
                            aria-hidden="true"
                        />
                        <span class="hidden sm:inline">{{ tab.label }}</span>
                        <span class="sr-only sm:hidden">{{ tab.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Publicaciones -->
            <section
                v-show="activeTab === 'publicaciones'"
                id="panel-publicaciones"
                role="tabpanel"
                aria-labelledby="tab-publicaciones"
            >
                <InfiniteScroll
                    data="posts"
                    :buffer="600"
                    items-element="#profile-posts"
                >
                    <div id="profile-posts">
                        <ProfilePostGrid
                            v-if="visiblePosts.length"
                            :posts="visiblePosts"
                            @deleted="onDeleted"
                        />
                    </div>
                    <div
                        v-if="!visiblePosts.length"
                        class="flex flex-col items-center px-6 py-16 text-center"
                    >
                        <span
                            class="flex size-16 items-center justify-center rounded-full border-2 border-foreground"
                        >
                            <Camera class="size-7" />
                        </span>
                        <p class="mt-4 text-xl font-semibold">
                            {{
                                isOwner
                                    ? 'Comparte tu primer momento'
                                    : 'Aún no hay publicaciones'
                            }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{
                                isOwner
                                    ? 'Tus fotos y logros aparecerán en tu perfil.'
                                    : `Cuando ${profile.name} publique, lo verás aquí.`
                            }}
                        </p>
                        <Link
                            v-if="isOwner"
                            :href="communityIndex({ query: { publicar: 1 } })"
                            class="mt-4 text-sm font-semibold text-fl-gold-ink hover:text-foreground"
                            >Publicar ahora</Link
                        >
                    </div>
                    <template #loading>
                        <div
                            class="mt-0.5 grid grid-cols-3 gap-0.5 sm:mt-1 sm:gap-1"
                            aria-live="polite"
                        >
                            <span
                                v-for="n in 3"
                                :key="n"
                                class="aspect-square animate-pulse bg-muted"
                            />
                            <span class="sr-only">Cargando publicaciones…</span>
                        </div>
                    </template>
                </InfiniteScroll>
            </section>

            <!-- Legado -->
            <section
                v-show="activeTab === 'legado'"
                id="panel-legado"
                role="tabpanel"
                aria-labelledby="tab-legado"
                class="px-4 pt-6 sm:px-0"
            >
                <div
                    v-if="highlights.length"
                    class="grid gap-px overflow-hidden rounded-card border border-border bg-border sm:grid-cols-3"
                >
                    <component
                        :is="item.event_slug ? Link : 'div'"
                        v-for="(item, index) in highlights"
                        :key="`${item.event_slug}-${index}`"
                        :href="
                            item.event_slug
                                ? `/events/${item.event_slug}`
                                : undefined
                        "
                        class="flex flex-col bg-card p-5 transition-colors"
                        :class="item.event_slug ? 'hover:bg-fl-cream/40' : ''"
                    >
                        <p
                            class="truncate text-[11px] font-medium tracking-[0.14em] text-muted-foreground uppercase"
                        >
                            {{
                                [item.distance, shortDate(item.event_date)]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </p>
                        <p
                            class="mt-1 line-clamp-2 font-serif text-lg leading-tight"
                        >
                            {{ item.event }}
                        </p>
                        <p
                            class="legacy-numeric mt-4 font-serif text-4xl leading-none"
                        >
                            {{ item.official_time }}
                        </p>
                        <p
                            class="mt-2 inline-flex items-center gap-1 text-[11px] font-medium tracking-[0.14em] text-muted-foreground uppercase"
                        >
                            <Timer
                                class="size-3 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            Tiempo oficial
                        </p>
                    </component>
                </div>
                <div v-if="timeline.length" class="mt-8">
                    <LegacyTimelineList :entries="timeline" />
                </div>
                <p
                    v-else
                    class="py-14 text-center text-sm text-muted-foreground"
                >
                    {{ profile.name }} aún no tiene participaciones públicas.
                </p>
            </section>

            <!-- Medallas -->
            <section
                v-show="activeTab === 'medallas'"
                id="panel-medallas"
                role="tabpanel"
                aria-labelledby="tab-medallas"
                class="px-4 pt-6 sm:px-0"
            >
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <div
                        v-for="medal in medals"
                        :key="medal.id"
                        class="overflow-hidden rounded-card border border-border bg-card"
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
                            <p class="truncate text-xs text-muted-foreground">
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

            <!-- Fotos -->
            <section
                v-show="activeTab === 'fotos'"
                id="panel-fotos"
                role="tabpanel"
                aria-labelledby="tab-fotos"
            >
                <div class="grid grid-cols-3 gap-0.5 sm:gap-1">
                    <div
                        v-for="photo in photos"
                        :key="photo.uuid"
                        class="group/photo relative aspect-square overflow-hidden bg-muted"
                    >
                        <img
                            :src="photo.url"
                            :alt="
                                photo.event
                                    ? `${profile.name} en ${photo.event}`
                                    : `Foto de ${profile.name}`
                            "
                            loading="lazy"
                            decoding="async"
                            class="size-full object-cover"
                        />
                        <span
                            v-if="photo.event"
                            class="absolute inset-x-0 bottom-0 hidden truncate bg-gradient-to-t from-black/60 to-transparent px-2 pt-6 pb-1.5 text-[11px] font-medium text-white sm:block sm:opacity-0 sm:transition-opacity sm:group-hover/photo:opacity-100"
                            >{{ photo.event }}</span
                        >
                    </div>
                </div>
            </section>
        </template>
    </div>

    <CommunityReportDialog
        v-if="signedIn && !isOwner"
        :reasons="reportReasons"
    />
</template>
