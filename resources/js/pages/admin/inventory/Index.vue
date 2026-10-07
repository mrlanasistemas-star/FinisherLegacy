<script setup lang="ts">
/**
 * Administración → Inventario: one row per variant × location with the
 * product photo, a stock bar and a visible "Ajustar" action that opens the
 * adjustment dialog already filled in. Filters: search and stock state.
 * Everything comes from InventoryController (inventory_levels).
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    MapPin,
    Package,
    PackageX,
    Plus,
    Search,
    SlidersHorizontal,
    Warehouse,
} from '@lucide/vue';
import { ref } from 'vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { CATALOG_AREA_NAV } from '@/config/areaNav';

type LevelRow = {
    id: number;
    product_id: number;
    product_variant_id: number;
    inventory_location_id: number;
    product: string;
    image_url: string | null;
    variant: string;
    sku: string;
    location: string;
    quantity_on_hand: number;
    quantity_reserved: number;
    available: number;
};

const props = defineProps<{
    levels: { data: LevelRow[]; links: PaginationLink[]; total: number };
    filters: { q: string; estado: string | null };
    totals: { on_hand: number; reserved: number; low: number; out: number };
    lowThreshold: number;
    variants: { id: number; label: string }[];
    locations: { id: number; name: string }[];
}>();

const q = ref(props.filters.q);

function visit(params: Record<string, string | null>) {
    router.get(
        '/admin/inventory',
        Object.fromEntries(
            Object.entries({
                q: q.value || null,
                estado: props.filters.estado,
                ...params,
            }).filter(([, v]) => v),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function state(row: LevelRow) {
    if (row.available <= 0) {
        return { label: 'Agotado', class: 'bg-red-50 text-red-700' };
    }

    if (row.available <= props.lowThreshold) {
        return { label: 'Stock bajo', class: 'bg-amber-50 text-amber-700' };
    }

    return { label: 'Disponible', class: 'bg-emerald-50 text-emerald-700' };
}

const barWidth = (row: LevelRow) =>
    `${row.quantity_on_hand > 0 ? Math.max((Math.max(row.available, 0) / row.quantity_on_hand) * 100, 2) : 0}%`;

const dialogOpen = ref(false);
const form = useForm({
    product_variant_id: null as number | string | null,
    inventory_location_id: null as number | string | null,
    type: 'receive' as string | number | null,
    quantity: 0,
    notes: '',
});

function openAdjust(row?: LevelRow) {
    form.reset();
    form.clearErrors();

    if (row) {
        form.product_variant_id = row.product_variant_id;
        form.inventory_location_id = row.inventory_location_id;
    }

    dialogOpen.value = true;
}

function submit() {
    form.post('/admin/inventory/adjust', {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    });
}

const fmt = new Intl.NumberFormat('es-MX');
</script>

<template>
    <Head title="Inventario" />

    <div class="w-full space-y-6 p-4 md:p-8">
        <SecondaryNav :items="CATALOG_AREA_NAV" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="flex items-center gap-2 text-xl font-bold">
                <Warehouse class="size-5 text-fl-gold-ink" />
                Inventario
            </h1>
            <Button class="rounded-full" @click="openAdjust()">
                <Plus class="size-4" />
                Ajustar stock
            </Button>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">En existencia</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ fmt.format(totals.on_hand) }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Reservado</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ fmt.format(totals.reserved) }}
                </p>
            </div>
            <button
                type="button"
                class="fl-card p-4 text-left transition-colors hover:border-amber-300"
                :class="filters.estado === 'low' ? 'ring-2 ring-amber-300' : ''"
                @click="
                    visit({
                        estado: filters.estado === 'low' ? null : 'low',
                        page: null,
                    })
                "
            >
                <p class="flex items-center gap-1.5 text-xs text-amber-700">
                    <AlertTriangle class="size-3.5" /> Stock bajo (≤
                    {{ lowThreshold }})
                </p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ totals.low }}
                </p>
            </button>
            <button
                type="button"
                class="fl-card p-4 text-left transition-colors hover:border-red-300"
                :class="filters.estado === 'out' ? 'ring-2 ring-red-300' : ''"
                @click="
                    visit({
                        estado: filters.estado === 'out' ? null : 'out',
                        page: null,
                    })
                "
            >
                <p class="flex items-center gap-1.5 text-xs text-red-700">
                    <PackageX class="size-3.5" /> Agotados
                </p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ totals.out }}
                </p>
            </button>
        </div>

        <form class="relative max-w-xl" @submit.prevent="visit({ page: null })">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                v-model="q"
                class="pl-9"
                placeholder="Buscar producto, variante o SKU"
                aria-label="Buscar en inventario"
            />
        </form>

        <div
            v-if="levels.data.length"
            class="grid grid-cols-1 gap-3 md:grid-cols-2 2xl:grid-cols-3"
        >
            <article
                v-for="row in levels.data"
                :key="row.id"
                class="fl-card flex gap-4 p-4"
            >
                <Link
                    :href="`/admin/products/${row.product_id}`"
                    class="size-20 shrink-0 overflow-hidden rounded-xl border border-border bg-muted"
                >
                    <img
                        v-if="row.image_url"
                        :src="row.image_url"
                        :alt="row.product"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover"
                    />
                    <Package v-else class="m-6 size-8 text-muted-foreground" />
                </Link>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <Link
                                :href="`/admin/products/${row.product_id}`"
                                class="block truncate font-semibold hover:underline"
                                >{{ row.product }}</Link
                            >
                            <p class="truncate text-xs text-muted-foreground">
                                {{ row.variant }} ·
                                <span class="font-mono">{{ row.sku }}</span>
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="state(row).class"
                            >{{ state(row).label }}</span
                        >
                    </div>
                    <div
                        class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full rounded-full"
                            :class="
                                row.available <= 0
                                    ? 'bg-red-500'
                                    : row.available <= lowThreshold
                                      ? 'bg-amber-500'
                                      : 'bg-emerald-600'
                            "
                            :style="{ width: barWidth(row) }"
                        />
                    </div>
                    <div
                        class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span
                            ><b class="legacy-numeric text-foreground">{{
                                row.available
                            }}</b>
                            disponibles</span
                        >
                        <span>{{ row.quantity_on_hand }} en existencia</span>
                        <span>{{ row.quantity_reserved }} reservadas</span>
                        <span class="inline-flex items-center gap-1"
                            ><MapPin class="size-3" />{{ row.location }}</span
                        >
                        <Button
                            size="sm"
                            variant="outline"
                            class="ml-auto h-8 rounded-full"
                            @click="openAdjust(row)"
                        >
                            <SlidersHorizontal class="size-3.5" /> Ajustar
                        </Button>
                    </div>
                </div>
            </article>
        </div>
        <p
            v-else
            class="fl-card px-6 py-12 text-center text-sm text-muted-foreground"
        >
            No hay existencias con estos filtros.
        </p>

        <Pagination :links="levels.links" />

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Ajustar inventario</DialogTitle>
                    <DialogDescription>
                        Recepción suma unidades; Ajuste acepta negativos (merma,
                        conteo).
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label>Variante</Label>
                        <FancySelect
                            v-model="form.product_variant_id"
                            placeholder="Selecciona una variante"
                            :options="
                                variants.map((v) => ({
                                    value: v.id,
                                    label: v.label,
                                }))
                            "
                        />
                        <InputError :message="form.errors.product_variant_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Ubicación</Label>
                        <FancySelect
                            v-model="form.inventory_location_id"
                            placeholder="Selecciona una ubicación"
                            :options="
                                locations.map((l) => ({
                                    value: l.id,
                                    label: l.name,
                                }))
                            "
                        />
                        <InputError
                            :message="form.errors.inventory_location_id"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Tipo</Label>
                            <FancySelect
                                v-model="form.type"
                                :options="[
                                    { value: 'receive', label: 'Recepción' },
                                    {
                                        value: 'adjustment',
                                        label: 'Ajuste / merma',
                                    },
                                ]"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="inv-qty">Cantidad</Label>
                            <Input
                                id="inv-qty"
                                v-model.number="form.quantity"
                                type="number"
                            />
                            <InputError :message="form.errors.quantity" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="inv-notes">Motivo (opcional)</Label>
                        <Textarea
                            id="inv-notes"
                            v-model="form.notes"
                            rows="2"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full rounded-full sm:w-auto"
                            :disabled="form.processing"
                        >
                            Guardar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
