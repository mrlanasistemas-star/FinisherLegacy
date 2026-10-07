<script setup lang="ts">
/**
 * Public Legacy Profile (/@username) — same editorial layout as Mi
 * Legado, showing only what this viewer may see (the controller already
 * filtered medals, posts and photos by their visibility).
 */
import { Head, Link } from '@inertiajs/vue3';
import { Award, PenLine, Share2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import FollowButton from '@/components/community/FollowButton.vue';
import PostCard from '@/components/community/PostCard.vue';
import LegacyTimelineList from '@/components/profile/LegacyTimelineList.vue';
import type { TimelineEntry } from '@/components/profile/LegacyTimelineList.vue';
import ProfileHeader from '@/components/profile/ProfileHeader.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { useCanonicalUrl } from '@/composables/useCanonicalUrl';
import { shortDate } from '@/lib/datetime';
import { edit as editProfile } from '@/routes/dashboard/profile';
import type {
    CommunityPost,
    PublicAthleteMedal,
    PublicAthleteProfile,
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
    profile: PublicAthleteProfile;
    stats: { medals: number; events: number };
    medals: PublicAthleteMedal[];
    social: { followers: number; following: number; is_following: boolean };
    events: PublicEvent[];
    posts: CommunityPost[];
    photos: { uuid: string; url: string; event: string | null }[];
}>();

const canonicalUrl = useCanonicalUrl();

type Tab = 'historia' | 'logros' | 'fotos' | 'publicaciones';
const tabs = computed(() =>
    (
        [
            { key: 'historia', label: 'Historia', count: props.events.length },
            { key: 'logros', label: 'Logros', count: props.medals.length },
            { key: 'fotos', label: 'Fotos', count: props.photos.length },
            {
                key: 'publicaciones',
                label: 'Publicaciones',
                count: props.posts.length,
            },
        ] as { key: Tab; label: string; count: number }[]
    ).filter((tab) => tab.key === 'historia' || tab.count > 0),
);
const activeTab = ref<Tab>('historia');

const location = computed(() =>
    [props.profile.city, props.profile.state, props.profile.country]
        .filter(Boolean)
        .join(', '),
);

const headerStats = computed(() => [
    { label: 'Eventos', value: props.stats.events },
    { label: 'Medallas', value: props.stats.medals },
    { label: 'Seguidores', value: props.social.followers },
    { label: 'Siguiendo', value: props.social.following },
]);

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

async function share() {
    const url = canonicalUrl ?? window.location.href;

    try {
        if (navigator.share) {
            await navigator.share({ title: props.profile.name, url });
        } else {
            await navigator.clipboard.writeText(url);
            toast.success('Enlace copiado.');
        }
    } catch {
        // Share sheet dismissed.
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

    if (props.profile.city || props.profile.country) {
        person.address = {
            '@type': 'PostalAddress',
            addressLocality: props.profile.city ?? undefined,
            addressCountry: props.profile.country ?? undefined,
        };
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
            :bio="profile.bio"
            :location="location"
            :sports="profile.sport ? [profile.sport] : []"
            :photo-url="profile.photo_url"
            :cover-url="profile.cover_url"
            :stats="headerStats"
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
                <template v-else>
                    <FollowButton
                        :username="profile.username"
                        :name="profile.name"
                        :following="social.is_following"
                        :only="['social']"
                    />
                </template>
                <Button
                    variant="ghost"
                    size="icon"
                    class="rounded-full"
                    aria-label="Compartir perfil"
                    @click="share"
                >
                    <Share2 class="size-4" />
                </Button>
            </template>
        </ProfileHeader>

        <div class="mt-10">
            <div
                class="flex gap-1 overflow-x-auto border-b border-border"
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
                    class="-mb-px shrink-0 border-b-2 px-4 py-3 text-sm font-medium transition-colors"
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
                    {{ profile.name }} aún no tiene participaciones públicas.
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

            <section
                v-show="activeTab === 'publicaciones'"
                id="panel-publicaciones"
                role="tabpanel"
                aria-labelledby="tab-publicaciones"
                class="max-w-2xl space-y-5 pt-8"
            >
                <PostCard v-for="post in posts" :key="post.uuid" :post="post" />
            </section>
        </div>
    </div>
</template>
