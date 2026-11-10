<script setup lang="ts">
/**
 * "Mi Finisher Legacy" — the athlete's hub. In order of what a person
 * comes here to do: activate a Finisher Legacy (NFC on Chrome/Android,
 * /activar elsewhere), see the ones already activated, jump to the usual
 * places, then the next race, the real totals, the race timeline and the
 * latest activity. Everything shown is the athlete's own data; every block
 * has an honest empty state instead of zeros.
 */
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarPlus,
    Camera,
    Check,
    Copy,
    ExternalLink,
    Images,
    Medal,
    MessageCirclePlus,
    Package,
    PenLine,
    Receipt,
    ShoppingBag,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ActivateLegacyCard from '@/components/account/ActivateLegacyCard.vue';
import LegacyStoryCard from '@/components/account/LegacyStoryCard.vue';
import type { LegacyStoryCardData } from '@/components/account/LegacyStoryCard.vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import PostPreview from '@/components/community/PostPreview.vue';
import LegacyTimeline from '@/components/legado/LegacyTimeline.vue';
import StatCounter from '@/components/legado/StatCounter.vue';
import UpcomingEventCard from '@/components/legado/UpcomingEventCard.vue';
import { vReveal } from '@/composables/useReveal';
import { useI18n } from '@/i18n';
import { index as communityIndex } from '@/routes/community';
import { myEvents, myGear } from '@/routes/dashboard';
import { show as legadoShow } from '@/routes/dashboard/legado';
import { create as createMedal } from '@/routes/dashboard/medals';
import { edit as editProfile } from '@/routes/dashboard/profile';
import { index as eventsIndex } from '@/routes/events';
import { index as myLegacyIndex } from '@/routes/my-legacy';
import { index as photosIndex } from '@/routes/photos';
import { index as ordersIndex } from '@/routes/store/orders';
import { index as storeIndex } from '@/routes/store/products';
import type { CommunityPost } from '@/types';
import type { LegadoEntry, LegadoTotals, UpcomingEntry } from '@/types/legado';

const props = defineProps<{
    legacies: LegacyStoryCardData[];
    legaciesTotal: number;
    legacyId: string | null;
    profile: {
        username: string;
        profile_visibility: string;
        completion: number;
    } | null;
    stats: {
        medals: number;
        events: number;
        plates: number;
        ownedProducts: number;
        media: number;
    };
    totals: LegadoTotals;
    legado: LegadoEntry[];
    legadoLimit: number;
    nextEvent: UpcomingEntry | null;
    athlete: {
        name: string;
        first_name: string;
        username: string | null;
        photo_url: string | null;
        cover_url: string | null;
        sport: string | null;
        city: string | null;
    };
    social: { followers: number; following: number; posts: number };
    recentPhotos: {
        uuid: string;
        url: string;
        is_public: boolean;
        event: string | null;
        participant_id: number;
    }[];
    recentPosts: CommunityPost[];
}>();

const { t, tc, regional } = useI18n();

const story = computed(() => props.legado.filter((entry) => !entry.upcoming));
const hasMore = computed(() => props.legado.length >= props.legadoLimit);

const greeting = computed(() => {
    const hour = new Date().getHours();
    const name = props.athlete.first_name;

    if (hour < 12) {
        return t('Buenos días, {name}', { name });
    }

    return hour < 19
        ? t('Buenas tardes, {name}', { name })
        : t('Buenas noches, {name}', { name });
});

const yearsHint = computed(() =>
    props.totals.first_year
        ? t('Desde {year}', { year: props.totals.first_year })
        : null,
);

/** The count with the thousands separator of the active language. */
const grouped = (n: number) => ({ count: n.toLocaleString(regional.value) });

// Only what the athlete actually has — no row of zeros.
const keepsakes = computed(() =>
    [
        {
            key: 'medallas',
            value: props.stats.medals,
            text: tc(
                '{count} medalla|{count} medallas',
                props.stats.medals,
                grouped(props.stats.medals),
            ),
        },
        {
            key: 'recuerdos',
            value: props.stats.media,
            text: tc(
                '{count} recuerdo|{count} recuerdos',
                props.stats.media,
                grouped(props.stats.media),
            ),
        },
        {
            key: 'publicaciones',
            value: props.social.posts,
            text: tc(
                '{count} publicación|{count} publicaciones',
                props.social.posts,
                grouped(props.social.posts),
            ),
        },
        {
            key: 'seguidores',
            value: props.social.followers,
            text: tc(
                '{count} seguidor|{count} seguidores',
                props.social.followers,
                grouped(props.social.followers),
            ),
        },
    ].filter((item) => item.value > 0),
);

const quickActions = computed(() => [
    {
        label: t('Mis eventos'),
        hint: t('Próximos y corridos'),
        href: myEvents().url,
        icon: CalendarPlus,
    },
    {
        label: t('Mis fotos'),
        hint: t('Búscalas por tu número'),
        href: photosIndex().url,
        icon: Images,
    },
    {
        label: t('Publicar'),
        hint: t('Comparte un momento'),
        href: communityIndex({ query: { publicar: 1 } }).url,
        icon: MessageCirclePlus,
    },
    {
        label: t('Mis pedidos'),
        hint: t('Sigue tus compras'),
        href: ordersIndex().url,
        icon: Receipt,
    },
    {
        label: t('Mi equipo'),
        hint:
            props.stats.ownedProducts > 0
                ? tc(
                      '{count} producto|{count} productos',
                      props.stats.ownedProducts,
                      grouped(props.stats.ownedProducts),
                  )
                : t('Tu equipo registrado'),
        href: myGear().url,
        icon: Package,
    },
    {
        label: t('Agregar medalla'),
        hint: t('De una carrera anterior'),
        href: createMedal().url,
        icon: Medal,
    },
]);

const hasActivity = computed(
    () => props.recentPhotos.length > 0 || props.recentPosts.length > 0,
);

const copied = ref(false);

async function copyLegacyId() {
    if (!props.legacyId) {
        return;
    }

    try {
        await navigator.clipboard.writeText(props.legacyId);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1800);
    } catch {
        // Clipboard blocked — the ID stays visible to copy by hand.
    }
}
</script>

<template>
    <Head :title="$t('Mi Finisher Legacy')" />

    <div class="fl-container py-6 md:py-10">
        <!-- Greeting -->
        <header
            v-reveal
            class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between"
        >
            <div class="flex min-w-0 items-center gap-4 sm:gap-5">
                <AthleteAvatar
                    :name="athlete.name"
                    :photo-url="athlete.photo_url"
                    size="lg"
                    class="shrink-0 ring-2 ring-fl-gold/50 ring-offset-2 ring-offset-background"
                />
                <div class="min-w-0">
                    <p class="fl-eyebrow truncate">
                        {{ greeting }}
                    </p>
                    <h1
                        class="mt-1.5 font-heading text-[1.85rem] leading-[1.1] font-semibold tracking-tight sm:text-4xl lg:text-5xl"
                    >
                        {{ $t('Mi Finisher Legacy') }}
                    </h1>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Link
                    :href="editProfile()"
                    class="fl-btn-secondary min-h-11 px-5 text-sm focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                >
                    <PenLine class="size-4" aria-hidden="true" />
                    {{ $t('Editar perfil') }}
                </Link>
                <Link
                    v-if="athlete.username"
                    :href="`/@${athlete.username}`"
                    class="inline-flex min-h-11 items-center gap-2 rounded-full px-4 text-sm font-semibold text-foreground/80 transition-colors hover:bg-white hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                >
                    <ExternalLink class="size-4" aria-hidden="true" />
                    {{ $t('Ver mi perfil público') }}
                </Link>
            </div>
        </header>

        <!-- Profile nudge -->
        <Link
            v-if="!profile || profile.completion < 100"
            v-reveal="{ delay: 60 }"
            :href="editProfile()"
            class="group mt-6 flex items-center gap-4 rounded-2xl border border-dashed border-fl-gold/50 bg-white/70 px-4 py-3 text-sm transition-colors hover:bg-white focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none sm:px-5"
        >
            <span
                class="relative size-10 shrink-0 rounded-full"
                :style="{
                    background: `conic-gradient(var(--fl-gold) ${(profile?.completion ?? 0) * 3.6}deg, var(--border) 0)`,
                }"
                aria-hidden="true"
            >
                <span
                    class="absolute inset-1 flex items-center justify-center rounded-full bg-white text-[10px] font-semibold text-fl-gold-ink"
                    >{{ profile?.completion ?? 0 }}%</span
                ></span
            >
            <span class="min-w-0 flex-1">
                <span class="block font-semibold text-foreground">{{
                    profile
                        ? $t('Tu perfil está al {percent}%', {
                              percent: profile.completion,
                          })
                        : $t('Crea tu perfil de atleta')
                }}</span>
                <span class="block text-muted-foreground">{{
                    $t('Agrega tu foto, portada y una frase que te represente.')
                }}</span>
            </span>
            <ArrowRight
                class="size-4 shrink-0 text-muted-foreground transition-transform duration-300 group-hover:translate-x-1"
                aria-hidden="true"
            />
        </Link>

        <!-- Activate -->
        <div v-reveal="{ delay: 100 }" class="mt-6 md:mt-8">
            <ActivateLegacyCard :has-any="legaciesTotal > 0" />
        </div>

        <!-- My Finisher Legacy -->
        <section class="mt-12" aria-labelledby="legacies-title">
            <div
                v-reveal
                class="flex flex-wrap items-end justify-between gap-3"
            >
                <div>
                    <p class="fl-eyebrow">{{ $t('Tus recuerdos') }}</p>
                    <h2
                        id="legacies-title"
                        class="mt-1.5 font-heading text-2xl font-semibold tracking-tight sm:text-3xl"
                    >
                        {{ $t('Mis Finisher Legacy') }}
                    </h2>
                </div>
                <Link
                    v-if="legaciesTotal > 0"
                    :href="myLegacyIndex()"
                    class="group inline-flex min-h-11 items-center gap-1 text-sm font-semibold"
                >
                    <span class="fl-link">{{
                        legaciesTotal > legacies.length
                            ? $t('Ver los {count}', { count: legaciesTotal })
                            : $t('Ver biblioteca')
                    }}</span>
                    <ArrowRight
                        class="size-4 transition-transform duration-300 group-hover:translate-x-0.5"
                        aria-hidden="true"
                    />
                </Link>
            </div>

            <ul
                v-if="legacies.length"
                class="mt-5 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-6"
            >
                <li
                    v-for="(item, i) in legacies"
                    :key="item.public_id"
                    v-reveal="{ index: i }"
                >
                    <LegacyStoryCard :story="item" />
                </li>
            </ul>
            <p
                v-else
                v-reveal
                class="mt-4 rounded-2xl border border-dashed border-foreground/15 bg-white px-5 py-6 text-sm leading-relaxed text-muted-foreground"
            >
                {{
                    $t(
                        'Aún no has activado ningún Finisher Legacy. Cuando lo hagas, cada historia — con sus fotos, tu tiempo y tu voz — vivirá aquí.',
                    )
                }}
            </p>
        </section>

        <!-- Quick actions -->
        <nav class="mt-12" aria-labelledby="quick-title">
            <h2 id="quick-title" class="sr-only">
                {{ $t('Accesos rápidos') }}
            </h2>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <li
                    v-for="(action, i) in quickActions"
                    :key="action.label"
                    v-reveal="{ index: i }"
                >
                    <Link
                        :href="action.href"
                        class="group fl-card fl-card-hover flex h-full min-h-[4.75rem] items-center gap-3 px-3.5 py-3 focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none sm:px-4"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink transition-colors duration-300 group-hover:bg-fl-gold group-hover:text-white"
                        >
                            <component
                                :is="action.icon"
                                class="size-[18px]"
                                aria-hidden="true"
                            />
                        </span>
                        <span class="min-w-0">
                            <span
                                class="block truncate text-sm font-semibold"
                                >{{ action.label }}</span
                            >
                            <span
                                class="block truncate text-xs text-muted-foreground"
                                >{{ action.hint }}</span
                            >
                        </span>
                    </Link>
                </li>
            </ul>
        </nav>

        <!-- Next race + totals -->
        <div class="mt-12 grid gap-5 lg:grid-cols-12">
            <div v-reveal class="lg:col-span-7">
                <p class="mb-3 text-sm font-semibold text-foreground">
                    {{ $t('Tu próxima meta') }}
                </p>
                <UpcomingEventCard
                    v-if="nextEvent"
                    :entry="nextEvent"
                    featured
                />
                <div
                    v-else
                    class="flex min-h-56 flex-col justify-between gap-6 rounded-[var(--fl-radius-media)] border border-border bg-[radial-gradient(120%_90%_at_90%_10%,#EFE6CF_0%,var(--fl-cream)_45%,#F6F3EC_100%)] p-6 sm:min-h-64 sm:p-7"
                >
                    <div>
                        <p
                            class="font-heading text-2xl leading-tight font-semibold sm:text-3xl"
                        >
                            {{ $t('¿Cuál es tu siguiente carrera?') }}
                        </p>
                        <p class="mt-2 max-w-md text-sm text-muted-foreground">
                            {{
                                $t(
                                    'Inscríbete a un evento y aquí contaremos los días que faltan para la salida.',
                                )
                            }}
                        </p>
                    </div>
                    <Link
                        :href="eventsIndex()"
                        class="fl-btn-primary w-fit text-sm focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        {{ $t('Explorar eventos') }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </div>

            <section
                v-reveal="{ delay: 80 }"
                class="fl-card flex flex-col justify-between p-6 sm:p-7 lg:col-span-5"
                aria-labelledby="totals-title"
            >
                <p
                    id="totals-title"
                    class="text-sm font-semibold text-foreground"
                >
                    {{ $t('Tu legado en números') }}
                </p>
                <p
                    v-if="!totals.races"
                    class="mt-6 font-heading text-xl leading-snug text-foreground"
                >
                    {{
                        $t(
                            'Tus carreras, tus kilómetros y tus temporadas se sumarán aquí desde tu primera meta.',
                        )
                    }}
                </p>
                <div v-else class="mt-6 grid grid-cols-3 gap-4">
                    <StatCounter
                        :value="totals.races"
                        :label="$tc('Carrera|Carreras', totals.races)"
                    />
                    <StatCounter
                        :value="totals.km"
                        :decimals="1"
                        :label="$t('Kilómetros')"
                    />
                    <StatCounter
                        :value="totals.years"
                        :label="$tc('Temporada|Temporadas', totals.years)"
                        :hint="yearsHint"
                    />
                </div>
                <div
                    v-if="keepsakes.length"
                    class="mt-6 flex flex-wrap gap-x-5 gap-y-1 border-t border-border pt-4 text-sm text-muted-foreground"
                >
                    <span v-for="item in keepsakes" :key="item.key">{{
                        item.text
                    }}</span>
                </div>
                <div
                    v-if="legacyId"
                    class="mt-5 flex items-center justify-between gap-3 rounded-xl bg-fl-paper px-4 py-2.5"
                >
                    <div class="min-w-0">
                        <p class="text-[11px] text-muted-foreground">
                            {{ $t('Tu Legacy ID') }}
                        </p>
                        <p class="truncate font-mono text-sm text-fl-gold-ink">
                            {{ legacyId }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="fl-press flex size-11 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-white hover:text-foreground focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                        :aria-label="
                            copied ? $t('Copiado') : $t('Copiar Legacy ID')
                        "
                        @click="copyLegacyId"
                    >
                        <Check
                            v-if="copied"
                            class="size-4 text-emerald-600"
                            aria-hidden="true"
                        />
                        <Copy v-else class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </section>
        </div>

        <div class="mt-14 grid gap-12 xl:grid-cols-12 xl:gap-10">
            <!-- Race timeline -->
            <section class="min-w-0 xl:col-span-8" aria-labelledby="story">
                <div
                    v-reveal
                    class="flex flex-wrap items-end justify-between gap-3"
                >
                    <div>
                        <p class="fl-eyebrow">{{ $t('Tu historia') }}</p>
                        <h2
                            id="story"
                            class="mt-1.5 font-heading text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            {{ $t('Cada carrera suma una página') }}
                        </h2>
                    </div>
                    <Link
                        v-if="story.length"
                        :href="myEvents()"
                        class="group inline-flex min-h-11 items-center gap-1 text-sm font-semibold"
                    >
                        <span class="fl-link">{{
                            hasMore
                                ? $t('Ver todas mis carreras')
                                : $t('Mis eventos')
                        }}</span>
                        <ArrowRight
                            class="size-4 transition-transform duration-300 group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </Link>
                </div>

                <div v-if="story.length" class="mt-8">
                    <LegacyTimeline :entries="story" />
                </div>

                <div
                    v-else
                    v-reveal
                    class="mt-6 rounded-[var(--fl-radius-media)] border border-border bg-white p-6 sm:p-8"
                >
                    <p
                        class="font-heading text-2xl leading-tight font-semibold"
                    >
                        {{ $t('Tu primera página está por escribirse.') }}
                    </p>
                    <p
                        class="mt-3 max-w-lg text-sm leading-relaxed text-muted-foreground"
                    >
                        {{
                            $t(
                                'Cuando cruces la meta de un evento Finisher Legacy, tu resultado, tus fotos y tu medalla aparecerán aquí.',
                            )
                        }}
                    </p>
                    <div class="mt-6 flex flex-col gap-2 sm:flex-row">
                        <Link
                            :href="eventsIndex()"
                            class="fl-btn-primary text-sm focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none"
                            >{{ $t('Explorar eventos') }}</Link
                        >
                        <Link
                            :href="storeIndex()"
                            class="fl-btn-secondary text-sm focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                        >
                            <ShoppingBag class="size-4" aria-hidden="true" />
                            {{ $t('Ver la tienda') }}
                        </Link>
                    </div>
                    <Link
                        :href="createMedal()"
                        class="mt-4 inline-flex min-h-11 items-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    >
                        {{
                            $t('¿Ya tienes medallas? Agrégalas a tu historia.')
                        }}
                    </Link>
                </div>
            </section>

            <!-- Recent activity -->
            <aside
                class="min-w-0 xl:col-span-4"
                aria-labelledby="moments-title"
            >
                <div v-reveal class="flex items-end justify-between gap-3">
                    <h2
                        id="moments-title"
                        class="font-heading text-xl font-semibold"
                    >
                        {{ $t('Actividad reciente') }}
                    </h2>
                    <Link
                        :href="photosIndex()"
                        class="fl-link inline-flex min-h-11 items-center text-sm font-semibold"
                        >{{ $t('Buscar mis fotos') }}</Link
                    >
                </div>

                <div
                    v-if="recentPhotos.length"
                    class="mt-4 grid grid-cols-4 gap-2"
                >
                    <Link
                        v-for="(photo, index) in recentPhotos"
                        :key="photo.uuid"
                        v-reveal="{ index }"
                        :href="legadoShow(photo.participant_id)"
                        class="fl-zoom relative aspect-square overflow-hidden rounded-xl bg-muted focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                    >
                        <img
                            :src="photo.url"
                            :alt="
                                photo.event
                                    ? $t('Foto en {event}', {
                                          event: photo.event,
                                      })
                                    : $t('Foto de evento')
                            "
                            loading="lazy"
                            class="size-full object-cover"
                        />
                        <span
                            v-if="!photo.is_public"
                            class="absolute top-1 right-1 rounded-full bg-white/90 px-1.5 py-0.5 text-[9px] font-medium"
                            >{{ $t('Privada') }}</span
                        >
                    </Link>
                </div>

                <div v-if="recentPosts.length" class="mt-5 space-y-3">
                    <div
                        v-for="(post, i) in recentPosts"
                        :key="post.uuid"
                        v-reveal="{ index: i }"
                        class="fl-card-hover rounded-[var(--fl-radius-card)]"
                    >
                        <PostPreview :post="post" />
                    </div>
                </div>

                <div
                    v-if="!hasActivity"
                    v-reveal
                    class="mt-4 rounded-2xl border border-dashed border-foreground/15 bg-white p-6 text-center"
                >
                    <span
                        class="mx-auto flex size-12 items-center justify-center rounded-full bg-fl-cream"
                    >
                        <Camera
                            class="size-5 text-fl-gold-ink"
                            aria-hidden="true"
                        />
                    </span>
                    <p class="mt-3 text-sm font-semibold">
                        {{ $t('Aún no hay actividad') }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            $t(
                                'Tus fotos de evento y lo que publiques en la comunidad aparecerán aquí.',
                            )
                        }}
                    </p>
                </div>

                <Link
                    :href="communityIndex({ query: { publicar: 1 } })"
                    class="fl-btn-secondary mt-4 w-full text-sm focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                >
                    <MessageCirclePlus class="size-4" aria-hidden="true" />
                    {{ $t('Compartir un momento') }}
                </Link>
            </aside>
        </div>
    </div>
</template>
