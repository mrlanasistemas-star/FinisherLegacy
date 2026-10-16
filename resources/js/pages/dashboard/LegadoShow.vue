<script setup lang="ts">
/**
 * The event story (spec §7/§91/§102): one race told as a page of the
 * athlete's legacy — the result and its splits, the medal and Legacy Plate
 * it earned, the photos and videos, the gear that went along, the support
 * messages received and what was bought for it.
 *
 * Sections are plain <section id="…"> blocks so other features can slot
 * in without reshaping the page; the live-tracking card ("Seguimiento en
 * vivo") belongs in the #seguimiento section below.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Camera,
    Check,
    Eye,
    EyeOff,
    Heart,
    Medal as MedalIcon,
    Package,
    Plus,
    Share2,
    Trash2,
    Video as VideoIcon,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import Reveal from '@/components/motion/Reveal.vue';
import LegacyPlateViewer from '@/components/shared/LegacyPlateViewer.vue';
import type {
    LegacyPlateModelData,
    LegacyPlatePersonalization,
} from '@/components/shared/LegacyPlateViewer.vue';
import MediaUploader from '@/components/shared/MediaUploader.vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import { MEDIA } from '@/config/media';
import { shortDate } from '@/lib/datetime';
import { dashboard } from '@/routes';
import { show as eventShow } from '@/routes/events';
import { show as orderShow } from '@/routes/store/orders';
import { legacyPlateStatusLabel } from '@/types/legado';

type Split = {
    label: string | null;
    distance_value: string | null;
    distance_unit: string | null;
    segment_time: string | null;
    elapsed_time: string | null;
    pace: string | null;
};

type Result = {
    official_time: string | null;
    chip_time: string | null;
    pace: string | null;
    overall_position: number | null;
    gender_position: number | null;
    category_position: number | null;
    splits: Split[];
};

type Medal = {
    id: number;
    title: string;
    story: string | null;
    image_url: string | null;
};
type Plate = {
    id: number;
    serial_number: string | null;
    status: string;
    legacy_code: string | null;
    engraving_display_name: string | null;
};
type Media = {
    uuid: string;
    type: 'image' | 'video';
    url: string;
    is_public: boolean;
};
type Purchase = {
    id: number;
    name: string;
    quantity: number;
    line_total_minor: number;
    currency: string;
    order_uuid: string;
    payment_status: string;
};
type GearUsed = {
    uuid: string;
    athlete_owned_product_uuid: string;
    product_name: string | null;
    variant_name: string | null;
    asset_code: string | null;
    notes: string | null;
};
type AvailableGear = { uuid: string; product: string; variant: string | null };
type SupportMessage = {
    id: number;
    type: 'text' | 'audio';
    message_text: string | null;
    audio_url: string | null;
    contributor_name: string | null;
    status: string;
    is_surprise: boolean;
    created_at: string;
};
type SupportSession = {
    public_code: string;
    title: string;
    public_url: string;
    status: string;
    messages: SupportMessage[];
};

const props = defineProps<{
    participant: {
        id: number;
        event: string | null;
        edition: string | null;
        race: string | null;
        bib_number: string | null;
        event_date: string | null;
    };
    story: {
        event_slug: string | null;
        distance: string | null;
        city: string | null;
        state: string | null;
        year: number | null;
        upcoming: boolean;
        cover_url: string | null;
        category: string | null;
    };
    result: Result | null;
    medals: Medal[];
    legacyPlate: {
        status: string | null;
        serial_number: string | null;
        legacy_code: string | null;
        model: LegacyPlateModelData | null;
        personalization: LegacyPlatePersonalization;
    } | null;
    plates: Plate[];
    media: Media[];
    mediaLimits: { images: number; videos: number };
    mediaRemaining: { images: number; videos: number };
    gearUsed: GearUsed[];
    availableGear: AvailableGear[];
    purchases: Purchase[];
    supportSession: SupportSession | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Mi legado', href: dashboard() }],
    },
});

const heroImage = computed(
    () => props.story.cover_url ?? props.medals[0]?.image_url ?? null,
);

const place = computed(() =>
    [props.story.city, props.story.state].filter(Boolean).join(', '),
);

const raceLine = computed(() =>
    [
        props.participant.race,
        props.story.distance,
        props.participant.bib_number
            ? `#${props.participant.bib_number}`
            : null,
    ]
        .filter(Boolean)
        .join(' · '),
);

const positions = computed(() =>
    [
        { label: 'General', value: props.result?.overall_position },
        { label: 'Rama', value: props.result?.gender_position },
        {
            label: props.story.category
                ? `Categoría ${props.story.category}`
                : 'Categoría',
            value: props.result?.category_position,
        },
    ].filter((p) => p.value),
);

const photos = computed(() => props.media.filter((m) => m.type === 'image'));
const videos = computed(() => props.media.filter((m) => m.type === 'video'));
const approvedMessages = computed(
    () =>
        props.supportSession?.messages.filter((m) => m.status !== 'rejected') ??
        [],
);

/* Media */
function toggleVisibility(item: Media) {
    router.patch(
        `/dashboard/media/${item.uuid}/visibility`,
        { is_public: !item.is_public },
        { preserveScroll: true },
    );
}

function removeMedia(item: Media) {
    if (!window.confirm('¿Eliminar este recuerdo? No se puede deshacer.')) {
        return;
    }

    router.delete(`/dashboard/media/${item.uuid}`, { preserveScroll: true });
}

/* Gear */
const selectedOwnedProduct = ref<string | number | null>(null);
const gearOptions = computed(() =>
    props.availableGear.map((owned) => ({
        value: owned.uuid,
        label: owned.variant
            ? `${owned.product} — ${owned.variant}`
            : owned.product,
    })),
);

function assignGear() {
    if (!selectedOwnedProduct.value) {
        return;
    }

    router.post(
        `/dashboard/legado/${props.participant.id}/gear`,
        { athlete_owned_product_uuid: selectedOwnedProduct.value },
        {
            preserveScroll: true,
            onSuccess: () => (selectedOwnedProduct.value = null),
        },
    );
}

function removeGear(gear: GearUsed) {
    router.delete(
        `/dashboard/legado/${props.participant.id}/gear/${gear.uuid}`,
        { preserveScroll: true },
    );
}

/* Support */
const supportForm = useForm({
    title: `Apoya mi carrera en ${props.participant.event ?? 'mi próximo evento'}`,
});

function createSupportSession() {
    supportForm.post(`/dashboard/legado/${props.participant.id}/support`, {
        preserveScroll: true,
    });
}

const linkCopied = ref(false);

async function shareSupportLink() {
    if (!props.supportSession) {
        return;
    }

    const url = props.supportSession.public_url;

    try {
        if (navigator.share) {
            await navigator.share({ title: props.supportSession.title, url });

            return;
        }

        await navigator.clipboard.writeText(url);
        linkCopied.value = true;
        setTimeout(() => (linkCopied.value = false), 1800);
    } catch {
        // Share sheet dismissed or clipboard blocked.
    }
}

function moderate(id: number, action: 'approve' | 'reject') {
    router.post(
        `/support-messages/${id}/${action}`,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="participant.event ?? 'Mi legado'" />

    <article class="pb-16">
        <!-- Hero -->
        <header class="relative isolate overflow-hidden">
            <div class="absolute inset-0 -z-10 bg-fl-cream">
                <img
                    v-if="heroImage"
                    :src="heroImage"
                    alt=""
                    class="size-full object-cover"
                    fetchpriority="high"
                />
                <div
                    v-else
                    class="size-full bg-[radial-gradient(90%_120%_at_85%_0%,rgb(201_164_92/0.35)_0%,transparent_55%),linear-gradient(160deg,#2A2824_0%,#171714_70%)]"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/45 to-black/10"
                />
            </div>

            <div class="fl-container pt-5 pb-8 text-white sm:pb-12">
                <Link
                    :href="dashboard()"
                    class="inline-flex min-h-11 items-center gap-1.5 rounded-full text-sm text-white/80 hover:text-white focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                >
                    <ArrowLeft class="size-4" /> Mi legado
                </Link>

                <div class="mt-16 max-w-3xl sm:mt-24 lg:mt-32">
                    <p
                        class="text-[11px] font-semibold tracking-[0.22em] text-white/80 uppercase"
                    >
                        {{
                            [shortDate(participant.event_date), place]
                                .filter(Boolean)
                                .join(' · ') || 'Tu carrera'
                        }}
                    </p>
                    <h1
                        class="mt-3 font-serif text-4xl leading-[1.05] font-medium tracking-tight sm:text-6xl"
                    >
                        {{ participant.event ?? 'Evento' }}
                    </h1>
                    <p v-if="raceLine" class="mt-3 text-base text-white/85">
                        {{ raceLine }}
                    </p>
                </div>

                <div
                    v-if="result?.official_time"
                    class="mt-8 flex flex-wrap items-end gap-x-10 gap-y-4"
                >
                    <div>
                        <p class="text-xs text-white/70">Tiempo oficial</p>
                        <p
                            class="legacy-numeric font-serif text-5xl leading-none font-medium tabular-nums sm:text-7xl"
                        >
                            {{ result.official_time }}
                        </p>
                    </div>
                    <div v-if="result.overall_position">
                        <p class="text-xs text-white/70">Posición general</p>
                        <p
                            class="legacy-numeric font-serif text-3xl leading-none tabular-nums sm:text-4xl"
                        >
                            #{{ result.overall_position }}
                        </p>
                    </div>
                </div>
                <p
                    v-else-if="story.upcoming"
                    class="mt-8 inline-flex rounded-full bg-white/15 px-4 py-2 text-sm backdrop-blur"
                >
                    Estás inscrito. Esta página se llenará el día de la carrera.
                </p>
            </div>
        </header>

        <!-- Section index -->
        <nav
            class="sticky top-0 z-20 border-b border-border bg-background/90 backdrop-blur"
            aria-label="Secciones de la carrera"
        >
            <div
                class="fl-container flex [scrollbar-width:none] gap-1 overflow-x-auto py-2 text-sm"
            >
                <a
                    v-for="link in [
                        { id: 'resultado', label: 'Resultado' },
                        { id: 'seguimiento', label: 'Apoyo' },
                        { id: 'recuerdos', label: 'Recuerdos' },
                        { id: 'legacy-plate', label: 'Legacy Plate' },
                        { id: 'equipo', label: 'Equipo' },
                    ]"
                    :key="link.id"
                    :href="`#${link.id}`"
                    class="inline-flex min-h-11 shrink-0 items-center rounded-full px-4 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >{{ link.label }}</a
                >
            </div>
        </nav>

        <div class="fl-container mt-10 space-y-16">
            <!-- Result -->
            <section
                id="resultado"
                class="scroll-mt-20"
                aria-labelledby="resultado-title"
            >
                <p class="fl-eyebrow">El resultado</p>
                <h2
                    id="resultado-title"
                    class="fl-display mt-2 text-3xl sm:text-4xl"
                >
                    {{
                        result
                            ? 'Así cruzaste la meta.'
                            : 'Tu resultado está en camino.'
                    }}
                </h2>

                <template v-if="result">
                    <dl
                        class="mt-8 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-border bg-border sm:grid-cols-4"
                    >
                        <div class="bg-card p-5">
                            <dt class="text-xs text-muted-foreground">
                                Tiempo oficial
                            </dt>
                            <dd
                                class="legacy-numeric mt-1 font-serif text-2xl tabular-nums sm:text-3xl"
                            >
                                {{ result.official_time ?? '—' }}
                            </dd>
                        </div>
                        <div class="bg-card p-5">
                            <dt class="text-xs text-muted-foreground">
                                Tiempo chip
                            </dt>
                            <dd
                                class="legacy-numeric mt-1 font-serif text-2xl tabular-nums sm:text-3xl"
                            >
                                {{ result.chip_time ?? '—' }}
                            </dd>
                        </div>
                        <div class="bg-card p-5">
                            <dt class="text-xs text-muted-foreground">Ritmo</dt>
                            <dd
                                class="legacy-numeric mt-1 font-serif text-2xl tabular-nums sm:text-3xl"
                            >
                                {{ result.pace ?? '—' }}
                            </dd>
                        </div>
                        <div class="bg-card p-5">
                            <dt class="text-xs text-muted-foreground">
                                Distancia
                            </dt>
                            <dd
                                class="legacy-numeric mt-1 font-serif text-2xl tabular-nums sm:text-3xl"
                            >
                                {{ story.distance ?? '—' }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="positions.length"
                        class="mt-4 flex flex-wrap gap-2"
                    >
                        <span
                            v-for="position in positions"
                            :key="position.label"
                            class="inline-flex items-center gap-2 rounded-full border border-border bg-card px-4 py-2 text-sm"
                        >
                            <span class="text-muted-foreground">{{
                                position.label
                            }}</span>
                            <strong class="legacy-numeric tabular-nums"
                                >#{{ position.value }}</strong
                            >
                        </span>
                    </div>

                    <div v-if="result.splits.length" class="mt-8">
                        <h3 class="text-sm font-semibold">
                            Tu carrera, tramo a tramo
                        </h3>
                        <ol
                            class="mt-3 divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card"
                        >
                            <li
                                v-for="(split, index) in result.splits"
                                :key="index"
                                class="grid grid-cols-[1.75rem_1fr_auto] items-center gap-3 px-4 py-3 text-sm sm:grid-cols-[2rem_1fr_8rem_8rem] sm:px-5"
                            >
                                <span
                                    class="flex size-7 items-center justify-center rounded-full bg-fl-cream text-xs font-semibold text-fl-gold-ink"
                                    >{{ index + 1 }}</span
                                >
                                <span class="truncate text-foreground">{{
                                    split.label ??
                                    `${split.distance_value ?? ''} ${split.distance_unit ?? ''}`
                                }}</span>
                                <span
                                    class="legacy-numeric hidden text-right text-muted-foreground tabular-nums sm:block"
                                    >{{ split.pace ?? '' }}</span
                                >
                                <span
                                    class="legacy-numeric text-right font-semibold tabular-nums"
                                    >{{
                                        split.elapsed_time ??
                                        split.segment_time ??
                                        '—'
                                    }}</span
                                >
                            </li>
                        </ol>
                    </div>
                </template>
                <p v-else class="mt-4 max-w-xl text-muted-foreground">
                    {{
                        story.upcoming
                            ? 'Cuando cruces la meta, aquí verás tu tiempo, tu ritmo y tu posición.'
                            : 'En cuanto el evento publique los resultados, tu tiempo y posición aparecerán aquí.'
                    }}
                </p>
            </section>

            <!-- Support + live tracking -->
            <section
                id="seguimiento"
                class="scroll-mt-20"
                aria-labelledby="apoyo-title"
            >
                <p class="fl-eyebrow">Apoyo</p>
                <h2
                    id="apoyo-title"
                    class="fl-display mt-2 text-3xl sm:text-4xl"
                >
                    Tu gente también corre contigo.
                </h2>

                <div class="mt-8 grid gap-5 lg:grid-cols-12">
                    <!--
                        Live tracking ("Seguimiento en vivo") card goes in
                        this column, above the support link.
                    -->
                    <div class="space-y-5 lg:col-span-5">
                        <div
                            v-if="!supportSession"
                            class="rounded-2xl border border-border bg-card p-6"
                        >
                            <Heart class="size-6 text-fl-gold-ink" />
                            <p class="mt-3 font-semibold">
                                Recibe mensajes de apoyo
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Crea un enlace para que tu familia y amigos te
                                manden mensajes de texto o de voz durante esta
                                carrera.
                            </p>
                            <Button
                                class="mt-5 min-h-11 rounded-full"
                                :disabled="supportForm.processing"
                                @click="createSupportSession"
                            >
                                <Heart class="size-4" />
                                Crear mi enlace de apoyo
                            </Button>
                        </div>
                        <div
                            v-else
                            class="rounded-2xl border border-border bg-card p-6"
                        >
                            <p class="font-semibold">
                                {{ supportSession.title }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Comparte este enlace con quien quieras que te
                                acompañe.
                            </p>
                            <p
                                class="mt-4 truncate rounded-lg bg-muted px-3 py-2.5 font-mono text-xs text-foreground"
                            >
                                {{ supportSession.public_url }}
                            </p>
                            <Button
                                variant="outline"
                                class="mt-3 min-h-11 w-full rounded-full"
                                @click="shareSupportLink"
                            >
                                <Check v-if="linkCopied" class="size-4" />
                                <Share2 v-else class="size-4" />
                                {{
                                    linkCopied
                                        ? 'Enlace copiado'
                                        : 'Compartir enlace'
                                }}
                            </Button>
                        </div>
                    </div>

                    <div class="lg:col-span-7">
                        <div v-if="approvedMessages.length" class="space-y-3">
                            <Reveal
                                v-for="msg in approvedMessages"
                                :key="msg.id"
                                class="rounded-2xl border border-border bg-card p-5"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <p class="font-semibold">
                                            {{
                                                msg.contributor_name ??
                                                'Alguien que te quiere'
                                            }}
                                            <span
                                                v-if="msg.is_surprise"
                                                class="ml-1 rounded-full bg-fl-cream px-2 py-0.5 text-xs font-medium text-fl-gold-ink"
                                                >Sorpresa</span
                                            >
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ msg.created_at }}
                                        </p>
                                    </div>
                                    <div
                                        v-if="msg.status === 'pending'"
                                        class="flex shrink-0 gap-1"
                                    >
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            class="size-11 rounded-full text-emerald-700 hover:bg-emerald-500/10"
                                            aria-label="Aprobar mensaje"
                                            @click="moderate(msg.id, 'approve')"
                                        >
                                            <Check class="size-4" />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            class="size-11 rounded-full text-red-700 hover:bg-red-500/10"
                                            aria-label="Rechazar mensaje"
                                            @click="moderate(msg.id, 'reject')"
                                        >
                                            <X class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                                <p
                                    v-if="msg.message_text"
                                    class="mt-3 font-serif text-lg leading-snug text-foreground"
                                >
                                    “{{ msg.message_text }}”
                                </p>
                                <audio
                                    v-else-if="msg.audio_url"
                                    :src="msg.audio_url"
                                    controls
                                    preload="none"
                                    class="mt-3 h-10 w-full max-w-sm"
                                />
                                <p
                                    v-else-if="msg.is_surprise"
                                    class="mt-3 text-sm text-muted-foreground"
                                >
                                    Un mensaje sorpresa te espera en la carrera.
                                </p>
                            </Reveal>
                        </div>
                        <div
                            v-else
                            class="flex h-full min-h-40 flex-col items-center justify-center rounded-2xl border border-dashed border-foreground/15 p-6 text-center"
                        >
                            <Heart class="size-6 text-muted-foreground" />
                            <p class="mt-3 text-sm text-muted-foreground">
                                Los mensajes de apoyo que recibas aparecerán
                                aquí.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Memories -->
            <section
                id="recuerdos"
                class="scroll-mt-20"
                aria-labelledby="recuerdos-title"
            >
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="fl-eyebrow">Recuerdos</p>
                        <h2
                            id="recuerdos-title"
                            class="fl-display mt-2 text-3xl sm:text-4xl"
                        >
                            Lo que guardaste de este día.
                        </h2>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ photos.length }}
                        {{ photos.length === 1 ? 'foto' : 'fotos' }} ·
                        {{ videos.length }}
                        {{ videos.length === 1 ? 'video' : 'videos' }}
                    </p>
                </div>

                <div
                    v-if="media.length"
                    class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
                >
                    <figure
                        v-for="(item, index) in media"
                        :key="item.uuid"
                        class="group relative overflow-hidden rounded-xl bg-muted"
                        :class="
                            index === 0
                                ? 'col-span-2 row-span-2 aspect-square'
                                : 'aspect-square'
                        "
                    >
                        <img
                            v-if="item.type === 'image'"
                            :src="item.url"
                            :alt="`Foto en ${participant.event ?? 'la carrera'}`"
                            loading="lazy"
                            class="size-full object-cover"
                        />
                        <video
                            v-else
                            :src="item.url"
                            class="size-full object-cover"
                            controls
                            preload="metadata"
                            playsinline
                        />
                        <span
                            class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-white/90 px-2 py-0.5 text-[11px] font-medium text-foreground"
                        >
                            <VideoIcon
                                v-if="item.type === 'video'"
                                class="size-3"
                            />
                            <Camera v-else class="size-3" />
                            {{ item.is_public ? 'Pública' : 'Solo tú' }}
                        </span>
                        <figcaption
                            class="absolute top-2 right-2 flex gap-1 opacity-100 transition-opacity sm:opacity-0 sm:group-focus-within:opacity-100 sm:group-hover:opacity-100"
                        >
                            <button
                                type="button"
                                class="flex size-11 items-center justify-center rounded-full bg-white/90 text-foreground shadow-sm hover:bg-white focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none sm:size-9"
                                :aria-label="
                                    item.is_public
                                        ? 'Hacer privada'
                                        : 'Hacer pública'
                                "
                                :title="
                                    item.is_public
                                        ? 'Hacer privada'
                                        : 'Hacer pública'
                                "
                                @click="toggleVisibility(item)"
                            >
                                <EyeOff v-if="item.is_public" class="size-4" />
                                <Eye v-else class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="flex size-11 items-center justify-center rounded-full bg-white/90 text-red-700 shadow-sm hover:bg-white focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none sm:size-9"
                                aria-label="Eliminar"
                                title="Eliminar"
                                @click="removeMedia(item)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </figcaption>
                    </figure>
                </div>

                <div class="mt-6">
                    <MediaUploader
                        :upload-url="`/dashboard/my-events/${participant.id}/media`"
                        :images-remaining="mediaRemaining.images"
                        :images-limit="mediaLimits.images"
                        :videos-remaining="mediaRemaining.videos"
                        :videos-limit="mediaLimits.videos"
                    />
                </div>
            </section>

            <!-- Medal + Legacy Plate -->
            <section
                id="legacy-plate"
                class="scroll-mt-20"
                aria-labelledby="plate-title"
            >
                <p class="fl-eyebrow">Lo que te llevaste</p>
                <h2
                    id="plate-title"
                    class="fl-display mt-2 text-3xl sm:text-4xl"
                >
                    Tu medalla y tu Legacy Plate.
                </h2>

                <div class="mt-8 grid gap-5 lg:grid-cols-12">
                    <div class="space-y-4 lg:col-span-5">
                        <div
                            v-for="medal in medals"
                            :key="medal.id"
                            class="fl-card flex items-center gap-4 p-4"
                        >
                            <img
                                v-if="medal.image_url"
                                :src="medal.image_url"
                                :alt="`Medalla ${medal.title}`"
                                loading="lazy"
                                class="size-24 shrink-0 rounded-xl object-cover"
                            />
                            <span
                                v-else
                                class="flex size-24 shrink-0 items-center justify-center rounded-xl bg-fl-cream"
                            >
                                <MedalIcon class="size-8 text-fl-gold-ink" />
                            </span>
                            <div class="min-w-0">
                                <p class="font-semibold">{{ medal.title }}</p>
                                <p
                                    v-if="medal.story"
                                    class="mt-1 line-clamp-3 text-sm text-muted-foreground"
                                >
                                    {{ medal.story }}
                                </p>
                                <Link
                                    :href="`/dashboard/medals/${medal.id}`"
                                    class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold underline-offset-4 hover:underline"
                                    >Ver medalla</Link
                                >
                            </div>
                        </div>
                        <div
                            v-if="!medals.length"
                            class="rounded-2xl border border-dashed border-foreground/15 p-6 text-sm text-muted-foreground"
                        >
                            ¿Tienes la medalla de esta carrera? Tómale una foto
                            y agrégala a tu historia.
                            <Link
                                href="/dashboard/medals/create"
                                class="mt-2 block min-h-11 font-semibold text-foreground underline-offset-4 hover:underline"
                                >Agregar medalla</Link
                            >
                        </div>
                    </div>

                    <div class="lg:col-span-7">
                        <div
                            v-if="legacyPlate"
                            class="rounded-2xl border border-fl-gold/30 bg-card p-5 sm:p-6"
                        >
                            <div
                                class="mb-4 flex flex-wrap items-center justify-between gap-2"
                            >
                                <p class="font-semibold">Legacy Plate</p>
                                <span
                                    v-if="legacyPlate.status"
                                    class="rounded-full bg-fl-cream px-3 py-1 text-xs font-medium text-fl-gold-ink"
                                >
                                    {{
                                        legacyPlateStatusLabel[
                                            legacyPlate.status
                                        ] ?? 'En preparación'
                                    }}
                                </span>
                            </div>
                            <LegacyPlateViewer
                                v-if="legacyPlate.model"
                                :model="legacyPlate.model"
                                :personalization="legacyPlate.personalization"
                                mode="athlete"
                            />
                            <p
                                v-else
                                class="rounded-xl bg-muted p-6 text-center text-sm text-muted-foreground"
                            >
                                Tu Legacy Plate está en preparación. Verás cómo
                                queda en cuanto tenga su diseño.
                            </p>
                        </div>
                        <div
                            v-else
                            class="grid overflow-hidden rounded-2xl border border-border bg-card sm:grid-cols-2"
                        >
                            <img
                                :src="MEDIA.plate.front.src"
                                :srcset="MEDIA.plate.front.srcset"
                                sizes="(min-width: 640px) 30vw, 100vw"
                                :alt="MEDIA.plate.front.alt"
                                loading="lazy"
                                class="aspect-[4/3] size-full object-cover"
                            />
                            <div class="flex flex-col justify-center p-6">
                                <p class="font-serif text-2xl leading-tight">
                                    Convierte esta carrera en una pieza.
                                </p>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    Una pieza física que conecta tu medalla con
                                    tu historia digital: tu nombre, tu tiempo y
                                    tu distancia.
                                </p>
                                <Button
                                    as-child
                                    variant="outline"
                                    class="mt-4 w-fit rounded-full"
                                >
                                    <Link href="/tienda">Ver Legacy Plate</Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Gear -->
            <section
                id="equipo"
                class="scroll-mt-20"
                aria-labelledby="equipo-title"
            >
                <p class="fl-eyebrow">Tu equipo</p>
                <h2
                    id="equipo-title"
                    class="fl-display mt-2 text-3xl sm:text-4xl"
                >
                    Lo que llevabas puesto.
                </h2>

                <ul
                    v-if="gearUsed.length"
                    class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <li
                        v-for="item in gearUsed"
                        :key="item.uuid"
                        class="fl-card flex items-center justify-between gap-3 p-4"
                    >
                        <span class="flex min-w-0 items-center gap-3">
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                            >
                                <Package class="size-[18px]" />
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate font-medium">{{
                                    item.product_name
                                }}</span>
                                <span
                                    v-if="item.variant_name"
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ item.variant_name }}</span
                                >
                            </span>
                        </span>
                        <button
                            type="button"
                            class="inline-flex min-h-11 shrink-0 items-center px-2 text-xs text-muted-foreground hover:text-red-700"
                            @click="removeGear(item)"
                        >
                            Quitar
                        </button>
                    </li>
                </ul>
                <p v-else class="mt-4 text-muted-foreground">
                    Agrega el equipo de Finisher Legacy que usaste en esta
                    carrera.
                </p>

                <div
                    v-if="availableGear.length"
                    class="mt-5 flex flex-col gap-2 sm:flex-row sm:items-center"
                >
                    <FancySelect
                        v-model="selectedOwnedProduct"
                        :options="gearOptions"
                        placeholder="Elige de tu equipo"
                        aria-label="Producto de tu equipo"
                        class="sm:w-72"
                    />
                    <Button
                        variant="outline"
                        class="min-h-11 rounded-full"
                        :disabled="!selectedOwnedProduct"
                        @click="assignGear"
                    >
                        <Plus class="size-4" />
                        Agregar a esta carrera
                    </Button>
                </div>

                <!-- Purchases for this event -->
                <div v-if="purchases.length" class="mt-10">
                    <h3 class="text-sm font-semibold">
                        Lo que compraste para este evento
                    </h3>
                    <ul
                        class="mt-3 divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card"
                    >
                        <li v-for="item in purchases" :key="item.id">
                            <Link
                                :href="orderShow(item.order_uuid)"
                                class="flex min-h-14 items-center justify-between gap-3 px-4 py-3 text-sm transition-colors hover:bg-muted/50 sm:px-5"
                            >
                                <span class="min-w-0 truncate">
                                    {{ item.name }}
                                    <span
                                        v-if="item.quantity > 1"
                                        class="text-muted-foreground"
                                        >×{{ item.quantity }}</span
                                    >
                                </span>
                                <Money
                                    :minor="item.line_total_minor"
                                    :currency="item.currency"
                                    class="shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </li>
                    </ul>
                </div>
            </section>

            <div
                v-if="story.event_slug"
                class="border-t border-border pt-8 text-center"
            >
                <Link
                    :href="eventShow(story.event_slug)"
                    class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                >
                    Ver la página del evento
                </Link>
            </div>
        </div>
    </article>
</template>
