<script setup lang="ts">
/**
 * "Encuentra tu momento." — search an event + bib across photographers'
 * photos (watermarked preview, price) and athletes' public photos; pick
 * the ones you want and buy them (a regular Order paid with the existing
 * Stripe flow). Bought photos appear under "Compradas" with a
 * full-resolution download. Selfie search is a future, optional feature.
 */
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Camera,
    Check,
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
import FancySelect from '@/components/forms/FancySelect.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login, register } from '@/routes';
import { index as photosIndex } from '@/routes/photos';

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
    results: Photo[];
    purchased: Purchased[] | null;
    mine: Photo[] | null;
    purchase: { available: boolean; currency: string };
}>();

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
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.purchase.currency || 'MXN',
    }).format(minor / 100);

const forSale = computed(() => props.results.filter((p) => p.kind === 'pro'));
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
    forSale.value.filter((p) => selected.value.has(p.uuid)),
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
        '/fotos/comprar',
        { photos: [...selected.value] },
        { onFinish: () => (buying.value = false) },
    );
}

const lightbox = ref<Photo | null>(null);

function toggleFromLightbox() {
    if (lightbox.value) {
        toggle(lightbox.value);
    }

    lightbox.value = null;
}
</script>

<template>
    <SeoHead
        title="Fotos"
        description="Busca tus fotografías de evento por número de participante, cómpralas en alta resolución y revive la emoción de cada carrera."
    />

    <section class="fl-container pt-12 pb-8 sm:pt-16">
        <div
            class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    Fotografías
                </p>
                <h1 class="fl-display mt-5 text-5xl sm:text-6xl lg:text-7xl">
                    Encuentra tu momento.
                </h1>
                <p class="mt-4 max-w-xl text-lg text-muted-foreground">
                    Busca tus fotografías y revive la emoción de cada evento.
                </p>
            </div>
            <Link
                href="/fotografos"
                class="inline-flex items-center gap-2 self-start rounded-full border border-border bg-card px-4 py-2 text-sm font-medium hover:border-foreground/25 lg:self-auto"
            >
                <Camera class="size-4 text-fl-gold-ink" />
                ¿Eres fotógrafo? Vende tus fotos
            </Link>
        </div>

        <div class="mt-10 grid gap-4 lg:grid-cols-12">
            <form
                class="fl-card grid gap-4 p-5 sm:grid-cols-[1fr_180px_auto] sm:items-end sm:p-6 lg:col-span-8"
                role="search"
                @submit.prevent="search"
            >
                <div class="grid gap-2">
                    <Label for="photo-event">Evento</Label>
                    <FancySelect
                        id="photo-event"
                        v-model="eventId"
                        :options="eventOptions"
                        placeholder="Selecciona un evento"
                        class="h-11"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="photo-bib">Número de participante</Label>
                    <Input
                        id="photo-bib"
                        v-model="bib"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="Ej. 482"
                        class="legacy-numeric h-11"
                    />
                </div>
                <Button
                    type="submit"
                    class="h-11 rounded-full px-6"
                    :disabled="!eventId || !bib.trim() || searching"
                >
                    <Search class="size-4" />
                    Buscar
                </Button>
            </form>
            <div
                class="flex items-start gap-4 rounded-xl border border-dashed border-foreground/20 bg-card p-5 sm:p-6 lg:col-span-4"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                >
                    <ScanFace class="size-5" />
                </span>
                <div>
                    <p class="flex flex-wrap items-center gap-2 font-semibold">
                        Buscar con mi selfie
                        <span
                            class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold tracking-wide text-muted-foreground uppercase"
                            >Opcional · Próximamente</span
                        >
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        El reconocimiento facial será una integración futura y
                        siempre con tu consentimiento.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="fl-container pb-20">
        <div class="grid gap-8 lg:grid-cols-12">
            <div class="min-w-0 space-y-12 lg:col-span-8">
                <template v-if="searched">
                    <div>
                        <h2 class="flex items-center gap-2 font-serif text-2xl">
                            <Camera class="size-5 text-fl-gold-ink" />
                            Fotos profesionales
                            <span class="text-base text-muted-foreground">{{
                                forSale.length
                            }}</span>
                        </h2>
                        <div
                            v-if="forSale.length"
                            class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3"
                        >
                            <article
                                v-for="photo in forSale"
                                :key="photo.uuid"
                                class="group relative overflow-hidden rounded-xl border-2 bg-card transition-colors"
                                :class="
                                    selected.has(photo.uuid)
                                        ? 'border-foreground'
                                        : 'border-transparent'
                                "
                            >
                                <button
                                    type="button"
                                    class="block w-full"
                                    :aria-label="`Ver foto de ${photo.source}`"
                                    @click="lightbox = photo"
                                >
                                    <img
                                        :src="photo.thumb_url"
                                        :alt="`Foto de ${photo.event ?? 'evento'} por ${photo.source}`"
                                        loading="lazy"
                                        decoding="async"
                                        class="aspect-[4/5] w-full bg-muted object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                    />
                                </button>
                                <button
                                    v-if="!photo.owned"
                                    type="button"
                                    class="absolute top-2 right-2 flex size-8 items-center justify-center rounded-full border-2 border-white shadow transition-colors"
                                    :class="
                                        selected.has(photo.uuid)
                                            ? 'bg-foreground text-background'
                                            : 'bg-black/30 text-white hover:bg-black/50'
                                    "
                                    :aria-pressed="selected.has(photo.uuid)"
                                    :aria-label="
                                        selected.has(photo.uuid)
                                            ? 'Quitar de la selección'
                                            : 'Agregar a la selección'
                                    "
                                    @click="toggle(photo)"
                                >
                                    <Check
                                        v-if="selected.has(photo.uuid)"
                                        class="size-4"
                                    />
                                    <ShoppingBag v-else class="size-3.5" />
                                </button>
                                <span
                                    v-else
                                    class="absolute top-2 right-2 rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-semibold text-white"
                                    >Ya es tuya</span
                                >
                                <div
                                    class="flex items-center justify-between gap-2 px-3 py-2.5"
                                >
                                    <span class="min-w-0">
                                        <span
                                            class="flex items-center gap-1 truncate text-xs font-semibold"
                                            ><Camera
                                                class="size-3 shrink-0 text-muted-foreground"
                                            />{{ photo.source }}</span
                                        >
                                        <span
                                            class="block truncate text-[11px] text-muted-foreground"
                                            >{{ photo.event }}</span
                                        >
                                    </span>
                                    <span
                                        class="shrink-0 text-sm font-semibold"
                                        >{{
                                            money(photo.price_minor ?? 0)
                                        }}</span
                                    >
                                </div>
                            </article>
                        </div>
                        <p
                            v-else
                            class="mt-4 flex items-start gap-3 rounded-xl border border-dashed border-foreground/15 bg-card p-5 text-sm text-muted-foreground"
                        >
                            <ImageOff class="size-5 shrink-0" />
                            Aún no hay fotos de fotógrafos con ese número.
                            Vuelve pronto: se publican después del evento.
                        </p>
                    </div>

                    <div v-if="athletePhotos.length">
                        <h2 class="flex items-center gap-2 font-serif text-2xl">
                            <UserRound class="size-5 text-fl-gold-ink" />
                            Compartidas por atletas
                        </h2>
                        <div class="mt-5 grid grid-cols-3 gap-2 sm:grid-cols-4">
                            <img
                                v-for="photo in athletePhotos"
                                :key="photo.uuid"
                                :src="photo.thumb_url"
                                :alt="`Foto de ${photo.event ?? 'evento'}`"
                                loading="lazy"
                                class="aspect-square w-full rounded-lg bg-muted object-cover"
                            />
                        </div>
                    </div>
                </template>

                <div v-if="purchased && purchased.length" id="compradas">
                    <h2 class="flex items-center gap-2 font-serif text-2xl">
                        <Download class="size-5 text-fl-gold-ink" />
                        Compradas
                        <span class="text-base text-muted-foreground">{{
                            purchased.length
                        }}</span>
                    </h2>
                    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <article
                            v-for="item in purchased"
                            :key="item.uuid"
                            class="overflow-hidden rounded-xl border border-border bg-card"
                        >
                            <img
                                :src="item.thumb_url"
                                :alt="`Foto de ${item.event}`"
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
                                    Alta resolución
                                </a>
                            </div>
                        </article>
                    </div>
                </div>

                <div v-if="mine !== null" id="mis-fotos">
                    <h2 class="font-serif text-2xl">
                        Mis fotos subidas
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
                                :alt="`Mi foto en ${photo.event ?? 'evento'}`"
                                loading="lazy"
                                class="aspect-square w-full rounded-lg bg-muted object-cover"
                            />
                            <span
                                v-if="!photo.is_public"
                                class="absolute top-1.5 left-1.5 inline-flex items-center gap-1 rounded-full bg-white/90 px-1.5 py-0.5 text-[10px]"
                                ><Lock class="size-3" /> Privada</span
                            >
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-muted-foreground">
                        Sube tus fotos desde el detalle de cada evento en
                        <Link
                            href="/dashboard"
                            class="font-medium text-foreground underline underline-offset-4"
                            >Mi Legado</Link
                        >.
                    </p>
                </div>

                <div
                    v-if="isGuest && !searched"
                    class="rounded-xl border border-border bg-card p-6"
                >
                    <p class="font-semibold">¿Ya tienes cuenta?</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Inicia sesión para comprar y descargar tus fotos en alta
                        resolución.
                    </p>
                    <div class="mt-4 flex gap-2">
                        <Button as-child class="rounded-full">
                            <Link :href="login()">Iniciar sesión</Link>
                        </Button>
                        <Button as-child variant="outline" class="rounded-full">
                            <Link :href="register()">Crear mi perfil</Link>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Selection -->
            <aside class="lg:col-span-4">
                <div class="fl-card p-6 lg:sticky lg:top-24">
                    <h2 class="font-serif text-2xl">Tu selección</h2>
                    <div
                        v-if="selectedPhotos.length"
                        class="mt-4 flex -space-x-3"
                    >
                        <img
                            v-for="photo in selectedPhotos.slice(0, 6)"
                            :key="photo.uuid"
                            :src="photo.thumb_url"
                            alt=""
                            class="size-12 rounded-lg border-2 border-card object-cover"
                        />
                    </div>
                    <dl class="mt-5 space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Fotografías</dt>
                            <dd class="legacy-numeric font-semibold">
                                {{ selectedPhotos.length }}
                            </dd>
                        </div>
                        <div
                            class="flex justify-between border-t border-border pt-3"
                        >
                            <dt class="font-medium">Total</dt>
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
                            Licencia de uso personal
                        </li>
                        <li class="flex items-center gap-2.5">
                            <Sparkles class="size-4 text-fl-gold-ink" />
                            Alta resolución, sin marca de agua
                        </li>
                        <li class="flex items-center gap-2.5">
                            <Download class="size-4 text-fl-gold-ink" />
                            Descarga inmediata al pagar
                        </li>
                    </ul>
                    <Button
                        v-if="isGuest"
                        as-child
                        class="mt-6 h-11 w-full rounded-full"
                    >
                        <Link :href="login()">Inicia sesión para comprar</Link>
                    </Button>
                    <Button
                        v-else
                        class="mt-6 h-11 w-full rounded-full"
                        :disabled="!selectedPhotos.length || buying"
                        @click="buy"
                    >
                        <ShoppingBag class="size-4" />
                        {{ buying ? 'Preparando…' : 'Comprar selección' }}
                    </Button>
                    <p class="mt-3 text-center text-xs text-muted-foreground">
                        Pago seguro con tarjeta. Cada fotógrafo recibe su parte
                        de la venta.
                    </p>
                </div>
            </aside>
        </div>
    </section>

    <!-- Lightbox -->
    <div
        v-if="lightbox"
        class="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 p-4"
        role="dialog"
        aria-modal="true"
        @click.self="lightbox = null"
    >
        <div
            class="max-h-full w-full max-w-4xl overflow-hidden rounded-2xl bg-card"
        >
            <img
                :src="lightbox.url"
                alt=""
                class="max-h-[75vh] w-full bg-black object-contain"
            />
            <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div>
                    <p class="font-semibold">{{ lightbox.source }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ lightbox.event }} · vista previa con marca de agua
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        v-if="!lightbox.owned"
                        class="rounded-full"
                        @click="toggleFromLightbox"
                    >
                        {{
                            selected.has(lightbox.uuid)
                                ? 'Quitar'
                                : `Agregar · ${money(lightbox.price_minor ?? 0)}`
                        }}
                    </Button>
                    <Button
                        variant="outline"
                        class="rounded-full"
                        @click="lightbox = null"
                        >Cerrar</Button
                    >
                </div>
            </div>
        </div>
    </div>
</template>
