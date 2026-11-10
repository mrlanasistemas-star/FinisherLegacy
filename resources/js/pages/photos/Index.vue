<script setup lang="ts">
/**
 * "Encuentra tu momento." — search an event + bib across photographers'
 * photos (watermarked preview, price) and athletes' public photos; pick
 * the ones you want and buy them (a regular Order paid with the existing
 * online payment flow). Bought photos appear under "Compradas" with a
 * full-resolution download. Selfie search is a future, optional feature.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Camera,
    ChevronLeft,
    ChevronRight,
    Download,
    Flag,
    ImageOff,
    Lock,
    ScanFace,
    Search,
    ShieldCheck,
    ShoppingBag,
    Sparkles,
    UserRound,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import FancySelect from '@/components/forms/FancySelect.vue';
import PhotoPickCard from '@/components/photos/PhotoPickCard.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useI18n } from '@/i18n';
import { dashboard, login, register } from '@/routes';
import { landing as photographersLanding } from '@/routes/photographers';
import {
    checkout as photosCheckout,
    index as photosIndex,
} from '@/routes/photos';

type Photo = {
    uuid: string;
    kind: 'pro' | 'athlete';
    url: string;
    thumb_url: string;
    width: number | null;
    height: number | null;
    is_public: boolean;
    event: string | null;
    race: string | null;
    source: string;
    price_minor: number | null;
    currency: string | null;
    owned: boolean;
};

type Purchased = {
    uuid: string;
    thumb_url: string;
    event: string | null;
    source: string | null;
    download_url: string;
    purchased_at: string;
};

const props = defineProps<{
    events: { id: number; label: string; date: string | null }[];
    filters: { evento: number | null; numero: string };
    searched: boolean;
    /** Signed-in athlete: their events (with their bib) and the photos found. */
    myEvents: {
        edition_id: number;
        event: string;
        race: string | null;
        date: string | null;
        bib: string;
        photos: Photo[];
    }[];
    results: Photo[];
    purchased: Purchased[] | null;
    mine: Photo[] | null;
    purchase: { available: boolean; currency: string };
}>();

const { regional } = useI18n();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);

const eventId = ref<string | number | null>(props.filters.evento);
const bib = ref(props.filters.numero);
const searching = ref(false);
const eventOptions = computed(() =>
    props.events.map((event) => ({
        value: event.id,
        label: event.label,
        description: event.date ?? undefined,
        icon: Flag,
    })),
);

function search() {
    if (!eventId.value || !bib.value.trim()) {
        return;
    }

    searching.value = true;
    router.get(
        photosIndex().url,
        { evento: eventId.value, numero: bib.value.trim() },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['results', 'searched', 'filters'],
            onFinish: () => (searching.value = false),
        },
    );
}

const money = (minor: number) =>
    new Intl.NumberFormat(regional.value, {
        style: 'currency',
        currency: props.purchase.currency || 'MXN',
    }).format(minor / 100);

const forSale = computed(() => props.results.filter((p) => p.kind === 'pro'));
const fmtDate = (iso: string) =>
    new Date(`${iso}T12:00:00`).toLocaleDateString(regional.value, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
const myPhotoCount = computed(() =>
    props.myEvents.reduce((n, e) => n + e.photos.length, 0),
);
// Everything buyable on the page (own events + a manual search), once.
const buyable = computed(() => {
    const map = new Map<string, Photo>();
    [...props.myEvents.flatMap((e) => e.photos), ...forSale.value].forEach(
        (photo) => map.set(photo.uuid, photo),
    );

    return [...map.values()];
});
const showSearch = ref(props.searched || props.myEvents.length === 0);

function selectAll(photos: Photo[]) {
    const next = new Set(selected.value);
    photos.filter((p) => !p.owned).forEach((p) => next.add(p.uuid));
    selected.value = next;
}
const athletePhotos = computed(() =>
    props.results.filter((p) => p.kind === 'athlete'),
);

const selected = ref<Set<string>>(new Set());
function toggle(photo: Photo) {
    if (photo.kind !== 'pro' || photo.owned) {
        return;
    }

    const next = new Set(selected.value);

    if (next.has(photo.uuid)) {
        next.delete(photo.uuid);
    } else {
        next.add(photo.uuid);
    }

    selected.value = next;
}
const selectedPhotos = computed(() =>
    buyable.value.filter((p) => selected.value.has(p.uuid)),
);
const total = computed(() =>
    selectedPhotos.value.reduce((sum, p) => sum + (p.price_minor ?? 0), 0),
);

const buying = ref(false);
function buy() {
    if (!selectedPhotos.value.length) {
        return;
    }

    buying.value = true;
    router.post(
        photosCheckout().url,
        { photos: [...selected.value] },
        {
            preserveScroll: true,
            onError: (errors) => {
                // The server never charges a partial selection: it names
                // the photos that changed so we drop exactly those.
                const removed = (errors.photos_removed ?? '')
                    .split(',')
                    .filter(Boolean);

                if (removed.length) {
                    const next = new Set(selected.value);
                    removed.forEach((uuid) => next.delete(uuid));
                    selected.value = next;
                }

                if (errors.photos) {
                    toast.error(errors.photos);
                }
            },
            onFinish: () => (buying.value = false),
        },
    );
}

// Lightbox state. `v-model:open` on the shared Dialog drives this through
// `lightboxOpen` so Reka owns the focus-trap, Esc and overlay dismissal.
const lightbox = ref<Photo | null>(null);
const lightboxOpen = computed({
    get: () => lightbox.value !== null,
    set: (open: boolean) => {
        if (!open) {
            lightbox.value = null;
        }
    },
});

// Keyboard arrows step through every photo the lightbox can open (the
// athlete's own-event photos plus the pro results), in display order.
const lightboxIndex = computed(() =>
    lightbox.value
        ? buyable.value.findIndex((p) => p.uuid === lightbox.value!.uuid)
        : -1,
);
function stepLightbox(direction: 1 | -1) {
    const list = buyable.value;

    if (!list.length || lightboxIndex.value < 0) {
        return;
    }

    const next = (lightboxIndex.value + direction + list.length) % list.length;
    lightbox.value = list[next] ?? null;
}

function toggleFromLightbox() {
    if (lightbox.value) {
        toggle(lightbox.value);
    }
}
</script>

<template>
    <SeoHead
        :title="$t('Fotos de evento')"
        :description="
            $t(
                'Busca tus fotografías de evento por número de participante, cómpralas en alta resolución y revive la emoción de cada carrera.',
            )
        "
    />

    <div class="relative">
        <div
            class="pointer-events-none absolute inset-x-0 top-0 h-[28rem] bg-[radial-gradient(60%_80%_at_85%_0%,#f4eedf,transparent_70%)]"
            aria-hidden="true"
        />
    </div>
    <section class="fl-container relative pt-12 pb-8 sm:pt-16">
        <div
            class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    {{ $t('Fotos de evento') }}
                </p>
                <h1
                    class="fl-display mt-5 text-[2.4rem] leading-[1.04] sm:text-6xl"
                >
                    {{ $t('Encuentra') }}
                    <span class="font-medium text-fl-gold-ink italic">{{
                        $t('tu momento.')
                    }}</span>
                </h1>
                <p
                    class="mt-5 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg"
                >
                    {{
                        myEvents.length
                            ? $t(
                                  'Ya sabemos en qué eventos corriste y con qué número: aquí están tus fotos. Solo elige cuáles quieres.',
                              )
                            : $t(
                                  'Elige el evento, escribe tu número de participante y mira las fotos que los fotógrafos tomaron de ti en la carrera.',
                              )
                    }}
                </p>
            </div>
            <Link
                :href="photographersLanding()"
                class="inline-flex min-h-10 items-center gap-2 self-start rounded-full border border-border bg-card px-4 py-2 text-sm font-medium transition-colors hover:border-fl-gold lg:self-auto"
            >
                <Camera class="size-4 text-fl-gold-ink" />
                {{ $t('¿Eres fotógrafo? Vende tus fotos') }}
            </Link>
        </div>

        <button
            v-if="!showSearch"
            type="button"
            class="mt-8 inline-flex items-center gap-2 text-sm font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
            @click="showSearch = true"
        >
            <Search class="size-4" />
            {{ $t('¿Buscas fotos de otro evento o de otro número?') }}
        </button>
        <div v-if="showSearch" class="mt-10 grid gap-4 lg:grid-cols-12">
            <form
                class="fl-card grid gap-4 p-5 sm:grid-cols-[1fr_180px_auto] sm:items-end sm:p-6 lg:col-span-8"
                role="search"
                @submit.prevent="search"
            >
                <div class="grid gap-2">
                    <Label for="photo-event">{{ $t('Evento') }}</Label>
                    <FancySelect
                        id="photo-event"
                        v-model="eventId"
                        :options="eventOptions"
                        :placeholder="$t('Selecciona un evento')"
                        class="h-11"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="photo-bib">{{
                        $t('Número de participante')
                    }}</Label>
                    <Input
                        id="photo-bib"
                        v-model="bib"
                        inputmode="numeric"
                        autocomplete="off"
                        :placeholder="$t('Ej. 482')"
                        class="legacy-numeric h-11"
                    />
                </div>
                <Button
                    type="submit"
                    class="h-11 rounded-full px-6"
                    :disabled="!eventId || !bib.trim() || searching"
                >
                    <Search class="size-4" />
                    {{ $t('Buscar') }}
                </Button>
            </form>
            <div
                class="flex items-start gap-4 rounded-card border border-dashed border-foreground/20 bg-card p-5 sm:p-6 lg:col-span-4"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                >
                    <ScanFace class="size-5" />
                </span>
                <div>
                    <p class="flex flex-wrap items-center gap-2 font-semibold">
                        {{ $t('Buscar con mi selfie') }}
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                            >{{ $t('Opcional · Próximamente') }}</span
                        >
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            $t(
                                'El reconocimiento facial será una integración futura y siempre con tu consentimiento.',
                            )
                        }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="fl-container pb-20">
        <div class="grid gap-8 lg:grid-cols-12">
            <div class="min-w-0 space-y-12 lg:col-span-8">
                <!-- Signed-in athlete: their own events, photos already found -->
                <div v-if="myEvents.length" class="space-y-10">
                    <section
                        v-for="ev in myEvents"
                        :key="ev.edition_id"
                        :aria-label="
                            $t('Tus fotos de {event}', { event: ev.event })
                        "
                    >
                        <div
                            class="flex flex-wrap items-end justify-between gap-3"
                        >
                            <div>
                                <h2 class="font-serif text-2xl">
                                    {{ ev.event }}
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    <template v-if="ev.race"
                                        >{{ ev.race }} ·
                                    </template>
                                    <template v-if="ev.date"
                                        >{{ fmtDate(ev.date) }} ·
                                    </template>
                                    {{ $t('Tu número') }}
                                    <span
                                        class="legacy-numeric font-semibold text-foreground"
                                        >#{{ ev.bib }}</span
                                    >
                                    ·
                                    {{
                                        $tc(
                                            '{count} foto|{count} fotos',
                                            ev.photos.length,
                                        )
                                    }}
                                </p>
                            </div>
                            <button
                                v-if="ev.photos.some((p) => !p.owned)"
                                type="button"
                                class="rounded-full border border-border bg-card px-3 py-1.5 text-xs font-semibold hover:border-foreground/25"
                                @click="selectAll(ev.photos)"
                            >
                                {{ $t('Seleccionar todas') }}
                            </button>
                        </div>
                        <div
                            v-if="ev.photos.length"
                            class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3"
                        >
                            <PhotoPickCard
                                v-for="photo in ev.photos"
                                :key="photo.uuid"
                                :photo="photo"
                                :selected="selected.has(photo.uuid)"
                                :price="money(photo.price_minor ?? 0)"
                                @toggle="toggle(photo)"
                                @open="lightbox = photo"
                            />
                        </div>
                        <p
                            v-else
                            class="mt-3 flex items-start gap-3 rounded-card border border-dashed border-foreground/15 bg-card p-4 text-sm text-muted-foreground"
                        >
                            <ImageOff class="size-5 shrink-0" />
                            {{
                                $t(
                                    'Aún no hay fotos de fotógrafos con tu número en este evento. Se publican después de la carrera.',
                                )
                            }}
                        </p>
                    </section>
                    <p
                        v-if="myPhotoCount === 0"
                        class="text-sm text-muted-foreground"
                    >
                        {{
                            $t(
                                'Te avisaremos aquí en cuanto un fotógrafo publique fotos con tu número.',
                            )
                        }}
                    </p>
                </div>

                <!-- Before searching: how it works -->
                <ol
                    v-if="!searched && !myEvents.length"
                    class="grid gap-3 sm:grid-cols-3"
                    :aria-label="$t('Cómo encontrar tus fotos')"
                >
                    <li
                        v-for="(step, i) in [
                            {
                                title: $t('Elige tu evento'),
                                text: $t(
                                    'Todos los eventos con fotos publicadas por fotógrafos.',
                                ),
                            },
                            {
                                title: $t('Escribe tu número'),
                                text: $t(
                                    'El número de participante que llevabas en tu dorsal.',
                                ),
                            },
                            {
                                title: $t('Selecciona y descarga'),
                                text: $t(
                                    'Marca tus fotos, paga una sola vez y descarga en alta resolución.',
                                ),
                            },
                        ]"
                        :key="i"
                        class="fl-card p-5"
                    >
                        <span
                            class="flex size-8 items-center justify-center rounded-full bg-fl-cream font-serif text-fl-gold-ink"
                            >{{ i + 1 }}</span
                        >
                        <p class="mt-3 font-semibold">{{ step.title }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ step.text }}
                        </p>
                    </li>
                </ol>

                <template v-if="searched">
                    <div>
                        <h2 class="flex items-center gap-2 font-serif text-2xl">
                            <Camera class="size-5 text-fl-gold-ink" />
                            {{ $t('Resultado de tu búsqueda') }}
                            <span class="text-base text-muted-foreground">{{
                                forSale.length
                            }}</span>
                        </h2>
                        <div
                            v-if="forSale.length"
                            class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3"
                        >
                            <PhotoPickCard
                                v-for="photo in forSale"
                                :key="photo.uuid"
                                :photo="photo"
                                :selected="selected.has(photo.uuid)"
                                :price="money(photo.price_minor ?? 0)"
                                @toggle="toggle(photo)"
                                @open="lightbox = photo"
                            />
                        </div>
                        <p
                            v-else
                            class="mt-4 flex items-start gap-3 rounded-card border border-dashed border-foreground/15 bg-card p-5 text-sm text-muted-foreground"
                        >
                            <ImageOff class="size-5 shrink-0" />
                            {{
                                $t(
                                    'Aún no hay fotos de fotógrafos con ese número. Vuelve pronto: se publican después del evento.',
                                )
                            }}
                        </p>
                    </div>

                    <div v-if="athletePhotos.length">
                        <h2 class="flex items-center gap-2 font-serif text-2xl">
                            <UserRound class="size-5 text-fl-gold-ink" />
                            {{ $t('Compartidas por atletas') }}
                        </h2>
                        <div class="mt-5 grid grid-cols-3 gap-2 sm:grid-cols-4">
                            <img
                                v-for="photo in athletePhotos"
                                :key="photo.uuid"
                                :src="photo.thumb_url"
                                :alt="
                                    photo.event
                                        ? $t('Foto de {event}', {
                                              event: photo.event,
                                          })
                                        : $t('Foto de evento')
                                "
                                loading="lazy"
                                class="aspect-square w-full rounded-control bg-muted object-cover"
                            />
                        </div>
                    </div>
                </template>

                <div v-if="purchased && purchased.length" id="compradas">
                    <h2 class="flex items-center gap-2 font-serif text-2xl">
                        <Download class="size-5 text-fl-gold-ink" />
                        {{ $t('Compradas') }}
                        <span class="text-base text-muted-foreground">{{
                            purchased.length
                        }}</span>
                    </h2>
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <article
                            v-for="item in purchased"
                            :key="item.uuid"
                            class="overflow-hidden rounded-card border border-border bg-card"
                        >
                            <img
                                :src="item.thumb_url"
                                :alt="
                                    item.event
                                        ? $t('Foto de {event}', {
                                              event: item.event,
                                          })
                                        : $t('Foto de evento')
                                "
                                loading="lazy"
                                class="aspect-[4/5] w-full object-cover"
                            />
                            <div
                                class="flex items-center justify-between gap-2 px-3 py-2.5"
                            >
                                <span class="min-w-0 truncate text-xs">{{
                                    item.event
                                }}</span>
                                <a
                                    :href="item.download_url"
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-foreground px-2.5 py-1 text-[11px] font-semibold text-background"
                                >
                                    <Download class="size-3" />
                                    {{ $t('Alta resolución') }}
                                </a>
                            </div>
                        </article>
                    </div>
                </div>

                <div v-if="mine !== null && mine.length" id="mis-fotos">
                    <h2 class="font-serif text-2xl">
                        {{ $t('Mis fotos subidas') }}
                        <span class="text-base text-muted-foreground">{{
                            mine.length
                        }}</span>
                    </h2>
                    <div
                        v-if="mine.length"
                        class="mt-5 grid grid-cols-3 gap-2 sm:grid-cols-4"
                    >
                        <div
                            v-for="photo in mine"
                            :key="photo.uuid"
                            class="relative"
                        >
                            <img
                                :src="photo.thumb_url"
                                :alt="
                                    photo.event
                                        ? $t('Mi foto en {event}', {
                                              event: photo.event,
                                          })
                                        : $t('Mi foto en evento')
                                "
                                loading="lazy"
                                class="aspect-square w-full rounded-control bg-muted object-cover"
                            />
                            <span
                                v-if="!photo.is_public"
                                class="absolute top-1.5 left-1.5 inline-flex items-center gap-1 rounded-full bg-white/90 px-1.5 py-0.5 text-[10px]"
                                ><Lock class="size-3" />
                                {{ $t('Privada') }}</span
                            >
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-muted-foreground">
                        {{
                            $t(
                                'Sube tus fotos desde el detalle de cada evento en',
                            )
                        }}
                        <Link
                            :href="dashboard()"
                            class="font-medium text-foreground underline underline-offset-4"
                            >{{ $t('Mi Legado') }}</Link
                        >.
                    </p>
                </div>

                <div
                    v-if="isGuest && !searched"
                    class="rounded-card border border-border bg-card p-6"
                >
                    <p class="font-semibold">{{ $t('¿Ya tienes cuenta?') }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{
                            $t(
                                'Inicia sesión para comprar y descargar tus fotos en alta resolución.',
                            )
                        }}
                    </p>
                    <div class="mt-4 flex gap-2">
                        <Button as-child class="rounded-full">
                            <Link :href="login()">{{ $t('Entrar') }}</Link>
                        </Button>
                        <Button as-child variant="outline" class="rounded-full">
                            <Link :href="register()">{{
                                $t('Crear cuenta')
                            }}</Link>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Selection -->
            <aside class="lg:col-span-4">
                <div class="fl-card p-6 lg:sticky lg:top-24">
                    <h2 class="font-serif text-2xl">
                        {{ $t('Tu selección') }}
                    </h2>
                    <div
                        v-if="selectedPhotos.length"
                        class="mt-4 flex -space-x-3"
                    >
                        <img
                            v-for="photo in selectedPhotos.slice(0, 6)"
                            :key="photo.uuid"
                            :src="photo.thumb_url"
                            alt=""
                            class="size-12 rounded-control border-2 border-card object-cover"
                        />
                    </div>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">
                                {{ $t('Fotografías') }}
                            </dt>
                            <dd class="legacy-numeric font-semibold">
                                {{ selectedPhotos.length }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between border-t border-border pt-3"
                        >
                            <dt class="font-medium">{{ $t('Total') }}</dt>
                            <dd class="legacy-numeric font-serif text-2xl">
                                {{ money(total) }}
                            </dd>
                        </div>
                    </dl>
                    <ul
                        class="mt-6 space-y-2.5 border-t border-border pt-5 text-sm"
                    >
                        <li class="flex items-center gap-2.5">
                            <ShieldCheck class="size-4 text-fl-gold-ink" />
                            {{ $t('Licencia de uso personal') }}
                        </li>
                        <li class="flex items-center gap-2.5">
                            <Sparkles class="size-4 text-fl-gold-ink" />
                            {{ $t('Alta resolución, sin marca de agua') }}
                        </li>
                        <li class="flex items-center gap-2.5">
                            <Download class="size-4 text-fl-gold-ink" />
                            {{ $t('Descarga inmediata al pagar') }}
                        </li>
                    </ul>
                    <Button
                        v-if="isGuest"
                        as-child
                        class="mt-6 h-11 w-full rounded-full"
                    >
                        <Link :href="login()">{{
                            $t('Inicia sesión para comprar')
                        }}</Link>
                    </Button>
                    <Button
                        v-else
                        class="mt-6 h-11 w-full rounded-full"
                        :disabled="!selectedPhotos.length || buying"
                        @click="buy"
                    >
                        <ShoppingBag class="size-4" />
                        {{
                            buying ? $t('Preparando…') : $t('Comprar selección')
                        }}
                    </Button>
                    <p class="mt-3 text-center text-xs text-muted-foreground">
                        {{
                            $t(
                                'Pago seguro con tarjeta. Cada fotógrafo recibe su parte de la venta.',
                            )
                        }}
                    </p>
                </div>
            </aside>
        </div>
    </section>

    <!-- Lightbox: the shared Reka Dialog handles focus-trap, Esc, overlay
         dismissal and labelling; arrow keys step through the photos. -->
    <Dialog v-model:open="lightboxOpen">
        <DialogContent
            :show-close-button="false"
            class="gap-0 overflow-hidden rounded-media p-0 sm:max-w-4xl"
            @keydown.left.prevent="stepLightbox(-1)"
            @keydown.right.prevent="stepLightbox(1)"
        >
            <template v-if="lightbox">
                <div class="relative bg-fl-black">
                    <img
                        :src="lightbox.url"
                        :alt="
                            $t('Vista previa con marca de agua · {source}', {
                                source: lightbox.source,
                            })
                        "
                        class="max-h-[75vh] w-full object-contain"
                    />
                    <button
                        v-if="buyable.length > 1"
                        type="button"
                        class="fl-focus-glow absolute top-1/2 left-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white transition-colors hover:bg-black/60"
                        :aria-label="$t('Foto anterior')"
                        @click="stepLightbox(-1)"
                    >
                        <ChevronLeft class="size-5" />
                    </button>
                    <button
                        v-if="buyable.length > 1"
                        type="button"
                        class="fl-focus-glow absolute top-1/2 right-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/40 text-white transition-colors hover:bg-black/60"
                        :aria-label="$t('Foto siguiente')"
                        @click="stepLightbox(1)"
                    >
                        <ChevronRight class="size-5" />
                    </button>
                </div>
                <div
                    class="flex flex-wrap items-center justify-between gap-3 p-4"
                >
                    <div class="min-w-0">
                        <DialogTitle class="truncate font-semibold">{{
                            lightbox.source
                        }}</DialogTitle>
                        <DialogDescription class="truncate">
                            {{ lightbox.event }} ·
                            {{ $t('vista previa con marca de agua') }}
                        </DialogDescription>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            v-if="!lightbox.owned"
                            class="rounded-full"
                            @click="toggleFromLightbox"
                        >
                            {{
                                selected.has(lightbox.uuid)
                                    ? $t('Quitar')
                                    : $t('Agregar · {price}', {
                                          price: money(
                                              lightbox.price_minor ?? 0,
                                          ),
                                      })
                            }}
                        </Button>
                        <DialogClose as-child>
                            <Button variant="outline" class="rounded-full">{{
                                $t('Cerrar')
                            }}</Button>
                        </DialogClose>
                    </div>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
