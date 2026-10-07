<script setup lang="ts">
/**
 * Administración → Fotografías: the photographers' review grid. Status
 * tabs with live counts, filters (event, photographer, upload date, bib),
 * server pagination (24 / 50 / 100 per page — never thousands at once),
 * multi-select with quick "first 10 / 50 / 100" and bulk approve / reject.
 * Sales figures and the athletes' own media overview below.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    Camera,
    Check,
    CheckCircle2,
    CheckSquare,
    Clock,
    Globe2,
    Lock,
    Maximize2,
    RotateCcw,
    Search,
    ShoppingBag,
    Square,
    Wallet,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { CONTENT_AREA_NAV } from '@/config/areaNav';

type Photo = {
    uuid: string;
    thumb_url: string;
    preview_url: string;
    photographer: string | null;
    photographer_status: string | null;
    event: string | null;
    price_minor: number;
    currency: string;
    bib_numbers: string[];
    rejection_reason: string | null;
    uploaded_at: string | null;
};

type Filters = {
    event_edition_id: number | null;
    photographer_id: number | null;
    from: string | null;
    to: string | null;
    bib: string | null;
    per_page: number;
};

const props = defineProps<{
    photos: {
        data: Photo[];
        links: PaginationLink[];
        total: number;
        from: number | null;
        to: number | null;
    };
    status: string;
    counts: Record<string, number>;
    filters: Filters;
    perPageOptions: number[];
    events: { id: number; name: string }[];
    photographers: { id: number; name: string }[];
    sales: {
        count: number;
        gross_minor: number;
        platform_fee_minor: number;
        net_minor: number;
    };
    stats: { images: number; videos: number; public: number; private: number };
}>();

const tabs = [
    { key: 'review', label: 'Por revisar', icon: Clock },
    { key: 'published', label: 'Publicadas', icon: CheckCircle2 },
    { key: 'rejected', label: 'Rechazadas', icon: XCircle },
] as const;

const draft = ref<Filters>({ ...props.filters });
watch(
    () => props.filters,
    (f) => (draft.value = { ...f }),
);

function visit(extra: Record<string, unknown> = {}) {
    const query = Object.fromEntries(
        Object.entries({
            status: props.status,
            ...draft.value,
            ...extra,
        }).filter(([, v]) => v !== null && v !== '' && v !== undefined),
    );
    router.get('/admin/photos', query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        onSuccess: () => (selected.value = new Set()),
    });
}

function clearFilters() {
    draft.value = {
        event_edition_id: null,
        photographer_id: null,
        from: null,
        to: null,
        bib: null,
        per_page: props.filters.per_page,
    };
    visit();
}

const activeFilters = computed(
    () =>
        [
            props.filters.event_edition_id,
            props.filters.photographer_id,
            props.filters.from,
            props.filters.to,
            props.filters.bib,
        ].filter(Boolean).length,
);

// --- Selection ---------------------------------------------------------

const selected = ref(new Set<string>());
const pageUuids = computed(() => props.photos.data.map((p) => p.uuid));
const allSelected = computed(
    () =>
        pageUuids.value.length > 0 &&
        pageUuids.value.every((u) => selected.value.has(u)),
);

function toggle(uuid: string) {
    const next = new Set(selected.value);

    if (next.has(uuid)) {
        next.delete(uuid);
    } else {
        next.add(uuid);
    }

    selected.value = next;
}

function selectFirst(n: number) {
    selected.value = new Set(pageUuids.value.slice(0, n));
}

function toggleAll() {
    selected.value = allSelected.value ? new Set() : new Set(pageUuids.value);
}

// --- Review actions ------------------------------------------------------

const rejecting = ref(false);
const reason = ref('');
const processing = ref(false);

function review(action: 'publish' | 'reject' | 'review') {
    if (!selected.value.size) {
        return;
    }

    processing.value = true;
    router.post(
        '/admin/photos/review',
        {
            uuids: [...selected.value],
            action,
            reason: action === 'reject' ? reason.value || null : null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = new Set();
                rejecting.value = false;
                reason.value = '';
            },
            onFinish: () => (processing.value = false),
        },
    );
}

const preview = ref<Photo | null>(null);

const shortDate = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleDateString('es-MX', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '';
</script>

<template>
    <Head title="Fotografías" />

    <div class="w-full space-y-6 p-4 pb-32 md:p-8 md:pb-32">
        <SecondaryNav :items="CONTENT_AREA_NAV" />

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                >
                    <Camera class="size-5" />
                </span>
                <div>
                    <h1 class="text-xl font-semibold">Fotografías</h1>
                    <p class="text-sm text-muted-foreground">
                        Revisa lo que suben los fotógrafos antes de publicarlo
                        en Mis Fotos.
                    </p>
                </div>
            </div>
            <dl class="flex flex-wrap gap-2 text-sm">
                <div
                    class="flex items-center gap-2 rounded-xl border border-border bg-card px-3 py-2"
                >
                    <ShoppingBag class="size-4 text-fl-gold-ink" />
                    <dt class="text-muted-foreground">Vendidas</dt>
                    <dd class="legacy-numeric font-semibold">
                        {{ sales.count }}
                    </dd>
                </div>
                <div
                    class="flex items-center gap-2 rounded-xl border border-border bg-card px-3 py-2"
                >
                    <Wallet class="size-4 text-fl-gold-ink" />
                    <dt class="text-muted-foreground">Bruto</dt>
                    <dd class="legacy-numeric font-semibold">
                        <Money :minor="sales.gross_minor" />
                    </dd>
                </div>
                <div
                    class="flex items-center gap-2 rounded-xl border border-border bg-card px-3 py-2"
                >
                    <dt class="text-muted-foreground">Comisión</dt>
                    <dd class="legacy-numeric font-semibold">
                        <Money :minor="sales.platform_fee_minor" />
                    </dd>
                </div>
            </dl>
        </div>

        <!-- Status tabs -->
        <div
            class="flex gap-1 overflow-x-auto rounded-full border border-border bg-card p-1"
            role="tablist"
        >
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="status === tab.key"
                class="inline-flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition-colors"
                :class="
                    status === tab.key
                        ? 'bg-foreground text-background'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="visit({ status: tab.key, page: null })"
            >
                <component :is="tab.icon" class="size-4" />
                {{ tab.label }}
                <span
                    class="legacy-numeric rounded-full px-2 py-0.5 text-xs"
                    :class="
                        status === tab.key
                            ? 'bg-background/20'
                            : 'bg-muted text-foreground'
                    "
                    >{{ counts[tab.key] ?? 0 }}</span
                >
            </button>
        </div>

        <!-- Filters -->
        <form
            class="fl-card grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[1.4fr_1.2fr_1fr_1fr_0.8fr_auto]"
            @submit.prevent="visit({ page: null })"
        >
            <FancySelect
                v-model="draft.event_edition_id"
                aria-label="Evento"
                placeholder="Todos los eventos"
                :options="[
                    { value: null, label: 'Todos los eventos' },
                    ...events.map((e) => ({ value: e.id, label: e.name })),
                ]"
            />
            <FancySelect
                v-model="draft.photographer_id"
                aria-label="Fotógrafo"
                placeholder="Todos los fotógrafos"
                :options="[
                    { value: null, label: 'Todos los fotógrafos' },
                    ...photographers.map((p) => ({
                        value: p.id,
                        label: p.name,
                    })),
                ]"
            />
            <DatePicker v-model="draft.from" placeholder="Subidas desde" />
            <DatePicker
                v-model="draft.to"
                placeholder="Hasta"
                :range-with="draft.from"
            />
            <Input
                :model-value="draft.bib ?? ''"
                @update:model-value="(v) => (draft.bib = String(v) || null)"
                inputmode="numeric"
                placeholder="Dorsal"
                aria-label="Dorsal"
            />
            <div class="flex gap-2">
                <Button type="submit" class="flex-1 rounded-full">
                    <Search class="size-4" /> Filtrar
                </Button>
                <Button
                    v-if="activeFilters"
                    type="button"
                    variant="outline"
                    class="rounded-full"
                    aria-label="Limpiar filtros"
                    @click="clearFilters"
                >
                    <X class="size-4" />
                </Button>
            </div>
        </form>

        <!-- Selection toolbar -->
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-1.5 font-medium hover:border-foreground/25"
                :aria-pressed="allSelected"
                @click="toggleAll"
            >
                <component
                    :is="allSelected ? CheckSquare : Square"
                    class="size-4"
                />
                {{ allSelected ? 'Quitar selección' : 'Seleccionar página' }}
            </button>
            <span class="text-muted-foreground">Seleccionar primeras:</span>
            <button
                v-for="n in [10, 50, 100]"
                :key="n"
                type="button"
                class="legacy-numeric rounded-full border border-border bg-card px-3 py-1.5 font-medium hover:border-foreground/25 disabled:opacity-40"
                :disabled="!photos.data.length"
                @click="selectFirst(n)"
            >
                {{ n }}
            </button>
            <span class="ml-auto flex items-center gap-2 text-muted-foreground">
                <span v-if="photos.total"
                    >{{ photos.from }}–{{ photos.to }} de
                    {{ photos.total }}</span
                >
                <FancySelect
                    :model-value="filters.per_page"
                    size="sm"
                    aria-label="Fotos por página"
                    :options="
                        perPageOptions.map((n) => ({
                            value: n,
                            label: `${n} por página`,
                        }))
                    "
                    @update:model-value="
                        (v) => visit({ per_page: v, page: null })
                    "
                />
            </span>
        </div>

        <!-- Grid -->
        <div
            v-if="photos.data.length"
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-8"
        >
            <article
                v-for="photo in photos.data"
                :key="photo.uuid"
                class="group relative overflow-hidden rounded-xl border bg-card transition-shadow"
                :class="
                    selected.has(photo.uuid)
                        ? 'border-fl-gold ring-2 ring-fl-gold'
                        : 'border-border'
                "
            >
                <button
                    type="button"
                    class="relative block aspect-[4/3] w-full bg-muted"
                    :aria-pressed="selected.has(photo.uuid)"
                    :aria-label="`Seleccionar foto de ${photo.event ?? 'evento'}`"
                    @click="toggle(photo.uuid)"
                >
                    <img
                        :src="photo.thumb_url"
                        alt=""
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover"
                    />
                    <span
                        class="absolute top-2 left-2 flex size-6 items-center justify-center rounded-md border-2 shadow-sm"
                        :class="
                            selected.has(photo.uuid)
                                ? 'border-fl-gold bg-fl-gold text-fl-black'
                                : 'border-white bg-black/30'
                        "
                    >
                        <Check v-if="selected.has(photo.uuid)" class="size-4" />
                    </span>
                </button>
                <button
                    type="button"
                    class="absolute top-2 right-2 flex size-7 items-center justify-center rounded-full bg-white/95 shadow-sm focus-visible:opacity-100 sm:opacity-0 sm:group-hover:opacity-100"
                    aria-label="Ver en grande"
                    @click="preview = photo"
                >
                    <Maximize2 class="size-3.5" />
                </button>
                <div class="space-y-0.5 p-2.5 text-xs">
                    <p class="truncate font-medium">
                        {{ photo.event ?? 'Sin evento' }}
                    </p>
                    <p class="truncate text-muted-foreground">
                        {{ photo.photographer ?? '—' }}
                        <span
                            v-if="photo.photographer_status === 'suspended'"
                            class="font-semibold text-red-700"
                            >· suspendido</span
                        >
                    </p>
                    <p class="flex items-center justify-between gap-2">
                        <span class="truncate text-muted-foreground">
                            {{
                                photo.bib_numbers.length
                                    ? `#${photo.bib_numbers.join(' #')}`
                                    : 'Sin dorsal'
                            }}
                        </span>
                        <Money
                            class="legacy-numeric font-semibold"
                            :minor="photo.price_minor"
                            :currency="photo.currency"
                        />
                    </p>
                    <p
                        v-if="photo.rejection_reason && status === 'rejected'"
                        class="line-clamp-2 text-red-700"
                    >
                        {{ photo.rejection_reason }}
                    </p>
                </div>
            </article>
        </div>
        <div
            v-else
            class="fl-card flex flex-col items-center gap-2 px-6 py-16 text-center"
        >
            <Camera class="size-8 text-muted-foreground" />
            <p class="font-medium">No hay fotos con estos filtros.</p>
            <p class="text-sm text-muted-foreground">
                Cambia el estado o limpia los filtros.
            </p>
        </div>

        <Pagination :links="photos.links" />

        <!-- Athletes' own media -->
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Fotos de atletas</p>
                <p class="legacy-numeric text-2xl font-semibold">
                    {{ stats.images }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Videos de atletas</p>
                <p class="legacy-numeric text-2xl font-semibold">
                    {{ stats.videos }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p
                    class="flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Globe2 class="size-3" /> Públicas
                </p>
                <p class="legacy-numeric text-2xl font-semibold">
                    {{ stats.public }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p
                    class="flex items-center gap-1 text-xs text-muted-foreground"
                >
                    <Lock class="size-3" /> Privadas
                </p>
                <p class="legacy-numeric text-2xl font-semibold">
                    {{ stats.private }}
                </p>
            </div>
        </section>
    </div>

    <!-- Sticky bulk bar: actions always visible, never hidden in a menu -->
    <div
        v-if="selected.size"
        class="fixed inset-x-3 bottom-20 z-40 mx-auto max-w-3xl rounded-2xl border border-border bg-card p-3 shadow-[0_20px_50px_-20px_rgb(23_23_20/0.45)] md:bottom-6"
    >
        <div v-if="!rejecting" class="flex flex-wrap items-center gap-2">
            <span class="legacy-numeric px-2 text-sm font-semibold"
                >{{ selected.size }} seleccionadas</span
            >
            <Button
                v-if="status !== 'published'"
                class="rounded-full bg-emerald-700 text-white hover:bg-emerald-800"
                :disabled="processing"
                @click="review('publish')"
            >
                <Check class="size-4" /> Aprobar seleccionadas
            </Button>
            <Button
                v-if="status !== 'rejected'"
                variant="outline"
                class="rounded-full border-red-200 text-red-700 hover:bg-red-50"
                :disabled="processing"
                @click="rejecting = true"
            >
                <X class="size-4" /> Rechazar seleccionadas
            </Button>
            <Button
                v-if="status !== 'review'"
                variant="outline"
                class="rounded-full"
                :disabled="processing"
                @click="review('review')"
            >
                <RotateCcw class="size-4" /> Volver a revisión
            </Button>
            <button
                type="button"
                class="ml-auto text-sm text-muted-foreground hover:text-foreground"
                @click="selected = new Set()"
            >
                Cancelar
            </button>
        </div>
        <form
            v-else
            class="flex flex-col gap-2 sm:flex-row"
            @submit.prevent="review('reject')"
        >
            <Input
                v-model="reason"
                maxlength="200"
                placeholder="Motivo (lo verá el fotógrafo)"
                class="flex-1"
                autofocus
            />
            <div class="flex gap-2">
                <Button
                    type="submit"
                    class="rounded-full bg-red-700 text-white hover:bg-red-800"
                    :disabled="processing"
                >
                    Rechazar {{ selected.size }}
                </Button>
                <Button type="button" variant="ghost" @click="rejecting = false"
                    >Atrás</Button
                >
            </div>
        </form>
    </div>

    <Dialog
        :open="preview !== null"
        @update:open="(v) => !v && (preview = null)"
    >
        <DialogContent class="max-w-4xl p-2 sm:p-3">
            <DialogTitle class="sr-only">Vista previa</DialogTitle>
            <template v-if="preview">
                <img
                    :src="preview.preview_url"
                    :alt="`Foto de ${preview.event ?? 'evento'}`"
                    class="max-h-[78vh] w-full rounded-lg object-contain"
                />
                <p class="px-2 pt-2 text-xs text-muted-foreground">
                    {{ preview.event }} · {{ preview.photographer }} ·
                    {{ shortDate(preview.uploaded_at) }}
                </p>
            </template>
        </DialogContent>
    </Dialog>
</template>
