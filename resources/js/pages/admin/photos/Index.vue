<script setup lang="ts">
/**
 * Administración → Fotografías: review board for photographers' photos
 * (Por revisar → Publicadas / Rechazadas) with multi-select approve /
 * reject, sales figures, and the athletes' own media overview.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    Camera,
    Check,
    CheckCircle2,
    Clock,
    Globe2,
    Lock,
    RotateCcw,
    ShoppingBag,
    Wallet,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import KanbanBoard from '@/components/admin/KanbanBoard.vue';
import type { KanbanLane } from '@/components/admin/KanbanBoard.vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import { Button } from '@/components/ui/button';
import { CONTENT_AREA_NAV } from '@/config/areaNav';

type PhotoCard = {
    uuid: string;
    thumb_url: string;
    preview_url: string;
    photographer: string | null;
    event: string | null;
    price_minor: number;
    currency: string;
    bib_numbers: string[];
    rejection_reason: string | null;
};

const props = defineProps<{
    board: { key: string; count: number; items: PhotoCard[] }[];
    sales: {
        count: number;
        gross_minor: number;
        platform_fee_minor: number;
        net_minor: number;
    };
    stats: { images: number; videos: number; public: number; private: number };
}>();

const money = (minor: number) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        maximumFractionDigits: 0,
    }).format(minor / 100);

const meta: Record<
    string,
    { title: string; icon: typeof Clock; accent: string }
> = {
    review: {
        title: 'Por revisar',
        icon: Clock,
        accent: 'bg-amber-100 text-amber-700',
    },
    published: {
        title: 'Publicadas',
        icon: CheckCircle2,
        accent: 'bg-emerald-100 text-emerald-700',
    },
    rejected: {
        title: 'Rechazadas',
        icon: XCircle,
        accent: 'bg-red-100 text-red-700',
    },
};

const lanes = computed<KanbanLane<PhotoCard>[]>(() =>
    props.board.map((lane) => ({
        ...meta[lane.key],
        key: lane.key,
        count: lane.count,
        items: lane.items,
    })),
);

const selected = ref<Set<string>>(new Set());
function toggle(uuid: string) {
    const next = new Set(selected.value);

    if (next.has(uuid)) {
        next.delete(uuid);
    } else {
        next.add(uuid);
    }

    selected.value = next;
}

function review(uuids: string[], action: 'publish' | 'reject' | 'review') {
    router.post(
        '/admin/photos/review',
        { uuids, action },
        {
            preserveScroll: true,
            onSuccess: () => (selected.value = new Set()),
        },
    );
}

const reviewUuids = computed(
    () =>
        props.board.find((l) => l.key === 'review')?.items.map((i) => i.uuid) ??
        [],
);
</script>

<template>
    <Head title="Fotografías" />

    <div class="w-full p-4 md:p-8">
        <SecondaryNav :items="CONTENT_AREA_NAV" />

        <div
            class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                    ><Camera class="size-5"
                /></span>
                <div>
                    <h1 class="text-xl font-semibold">Fotografías</h1>
                    <p class="text-sm text-muted-foreground">
                        Revisa lo que suben los fotógrafos antes de ponerlo a la
                        venta.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="selected.size"
                    class="rounded-full"
                    @click="review([...selected], 'publish')"
                    ><Check class="size-4" /> Publicar
                    {{ selected.size }}</Button
                >
                <Button
                    v-if="selected.size"
                    variant="outline"
                    class="rounded-full text-red-700"
                    @click="review([...selected], 'reject')"
                    ><X class="size-4" /> Rechazar {{ selected.size }}</Button
                >
                <Button
                    v-if="!selected.size && reviewUuids.length"
                    variant="outline"
                    class="rounded-full"
                    @click="review(reviewUuids, 'publish')"
                    ><CheckCircle2 class="size-4" /> Publicar todas las
                    pendientes</Button
                >
            </div>
        </div>

        <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="fl-card p-4">
                <ShoppingBag class="size-4 text-sky-700" />
                <p class="legacy-numeric mt-2 text-2xl font-semibold">
                    {{ sales.count }}
                </p>
                <p class="text-xs text-muted-foreground">Fotos vendidas</p>
            </div>
            <div class="fl-card p-4">
                <Wallet class="size-4 text-emerald-700" />
                <p class="legacy-numeric mt-2 text-2xl font-semibold">
                    {{ money(sales.gross_minor) }}
                </p>
                <p class="text-xs text-muted-foreground">Ventas</p>
            </div>
            <div class="fl-card p-4">
                <Globe2 class="size-4 text-fl-gold-ink" />
                <p class="legacy-numeric mt-2 text-2xl font-semibold">
                    {{ money(sales.platform_fee_minor) }}
                </p>
                <p class="text-xs text-muted-foreground">Comisión Finisher</p>
            </div>
            <div class="fl-card p-4">
                <Lock class="size-4 text-muted-foreground" />
                <p class="legacy-numeric mt-2 text-2xl font-semibold">
                    {{ stats.images }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Fotos subidas por atletas
                </p>
            </div>
        </div>

        <KanbanBoard :lanes="lanes" :item-key="(p) => p.uuid">
            <template #card="{ item: photo, lane }">
                <article
                    class="overflow-hidden rounded-xl border bg-card transition-colors"
                    :class="
                        selected.has(photo.uuid)
                            ? 'border-fl-gold ring-2 ring-fl-gold/30'
                            : 'border-border'
                    "
                >
                    <button
                        type="button"
                        class="relative block w-full"
                        :aria-pressed="selected.has(photo.uuid)"
                        @click="toggle(photo.uuid)"
                    >
                        <img
                            :src="photo.thumb_url"
                            alt=""
                            loading="lazy"
                            class="aspect-[4/3] w-full object-cover"
                        />
                        <span
                            class="absolute top-2 right-2 flex size-6 items-center justify-center rounded-full border-2 border-white shadow"
                            :class="
                                selected.has(photo.uuid)
                                    ? 'bg-fl-gold text-fl-black'
                                    : 'bg-black/30'
                            "
                        >
                            <Check
                                v-if="selected.has(photo.uuid)"
                                class="size-3.5"
                            />
                        </span>
                    </button>
                    <div class="space-y-1.5 p-3 text-xs">
                        <p class="truncate font-semibold">
                            {{ photo.photographer }}
                        </p>
                        <p class="truncate text-muted-foreground">
                            {{ photo.event }} · {{ money(photo.price_minor) }}
                        </p>
                        <p class="flex flex-wrap gap-1">
                            <span
                                v-for="bib in photo.bib_numbers"
                                :key="bib"
                                class="rounded-full bg-muted px-1.5 font-mono"
                                >#{{ bib }}</span
                            >
                        </p>
                        <p v-if="photo.rejection_reason" class="text-red-700">
                            {{ photo.rejection_reason }}
                        </p>
                        <div class="flex gap-1 pt-1">
                            <Button
                                v-if="lane.key !== 'published'"
                                size="sm"
                                class="h-7 flex-1 rounded-full text-xs"
                                @click="review([photo.uuid], 'publish')"
                                ><Check class="size-3" /> Publicar</Button
                            >
                            <Button
                                v-if="lane.key !== 'rejected'"
                                size="sm"
                                variant="outline"
                                class="h-7 flex-1 rounded-full text-xs text-red-700"
                                @click="review([photo.uuid], 'reject')"
                                ><X class="size-3" /> Rechazar</Button
                            >
                            <Button
                                v-if="lane.key !== 'review'"
                                size="sm"
                                variant="ghost"
                                class="h-7 rounded-full text-xs"
                                aria-label="Regresar a revisión"
                                @click="review([photo.uuid], 'review')"
                                ><RotateCcw class="size-3"
                            /></Button>
                        </div>
                    </div>
                </article>
            </template>
        </KanbanBoard>
    </div>
</template>
