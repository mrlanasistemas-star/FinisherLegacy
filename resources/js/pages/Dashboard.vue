<script setup lang="ts">
/**
 * "Mi legado" — the athlete's home inside Mi espacio (spec §7/§134).
 * Greeting, the next race, the running totals and the timeline of every
 * race run ("Cada carrera suma una página."), then the latest moments.
 * Every card opens the event story at /dashboard/legado/{participant}.
 */
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarPlus,
    Camera,
    Check,
    Copy,
    ExternalLink,
    Medal,
    Nfc,
    Package,
    PenLine,
    Receipt,
    ShoppingBag,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AthleteAvatar from '@/components/community/AthleteAvatar.vue';
import PostPreview from '@/components/community/PostPreview.vue';
import LegacyTimeline from '@/components/legado/LegacyTimeline.vue';
import StatCounter from '@/components/legado/StatCounter.vue';
import UpcomingEventCard from '@/components/legado/UpcomingEventCard.vue';
import Reveal from '@/components/motion/Reveal.vue';
import LinkPlateDialog from '@/components/shared/LinkPlateDialog.vue';
import { Button } from '@/components/ui/button';
import { MEDIA } from '@/config/media';
import { dashboard } from '@/routes';
import { index as communityIndex } from '@/routes/community';
import { myEvents, myGear } from '@/routes/dashboard';
import { show as legadoShow } from '@/routes/dashboard/legado';
import { create as createMedal } from '@/routes/dashboard/medals';
import { edit as editProfile } from '@/routes/dashboard/profile';
import { index as eventsIndex } from '@/routes/events';
import { index as photosIndex } from '@/routes/photos';
import { index as ordersIndex } from '@/routes/store/orders';
import { index as storeIndex } from '@/routes/store/products';
import type { CommunityPost } from '@/types';
import type { LegadoEntry, LegadoTotals, UpcomingEntry } from '@/types/legado';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mi legado', href: dashboard() }],
    },
});

const props = defineProps<{
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

const story = computed(() => props.legado.filter((entry) => !entry.upcoming));
const hasMore = computed(() => props.legado.length >= props.legadoLimit);

const greeting = computed(() => {
    const hour = new Date().getHours();

    if (hour < 12) {
        return 'Buenos días';
    }

    return hour < 19 ? 'Buenas tardes' : 'Buenas noches';
});

const yearsHint = computed(() =>
    props.totals.first_year ? `Desde ${props.totals.first_year}` : null,
);

// Only what the athlete actually has — no row of zeros.
const keepsakes = computed(() => {
    const plates = Math.max(
        props.stats.plates,
        props.legado.filter((entry) => entry.has_legacy_plate).length,
    );

    return [
        {
            value: props.stats.medals,
            label: props.stats.medals === 1 ? 'medalla' : 'medallas',
        },
        {
            value: plates,
            label: plates === 1 ? 'Legacy Plate' : 'Legacy Plates',
        },
        {
            value: props.stats.media,
            label: props.stats.media === 1 ? 'recuerdo' : 'recuerdos',
        },
    ].filter((item) => item.value > 0);
});

const quickActions = computed(() => [
    {
        label: 'Mis eventos',
        hint: 'Próximos y corridos',
        href: myEvents().url,
        icon: CalendarPlus,
    },
    {
        label: 'Mi equipo',
        hint: `${props.stats.ownedProducts} ${props.stats.ownedProducts === 1 ? 'producto' : 'productos'}`,
        href: myGear().url,
        icon: Package,
    },
    {
        label: 'Mis pedidos',
        hint: 'Sigue tus compras',
        href: ordersIndex().url,
        icon: Receipt,
    },
    {
        label: 'Agregar medalla',
        hint: 'De una carrera anterior',
        href: createMedal().url,
        icon: Medal,
    },
]);

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

const linkDialogOpen = ref(false);
</script>

<template>
    <Head title="Mi legado" />

    <div class="fl-container py-6 md:py-10">
        <!-- Greeting -->
        <header
            class="flex flex-col gap-6 md:flex-row md:items-end md:justify-between"
        >
            <div class="flex items-center gap-4 sm:gap-5">
                <AthleteAvatar
                    :name="athlete.name"
                    :photo-url="athlete.photo_url"
                    size="lg"
                    class="ring-2 ring-fl-gold/40 ring-offset-2 ring-offset-background"
                />
                <div class="min-w-0">
                    <p class="fl-eyebrow">
                        {{ greeting }}, {{ athlete.first_name }}
                    </p>
                    <h1
                        class="fl-display mt-2 text-[2rem] sm:text-5xl 2xl:text-6xl"
                    >
                        Cada carrera suma una página.
                    </h1>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Button as-child variant="outline" class="rounded-full">
                    <Link :href="editProfile()">
                        <PenLine class="size-4" />
                        Editar perfil
                    </Link>
                </Button>
                <Button
                    v-if="athlete.username"
                    as-child
                    variant="ghost"
                    class="rounded-full"
                >
                    <Link :href="`/@${athlete.username}`">
                        <ExternalLink class="size-4" />
                        Ver mi perfil público
                    </Link>
                </Button>
            </div>
        </header>

        <!-- Profile nudge -->
        <Link
            v-if="!profile || profile.completion < 100"
            :href="editProfile()"
            class="group mt-6 flex items-center gap-4 rounded-xl border border-dashed border-fl-gold/50 bg-fl-cream/60 px-4 py-3 text-sm transition-colors hover:bg-fl-cream sm:px-5"
        >
            <span
                class="relative size-9 shrink-0 rounded-full"
                :style="{
                    background: `conic-gradient(var(--fl-gold) ${(profile?.completion ?? 0) * 3.6}deg, var(--border) 0)`,
                }"
                aria-hidden="true"
            >
                <span class="absolute inset-1 rounded-full bg-fl-cream"
            /></span>
            <span class="min-w-0 flex-1">
                <span class="font-semibold text-foreground">{{
                    profile
                        ? `Tu perfil está al ${profile.completion}%`
                        : 'Crea tu perfil de atleta'
                }}</span>
                <span class="text-muted-foreground">
                    — agrega tu foto, portada y una frase que te
                    represente.</span
                >
            </span>
            <ArrowRight
                class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
            />
        </Link>

        <!-- Next race + totals -->
        <div class="mt-8 grid gap-5 lg:grid-cols-12">
            <div class="lg:col-span-7">
                <p class="mb-3 text-sm font-semibold text-foreground">
                    Tu próxima meta
                </p>
                <UpcomingEventCard
                    v-if="nextEvent"
                    :entry="nextEvent"
                    featured
                />
                <div
                    v-else
                    class="flex min-h-56 flex-col justify-between gap-6 rounded-2xl border border-border bg-[radial-gradient(120%_90%_at_90%_10%,#EFE6CF_0%,var(--fl-cream)_45%,#F6F3EC_100%)] p-6 sm:min-h-64 sm:p-7"
                >
                    <div>
                        <p
                            class="font-serif text-3xl leading-tight sm:text-4xl"
                        >
                            ¿Cuál es tu siguiente carrera?
                        </p>
                        <p class="mt-2 max-w-md text-sm text-muted-foreground">
                            Inscríbete a un evento y aquí contaremos los días
                            que faltan para la salida.
                        </p>
                    </div>
                    <Button as-child class="w-fit rounded-full">
                        <Link :href="eventsIndex()">
                            Explorar eventos
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </div>
            </div>

            <section
                class="fl-card flex flex-col justify-between p-6 sm:p-7 lg:col-span-5"
                aria-labelledby="totals-title"
            >
                <p
                    id="totals-title"
                    class="text-sm font-semibold text-foreground"
                >
                    Tu legado en números
                </p>
                <p
                    v-if="!totals.races"
                    class="mt-6 font-serif text-2xl leading-snug text-foreground"
                >
                    Tus carreras, tus kilómetros y tus temporadas se sumarán
                    aquí desde tu primera meta.
                </p>
                <div v-else class="mt-6 grid grid-cols-3 gap-4">
                    <StatCounter
                        :value="totals.races"
                        :label="totals.races === 1 ? 'Carrera' : 'Carreras'"
                    />
                    <StatCounter
                        :value="totals.km"
                        :decimals="1"
                        label="Kilómetros"
                    />
                    <StatCounter
                        :value="totals.years"
                        :label="totals.years === 1 ? 'Temporada' : 'Temporadas'"
                        :hint="yearsHint"
                    />
                </div>
                <div
                    v-if="keepsakes.length"
                    class="mt-6 flex flex-wrap gap-x-5 gap-y-1 border-t border-border pt-4 text-sm text-muted-foreground"
                >
                    <span v-for="item in keepsakes" :key="item.label"
                        ><strong class="font-semibold text-foreground">{{
                            item.value
                        }}</strong>
                        {{ item.label }}</span
                    >
                </div>
            </section>
        </div>

        <!-- Quick actions -->
        <nav
            class="mt-5 grid grid-cols-2 gap-3 xl:grid-cols-4"
            aria-label="Accesos rápidos"
        >
            <Link
                v-for="action in quickActions"
                :key="action.label"
                :href="action.href"
                class="group fl-card flex min-h-[4.5rem] items-center gap-3 px-4 py-3 transition-colors hover:border-foreground/20 focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                >
                    <component :is="action.icon" class="size-[18px]" />
                </span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-semibold">{{
                        action.label
                    }}</span>
                    <span
                        class="block truncate text-xs text-muted-foreground"
                        >{{ action.hint }}</span
                    >
                </span>
            </Link>
        </nav>

        <div class="mt-14 grid gap-12 xl:grid-cols-12 xl:gap-10">
            <!-- The story -->
            <section class="min-w-0 xl:col-span-8" aria-labelledby="story">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="fl-eyebrow">Tu historia</p>
                        <h2
                            id="story"
                            class="fl-display mt-2 text-3xl sm:text-4xl"
                        >
                            Mi legado
                        </h2>
                    </div>
                    <Link
                        v-if="story.length"
                        :href="myEvents()"
                        class="inline-flex min-h-11 items-center gap-1 text-sm font-semibold underline-offset-4 hover:underline"
                    >
                        {{ hasMore ? 'Ver todas mis carreras' : 'Mis eventos' }}
                        <ArrowRight class="size-4" />
                    </Link>
                </div>

                <div v-if="story.length" class="mt-8">
                    <LegacyTimeline :entries="story" />
                </div>

                <!-- Empty: guide to Eventos / Tienda -->
                <div
                    v-else
                    class="relative mt-8 overflow-hidden rounded-2xl border border-border bg-card"
                >
                    <div class="grid items-center gap-0 xl:grid-cols-5">
                        <div class="p-6 sm:p-8 xl:col-span-3">
                            <p
                                class="font-serif text-3xl leading-tight sm:text-4xl"
                            >
                                Tu primera página está por escribirse.
                            </p>
                            <p
                                class="mt-3 max-w-md text-sm leading-relaxed text-muted-foreground"
                            >
                                Cuando cruces la meta de un evento Finisher
                                Legacy, tu resultado, tus fotos y tu medalla
                                aparecerán aquí. Empieza eligiendo tu próxima
                                carrera o la pieza que la va a recordar.
                            </p>
                            <div
                                class="mt-6 flex flex-col gap-2 sm:flex-row sm:flex-wrap"
                            >
                                <Button as-child class="rounded-full">
                                    <Link :href="eventsIndex()">
                                        Explorar eventos
                                    </Link>
                                </Button>
                                <Button
                                    as-child
                                    variant="outline"
                                    class="rounded-full"
                                >
                                    <Link :href="storeIndex()">
                                        <ShoppingBag class="size-4" />
                                        Ver la tienda
                                    </Link>
                                </Button>
                            </div>
                            <Link
                                :href="createMedal()"
                                class="mt-4 inline-flex min-h-11 items-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                            >
                                ¿Ya tienes medallas? Agrégalas a tu historia.
                            </Link>
                        </div>
                        <div
                            class="relative hidden h-full min-h-64 bg-fl-cream xl:col-span-2 xl:block"
                        >
                            <img
                                :src="MEDIA.plate.portrait.src"
                                :srcset="MEDIA.plate.portrait.srcset"
                                sizes="(min-width: 768px) 30vw, 0px"
                                :alt="MEDIA.plate.portrait.alt"
                                loading="lazy"
                                class="absolute inset-0 size-full object-cover"
                            />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Moments + plate -->
            <aside
                class="grid gap-8 md:grid-cols-2 xl:col-span-4 xl:block xl:space-y-8"
            >
                <Reveal as="section" aria-labelledby="moments-title">
                    <div class="flex items-end justify-between gap-3">
                        <h2
                            id="moments-title"
                            class="font-serif text-2xl font-medium"
                        >
                            Últimos momentos
                        </h2>
                        <Link
                            :href="photosIndex()"
                            class="inline-flex min-h-11 items-center text-sm font-semibold underline-offset-4 hover:underline"
                            >Buscar mis fotos</Link
                        >
                    </div>
                    <div
                        v-if="recentPhotos.length"
                        class="mt-4 grid grid-cols-4 gap-2 xl:grid-cols-2"
                    >
                        <Link
                            v-for="(photo, index) in recentPhotos"
                            :key="photo.uuid"
                            :href="legadoShow(photo.participant_id)"
                            class="group relative aspect-square overflow-hidden rounded-lg bg-muted focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                            :class="index >= 4 ? 'hidden xl:block' : ''"
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
                                class="absolute top-1.5 right-1.5 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-medium"
                                >Privada</span
                            >
                        </Link>
                    </div>
                    <div
                        v-else
                        class="mt-4 rounded-xl border border-dashed border-foreground/15 bg-card p-6 text-center"
                    >
                        <Camera class="mx-auto size-6 text-fl-gold-ink" />
                        <p class="mt-3 text-sm text-muted-foreground">
                            Sube fotos desde cada carrera de tu historia o
                            encuéntralas por tu número.
                        </p>
                    </div>

                    <div v-if="recentPosts.length" class="mt-6 space-y-3">
                        <PostPreview
                            v-for="post in recentPosts"
                            :key="post.uuid"
                            :post="post"
                        />
                    </div>
                    <Button
                        as-child
                        variant="outline"
                        class="mt-4 w-full rounded-full"
                    >
                        <Link :href="communityIndex()"
                            >Compartir un momento</Link
                        >
                    </Button>
                </Reveal>

                <!-- Legacy Plate: the one dark accent block -->
                <Reveal
                    as="section"
                    class="overflow-hidden rounded-2xl bg-fl-black text-white"
                    aria-labelledby="plate-title"
                >
                    <img
                        :src="MEDIA.plate.nfc.src"
                        :srcset="MEDIA.plate.nfc.srcset"
                        sizes="(min-width: 1024px) 30vw, 100vw"
                        alt=""
                        loading="lazy"
                        class="aspect-[16/9] w-full object-cover opacity-90"
                    />
                    <div class="p-6">
                        <Nfc class="size-5 text-fl-gold" />
                        <h2
                            id="plate-title"
                            class="mt-3 font-serif text-2xl leading-tight"
                        >
                            Conecta tu Legacy Plate
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-white/70">
                            Una pieza física que conecta tu medalla con tu
                            historia digital.
                        </p>
                        <Button
                            class="mt-5 min-h-11 w-full rounded-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                            @click="linkDialogOpen = true"
                        >
                            Vincular mi placa
                        </Button>
                        <div
                            v-if="legacyId"
                            class="mt-5 flex items-center justify-between gap-3 border-t border-white/15 pt-4"
                        >
                            <div>
                                <p class="text-[11px] text-white/60">
                                    Tu Legacy ID
                                </p>
                                <p class="font-mono text-sm text-fl-gold">
                                    {{ legacyId }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="flex size-11 items-center justify-center rounded-full text-white/70 hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                                aria-label="Copiar Legacy ID"
                                @click="copyLegacyId"
                            >
                                <Check v-if="copied" class="size-4" />
                                <Copy v-else class="size-4" />
                            </button>
                        </div>
                    </div>
                </Reveal>
            </aside>
        </div>
    </div>

    <LinkPlateDialog v-model:open="linkDialogOpen" />
</template>
