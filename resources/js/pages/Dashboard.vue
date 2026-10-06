<script setup lang="ts">
/**
 * "Mi Legado" — the heart of the athlete profile. Same data as before
 * (GetAthleteLegado participations, stats, Legacy ID) plus the profile
 * header, medals, photos and own posts, laid out as an editorial profile.
 * Every entry still opens the existing per-participation detail
 * (/dashboard/legado/{participant}).
 */
import { Head, Link } from '@inertiajs/vue3';
import {
    Award,
    Camera,
    Check,
    Copy,
    ExternalLink,
    Nfc,
    Package,
    PenLine,
    Plus,
    Receipt,
    Share2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import PostPreview from '@/components/community/PostPreview.vue';
import LegacyTimelineList from '@/components/profile/LegacyTimelineList.vue';
import type { TimelineEntry } from '@/components/profile/LegacyTimelineList.vue';
import ProfileHeader from '@/components/profile/ProfileHeader.vue';
import LinkPlateDialog from '@/components/shared/LinkPlateDialog.vue';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { shortDate } from '@/lib/datetime';
import { dashboard } from '@/routes';
import { index as communityIndex } from '@/routes/community';
import { show as legadoShow } from '@/routes/dashboard/legado';
import {
    create as createMedal,
    index as medalsIndex,
} from '@/routes/dashboard/medals';
import { edit as editProfile } from '@/routes/dashboard/profile';
import { index as eventsIndex } from '@/routes/events';
import { index as photosIndex } from '@/routes/photos';
import type {
    CommunityPost,
    DashboardProfileSummary,
    DashboardStats,
    MedalCard,
} from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mi Legado', href: dashboard() }],
    },
});

type LegadoEntry = {
    id: number;
    event: string | null;
    edition: string | null;
    race: string | null;
    bib_number: string | null;
    event_date: string | null;
    official_time: string | null;
    pace: string | null;
    position: number | null;
    image_url: string | null;
    has_medal: boolean;
    has_legacy_plate: boolean;
    legacy_plate_status: string | null;
    photo_count: number;
    video_count: number;
};

const props = defineProps<{
    legacyId: string | null;
    profile: DashboardProfileSummary | null;
    stats: DashboardStats;
    legado: LegadoEntry[];
    athlete: {
        name: string;
        username: string | null;
        bio: string | null;
        city: string | null;
        state: string | null;
        country: string | null;
        sport: string | null;
        photo_url: string | null;
        cover_url: string | null;
    };
    social: { followers: number; following: number; posts: number };
    medals: MedalCard[];
    recentPhotos: {
        uuid: string;
        url: string;
        is_public: boolean;
        event: string | null;
        participant_id: number;
    }[];
    recentPosts: CommunityPost[];
}>();

type Tab = 'historia' | 'logros' | 'fotos' | 'eventos';
const tabs: { key: Tab; label: string }[] = [
    { key: 'historia', label: 'Historia' },
    { key: 'logros', label: 'Logros' },
    { key: 'fotos', label: 'Fotos' },
    { key: 'eventos', label: 'Eventos' },
];
const activeTab = ref<Tab>('historia');

const location = computed(() =>
    [props.athlete.city, props.athlete.state, props.athlete.country]
        .filter(Boolean)
        .join(', '),
);

const headerStats = computed(() => [
    { label: 'Eventos', value: props.stats.events },
    { label: 'Medallas', value: props.stats.medals },
    { label: 'Placas', value: props.stats.plates },
    { label: 'Seguidores', value: props.social.followers },
    { label: 'Siguiendo', value: props.social.following },
    { label: 'Fotos', value: props.stats.media },
]);

const sortedLegado = computed(() =>
    [...props.legado].sort((a, b) =>
        (b.event_date ?? '').localeCompare(a.event_date ?? ''),
    ),
);

const timeline = computed<TimelineEntry[]>(() =>
    sortedLegado.value.map((entry) => ({
        key: entry.id,
        date: entry.event_date,
        title: entry.event ?? 'Evento',
        subtitle: entry.race,
        time: entry.official_time,
        pace: entry.pace,
        position: entry.position,
        imageUrl: entry.image_url,
        href: legadoShow(entry.id).url,
        hasLegacyPlate: entry.has_legacy_plate,
        photoCount: entry.photo_count,
    })),
);

const copied = ref(false);

async function copyLegacyId() {
    if (!props.legacyId) {
        return;
    }

    await navigator.clipboard.writeText(props.legacyId);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1800);
}

async function shareProfile() {
    if (!props.athlete.username) {
        return;
    }

    const url = `${window.location.origin}/@${props.athlete.username}`;

    try {
        if (navigator.share) {
            await navigator.share({ title: props.athlete.name, url });
        } else {
            await navigator.clipboard.writeText(url);
            toast.success('Enlace de tu perfil copiado.');
        }
    } catch {
        // Share sheet dismissed.
    }
}

const linkDialogOpen = ref(false);
</script>

<template>
    <Head title="Mi Legado" />

    <div class="mx-auto w-full max-w-6xl px-4 py-6 md:px-8 md:py-8">
        <ProfileHeader
            :name="athlete.name"
            :username="athlete.username"
            :bio="athlete.bio"
            :location="location"
            :sports="athlete.sport ? [athlete.sport] : []"
            :photo-url="athlete.photo_url"
            :cover-url="athlete.cover_url"
            :stats="headerStats"
        >
            <template #actions>
                <Button as-child variant="outline" class="rounded-full">
                    <Link :href="editProfile()">
                        <PenLine class="size-4" />
                        Editar perfil
                    </Link>
                </Button>
                <Button
                    v-if="athlete.username"
                    as-child
                    variant="outline"
                    class="rounded-full"
                >
                    <Link :href="`/@${athlete.username}`">
                        <ExternalLink class="size-4" />
                        Ver perfil público
                    </Link>
                </Button>
                <Button
                    v-if="athlete.username"
                    variant="ghost"
                    size="icon"
                    class="rounded-full"
                    aria-label="Compartir mi perfil"
                    @click="shareProfile"
                >
                    <Share2 class="size-4" />
                </Button>
            </template>
        </ProfileHeader>

        <!-- Profile setup prompts -->
        <div
            v-if="!profile"
            class="mt-8 flex flex-col gap-4 rounded-xl border border-dashed border-fl-gold/50 bg-fl-cream/60 p-6 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="font-semibold">Crea tu perfil de atleta</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Elige tu username, tu deporte y tu foto. Decide si tu perfil
                    es público o privado.
                </p>
            </div>
            <Button as-child class="rounded-full">
                <Link :href="editProfile()">Completar mi perfil</Link>
            </Button>
        </div>
        <div
            v-else-if="profile.completion < 100"
            class="mt-8 rounded-xl border border-border bg-card p-5"
        >
            <div class="flex items-center justify-between gap-4">
                <p class="text-sm text-muted-foreground">
                    Tu perfil está
                    <span class="font-semibold text-foreground"
                        >{{ profile.completion }}% completo</span
                    >. Agrega foto, portada y una frase que te represente.
                </p>
                <Link
                    :href="editProfile()"
                    class="shrink-0 text-sm font-semibold underline-offset-4 hover:underline"
                >
                    Completar
                </Link>
            </div>
            <Progress :model-value="profile.completion" class="mt-3" />
        </div>

        <div class="mt-10 grid gap-10 lg:grid-cols-12">
            <!-- Main column -->
            <div class="min-w-0 lg:col-span-8">
                <div
                    class="flex gap-1 overflow-x-auto border-b border-border"
                    role="tablist"
                    aria-label="Secciones de Mi Legado"
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
                    </button>
                </div>

                <!-- Historia -->
                <section
                    v-show="activeTab === 'historia'"
                    id="panel-historia"
                    role="tabpanel"
                    aria-labelledby="tab-historia"
                    class="pt-8"
                >
                    <h2 class="fl-display text-3xl sm:text-4xl">
                        Cada logro cuenta.
                    </h2>
                    <p class="mt-3 max-w-xl text-muted-foreground">
                        Aquí se construye mi historia. Un registro de los
                        momentos que me definen dentro y fuera del deporte.
                    </p>

                    <div v-if="timeline.length" class="mt-8">
                        <LegacyTimelineList :entries="timeline" />
                    </div>
                    <div
                        v-else
                        class="mt-8 rounded-xl border border-dashed border-foreground/15 bg-card p-8 text-center"
                    >
                        <p class="font-serif text-2xl">
                            Tu legado comienza con una meta.
                        </p>
                        <p
                            class="mx-auto mt-2 max-w-md text-sm text-muted-foreground"
                        >
                            Cuando participes en un evento de Finisher Legacy tu
                            resultado aparecerá aquí. También puedes registrar
                            una medalla que ya tengas.
                        </p>
                        <div
                            class="mt-6 flex flex-col justify-center gap-2 sm:flex-row"
                        >
                            <Button as-child class="rounded-full">
                                <Link :href="createMedal()">
                                    <Plus class="size-4" />
                                    Agregar una medalla
                                </Link>
                            </Button>
                            <Button
                                as-child
                                variant="outline"
                                class="rounded-full"
                            >
                                <Link :href="eventsIndex()"
                                    >Explorar eventos</Link
                                >
                            </Button>
                        </div>
                    </div>
                </section>

                <!-- Logros -->
                <section
                    v-show="activeTab === 'logros'"
                    id="panel-logros"
                    role="tabpanel"
                    aria-labelledby="tab-logros"
                    class="pt-8"
                >
                    <div class="flex items-end justify-between gap-4">
                        <h2 class="fl-display text-3xl">Mis medallas</h2>
                        <Link
                            :href="medalsIndex()"
                            class="text-sm font-semibold underline-offset-4 hover:underline"
                            >Ver todas</Link
                        >
                    </div>
                    <div
                        v-if="medals.length"
                        class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3"
                    >
                        <Link
                            v-for="medal in medals"
                            :key="medal.id"
                            :href="`/dashboard/medals/${medal.id}`"
                            class="group fl-card overflow-hidden transition-colors hover:border-foreground/20"
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
                        </Link>
                    </div>
                    <p v-else class="mt-6 text-muted-foreground">
                        Aún no registras medallas.
                    </p>
                    <Button
                        as-child
                        variant="outline"
                        class="mt-6 rounded-full"
                    >
                        <Link :href="createMedal()">
                            <Plus class="size-4" />
                            Agregar medalla
                        </Link>
                    </Button>
                </section>

                <!-- Fotos -->
                <section
                    v-show="activeTab === 'fotos'"
                    id="panel-fotos"
                    role="tabpanel"
                    aria-labelledby="tab-fotos"
                    class="pt-8"
                >
                    <div class="flex items-end justify-between gap-4">
                        <h2 class="fl-display text-3xl">Mis fotos</h2>
                        <Link
                            :href="photosIndex()"
                            class="text-sm font-semibold underline-offset-4 hover:underline"
                            >Buscar mis fotos</Link
                        >
                    </div>
                    <div
                        v-if="recentPhotos.length"
                        class="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-3"
                    >
                        <Link
                            v-for="photo in recentPhotos"
                            :key="photo.uuid"
                            :href="legadoShow(photo.participant_id).url"
                            class="group relative aspect-square overflow-hidden rounded-lg bg-muted"
                        >
                            <img
                                :src="photo.url"
                                :alt="
                                    photo.event
                                        ? `Foto en ${photo.event}`
                                        : 'Foto de evento'
                                "
                                loading="lazy"
                                class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.04]"
                            />
                            <span
                                v-if="!photo.is_public"
                                class="absolute top-2 right-2 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-medium"
                                >Privada</span
                            >
                        </Link>
                    </div>
                    <div
                        v-else
                        class="mt-6 rounded-xl border border-dashed border-foreground/15 bg-card p-8 text-center"
                    >
                        <Camera class="mx-auto size-6 text-fl-gold-ink" />
                        <p class="mt-3 text-sm text-muted-foreground">
                            Sube fotos desde el detalle de cada evento en tu
                            historia.
                        </p>
                    </div>
                </section>

                <!-- Eventos -->
                <section
                    v-show="activeTab === 'eventos'"
                    id="panel-eventos"
                    role="tabpanel"
                    aria-labelledby="tab-eventos"
                    class="pt-8"
                >
                    <h2 class="fl-display text-3xl">Mis eventos</h2>
                    <ul
                        v-if="sortedLegado.length"
                        class="mt-6 divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
                    >
                        <li v-for="entry in sortedLegado" :key="entry.id">
                            <Link
                                :href="legadoShow(entry.id)"
                                class="flex items-center justify-between gap-4 px-4 py-4 transition-colors hover:bg-muted/50 sm:px-5"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{
                                        entry.event
                                    }}</span>
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{
                                            [
                                                entry.race,
                                                shortDate(entry.event_date),
                                                entry.bib_number
                                                    ? `#${entry.bib_number}`
                                                    : null,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')
                                        }}</span
                                    >
                                </span>
                                <span
                                    v-if="entry.official_time"
                                    class="legacy-numeric shrink-0 font-semibold"
                                    >{{ entry.official_time }}</span
                                >
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="mt-6 text-muted-foreground">
                        Todavía no tienes participaciones registradas.
                    </p>
                    <Button
                        as-child
                        variant="outline"
                        class="mt-6 rounded-full"
                    >
                        <Link :href="eventsIndex()">Explorar eventos</Link>
                    </Button>
                </section>
            </div>

            <!-- Side column -->
            <aside class="space-y-6 lg:col-span-4">
                <!-- NFC -->
                <div
                    class="overflow-hidden rounded-xl bg-foreground p-6 text-background"
                >
                    <Nfc class="size-6 text-fl-gold" />
                    <h2 class="mt-4 font-serif text-2xl leading-tight">
                        Vincula tu placa NFC
                    </h2>
                    <p class="mt-2 text-sm leading-relaxed text-white/70">
                        Conecta tu placa física con tu perfil y mantén vivo tu
                        legado.
                    </p>
                    <Button
                        class="mt-5 w-full rounded-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        @click="linkDialogOpen = true"
                    >
                        Vincular mi placa
                    </Button>
                    <div
                        v-if="legacyId"
                        class="mt-5 flex items-center justify-between gap-3 border-t border-white/15 pt-4"
                    >
                        <div>
                            <p
                                class="text-[10px] tracking-[0.18em] text-white/50 uppercase"
                            >
                                Legacy ID
                            </p>
                            <p class="font-mono text-sm text-fl-gold">
                                {{ legacyId }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="flex size-8 items-center justify-center rounded-full text-white/70 hover:bg-white/10 hover:text-white"
                            aria-label="Copiar Legacy ID"
                            @click="copyLegacyId"
                        >
                            <Check v-if="copied" class="size-4" />
                            <Copy v-else class="size-4" />
                        </button>
                    </div>
                </div>

                <!-- Community -->
                <div class="fl-card p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="font-semibold">Mis publicaciones</h2>
                        <span class="text-xs text-muted-foreground"
                            >{{ social.posts }} en total</span
                        >
                    </div>
                    <div v-if="recentPosts.length" class="mt-4 space-y-3">
                        <PostPreview
                            v-for="post in recentPosts"
                            :key="post.uuid"
                            :post="post"
                        />
                    </div>
                    <p v-else class="mt-3 text-sm text-muted-foreground">
                        Comparte tu próxima carrera o marca personal con la
                        comunidad.
                    </p>
                    <Button
                        as-child
                        variant="outline"
                        class="mt-4 w-full rounded-full"
                    >
                        <Link :href="communityIndex()">Publicar un logro</Link>
                    </Button>
                </div>

                <!-- Quick links -->
                <nav
                    class="fl-card divide-y divide-border"
                    aria-label="Accesos"
                >
                    <Link
                        href="/dashboard/my-gear"
                        class="flex items-center gap-3 px-5 py-3.5 text-sm hover:bg-muted/50"
                    >
                        <Package class="size-4 text-fl-gold-ink" />
                        Mi equipo
                        <span class="ml-auto text-xs text-muted-foreground">{{
                            stats.ownedProducts
                        }}</span>
                    </Link>
                    <Link
                        href="/mis-pedidos"
                        class="flex items-center gap-3 px-5 py-3.5 text-sm hover:bg-muted/50"
                    >
                        <Receipt class="size-4 text-fl-gold-ink" />
                        Mis pedidos
                    </Link>
                    <Link
                        :href="medalsIndex()"
                        class="flex items-center gap-3 px-5 py-3.5 text-sm hover:bg-muted/50"
                    >
                        <Award class="size-4 text-fl-gold-ink" />
                        Mis medallas
                        <span class="ml-auto text-xs text-muted-foreground">{{
                            stats.medals
                        }}</span>
                    </Link>
                </nav>
            </aside>
        </div>
    </div>

    <LinkPlateDialog v-model:open="linkDialogOpen" />
</template>
