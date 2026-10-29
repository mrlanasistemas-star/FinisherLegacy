<script setup lang="ts">
/**
 * Comercio → Inventario (spec §12-§15). Table: PRODUCTO, VARIANTE, TALLA,
 * COLOR, STOCK, RESERVADO, DISPONIBLE, VENDIDO, ALERTA — cards on phones.
 * Matrix: apparel as colour × size. Any row/cell opens the safe-adjust
 * drawer (reason required, audited). The workspace tab bar comes from the
 * admin shell, not from this page.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Grid3x3,
    PackageX,
    Rows3,
    Search,
    SlidersHorizontal,
    Warehouse,
} from '@lucide/vue';
import { ref } from 'vue';
import InventoryMatrix from '@/components/commerce/InventoryMatrix.vue';
import type { MatrixCell } from '@/components/commerce/InventoryMatrix.vue';
import StockAdjustSheet from '@/components/commerce/StockAdjustSheet.vue';
import type { AdjustTarget } from '@/components/commerce/StockAdjustSheet.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Row = {
    variant_id: number;
    product_id: number;
    product: string;
    variant: string;
    label: string;
    sku: string;
    size: string | null;
    color: string | null;
    image_url: string | null;
    on_hand: number;
    reserved: number;
    available: number;
    sold: number;
    minimum: number;
    minimum_own: number | null;
    alert: 'out' | 'low' | 'ok';
    active: boolean;
};

type MatrixProduct = {
    product_id: number;
    name: string;
    type: string;
    image_url: string | null;
    sizes: string[];
    colors: string[];
    cells: Record<string, Record<string, MatrixCell>>;
    available: number;
};

type Paged<T> = { data: T[]; links: PaginationLink[]; total: number };

const props = defineProps<{
    view: 'table' | 'matrix';
    filters: { q: string; estado: string | null };
    counts: {
        total: number;
        low: number;
        out: number;
        attention: number;
        on_hand: number;
        reserved: number;
    };
    rows: Paged<Row> | null;
    matrix: Paged<MatrixProduct> | null;
    reasons: { value: string; label: string }[];
    defaultMinimum: number;
}>();

const q = ref(props.filters.q);

function visit(params: Record<string, string | null>) {
    router.get(
        '/admin/comercio/inventario',
        Object.fromEntries(
            Object.entries({
                q: q.value || null,
                estado: props.filters.estado,
                vista: props.view === 'matrix' ? 'matriz' : null,
                ...params,
            }).filter(([, v]) => v),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const stateFilters = [
    { key: null, label: 'Todo', count: () => props.counts.total },
    {
        key: 'attention',
        label: 'Necesita reposición',
        count: () => props.counts.attention,
    },
    { key: 'low', label: 'Bajo', count: () => props.counts.low },
    { key: 'out', label: 'Agotado', count: () => props.counts.out },
];

const alertUi = {
    out: { label: 'Agotado', class: 'bg-red-50 text-red-700 ring-red-200' },
    low: { label: 'Bajo', class: 'bg-amber-50 text-amber-800 ring-amber-200' },
    ok: {
        label: 'Suficiente',
        class: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    },
} as const;

const sheetOpen = ref(false);
const target = ref<AdjustTarget | null>(null);

function adjustRow(row: Row) {
    target.value = {
        variant_id: row.variant_id,
        label: row.label,
        sku: row.sku,
        available: row.available,
        on_hand: row.on_hand,
        reserved: row.reserved,
        minimum: row.minimum,
        minimum_own: row.minimum_own,
        image_url: row.image_url,
    };
    sheetOpen.value = true;
}

function adjustCell(cell: NonNullable<MatrixCell>, product: MatrixProduct) {
    target.value = {
        variant_id: cell.variant_id,
        label: cell.label,
        sku: cell.sku,
        available: cell.available,
        reserved: cell.reserved,
        minimum: cell.minimum,
        image_url: product.image_url,
    };
    sheetOpen.value = true;
}

const fmt = new Intl.NumberFormat('es-MX');
</script>

<template>
    <Head title="Inventario" />

    <div class="w-full space-y-5 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="fl-eyebrow">Comercio</p>
                <h1 class="flex items-center gap-2 text-xl font-bold">
                    <Warehouse class="size-5 text-fl-gold-ink" />
                    Inventario
                </h1>
            </div>
            <div
                class="inline-flex rounded-full bg-muted p-1"
                role="tablist"
                aria-label="Vista"
            >
                <button
                    type="button"
                    role="tab"
                    :aria-selected="view === 'table'"
                    class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 text-sm font-medium"
                    :class="
                        view === 'table'
                            ? 'bg-card shadow-sm'
                            : 'text-muted-foreground'
                    "
                    @click="visit({ vista: null })"
                >
                    <Rows3 class="size-4" /> Tabla
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="view === 'matrix'"
                    class="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 text-sm font-medium"
                    :class="
                        view === 'matrix'
                            ? 'bg-card shadow-sm'
                            : 'text-muted-foreground'
                    "
                    @click="visit({ vista: 'matriz' })"
                >
                    <Grid3x3 class="size-4" /> Tallas × color
                </button>
            </div>
        </header>

        <div
            v-if="counts.attention > 0"
            class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            <AlertTriangle class="size-4 shrink-0" />
            <p>
                <strong>{{ counts.attention }}</strong>
                {{
                    counts.attention === 1
                        ? 'variante necesita'
                        : 'variantes necesitan'
                }}
                reposición<template v-if="counts.out">
                    · {{ counts.out }} agotada{{
                        counts.out === 1 ? '' : 's'
                    }}</template
                >.
            </p>
            <button
                v-if="filters.estado !== 'attention'"
                type="button"
                class="ml-auto shrink-0 font-semibold underline-offset-4 hover:underline"
                @click="visit({ estado: 'attention', vista: null })"
            >
                Ver
            </button>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <form class="relative flex-1" @submit.prevent="visit({})">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    type="search"
                    placeholder="Buscar producto, variante o SKU"
                    aria-label="Buscar en inventario"
                    class="h-11 pl-9"
                />
            </form>
            <div
                v-if="view === 'table'"
                class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 lg:mx-0 lg:px-0 lg:pb-0"
            >
                <button
                    v-for="f in stateFilters"
                    :key="f.label"
                    type="button"
                    class="inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium transition"
                    :class="
                        filters.estado === f.key
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border bg-card text-foreground hover:border-foreground/40'
                    "
                    @click="visit({ estado: f.key })"
                >
                    {{ f.label }}
                    <span class="legacy-numeric text-xs opacity-70">{{
                        fmt.format(f.count())
                    }}</span>
                </button>
            </div>
        </div>

        <!-- TABLE (desktop) -->
        <template v-if="view === 'table' && rows">
            <div
                class="hidden overflow-hidden rounded-xl border border-border bg-card md:block"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border bg-muted/40 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                        >
                            <th class="px-4 py-2.5 font-semibold">Producto</th>
                            <th class="px-3 py-2.5 font-semibold">Variante</th>
                            <th class="px-3 py-2.5 font-semibold">Talla</th>
                            <th class="px-3 py-2.5 font-semibold">Color</th>
                            <th class="px-3 py-2.5 text-right font-semibold">
                                Stock
                            </th>
                            <th class="px-3 py-2.5 text-right font-semibold">
                                Reservado
                            </th>
                            <th class="px-3 py-2.5 text-right font-semibold">
                                Disponible
                            </th>
                            <th class="px-3 py-2.5 text-right font-semibold">
                                Vendido
                            </th>
                            <th class="px-3 py-2.5 font-semibold">Alerta</th>
                            <th class="px-3 py-2.5">
                                <span class="sr-only">Acciones</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows.data"
                            :key="row.variant_id"
                            class="border-b border-border last:border-0 hover:bg-muted/30"
                        >
                            <td class="px-4 py-2.5">
                                <Link
                                    :href="`/admin/products/${row.product_id}`"
                                    class="flex items-center gap-2.5 font-medium hover:underline"
                                >
                                    <img
                                        v-if="row.image_url"
                                        :src="row.image_url"
                                        alt=""
                                        loading="lazy"
                                        class="size-8 rounded-md object-cover"
                                    />
                                    <span
                                        v-else
                                        class="size-8 rounded-md bg-muted"
                                    />
                                    {{ row.product }}
                                </Link>
                            </td>
                            <td class="px-3 py-2.5">
                                {{ row.variant }}
                                <span
                                    class="block font-mono text-[11px] text-muted-foreground"
                                    >{{ row.sku }}</span
                                >
                            </td>
                            <td class="px-3 py-2.5">{{ row.size ?? '—' }}</td>
                            <td class="px-3 py-2.5">{{ row.color ?? '—' }}</td>
                            <td class="legacy-numeric px-3 py-2.5 text-right">
                                {{ row.on_hand }}
                            </td>
                            <td
                                class="legacy-numeric px-3 py-2.5 text-right text-muted-foreground"
                            >
                                {{ row.reserved }}
                            </td>
                            <td
                                class="legacy-numeric px-3 py-2.5 text-right font-semibold"
                            >
                                {{ row.available }}
                            </td>
                            <td
                                class="legacy-numeric px-3 py-2.5 text-right text-muted-foreground"
                            >
                                {{ row.sold }}
                            </td>
                            <td class="px-3 py-2.5">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                                    :class="alertUi[row.alert].class"
                                    :title="`Mínimo: ${row.minimum}`"
                                    >{{ alertUi[row.alert].label }}</span
                                >
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="rounded-full"
                                    @click="adjustRow(row)"
                                >
                                    <SlidersHorizontal class="size-3.5" />
                                    Ajustar
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Cards (phone) -->
            <ul class="space-y-2 md:hidden">
                <li
                    v-for="row in rows.data"
                    :key="`m-${row.variant_id}`"
                    class="rounded-xl border border-border bg-card p-3"
                >
                    <div class="flex items-start gap-3">
                        <img
                            v-if="row.image_url"
                            :src="row.image_url"
                            alt=""
                            loading="lazy"
                            class="size-12 rounded-lg object-cover"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                {{ row.product }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{
                                    [row.variant, row.size, row.color]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
                            :class="alertUi[row.alert].class"
                            >{{ alertUi[row.alert].label }}</span
                        >
                    </div>
                    <div class="mt-3 flex items-center justify-between gap-2">
                        <dl class="flex gap-4 text-xs text-muted-foreground">
                            <div>
                                <dt>Disp.</dt>
                                <dd
                                    class="legacy-numeric text-base font-semibold text-foreground"
                                >
                                    {{ row.available }}
                                </dd>
                            </div>
                            <div>
                                <dt>Res.</dt>
                                <dd class="legacy-numeric text-base">
                                    {{ row.reserved }}
                                </dd>
                            </div>
                            <div>
                                <dt>Vend.</dt>
                                <dd class="legacy-numeric text-base">
                                    {{ row.sold }}
                                </dd>
                            </div>
                        </dl>
                        <Button
                            size="sm"
                            class="h-11 rounded-full px-4"
                            @click="adjustRow(row)"
                            >Ajustar</Button
                        >
                    </div>
                </li>
            </ul>

            <div
                v-if="!rows.data.length"
                class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border py-14 text-center text-sm text-muted-foreground"
            >
                <PackageX class="size-8" />
                Nada que mostrar con estos filtros.
            </div>

            <Pagination :links="rows.links" />
        </template>

        <!-- MATRIX -->
        <template v-if="view === 'matrix' && matrix">
            <div class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="product in matrix.data"
                    :key="product.product_id"
                    class="rounded-xl border border-border bg-card p-4"
                >
                    <header class="mb-3 flex items-center gap-3">
                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            alt=""
                            loading="lazy"
                            class="size-10 rounded-lg object-cover"
                        />
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="`/admin/products/${product.product_id}`"
                                class="truncate font-semibold hover:underline"
                                >{{ product.name }}</Link
                            >
                            <p class="text-xs text-muted-foreground">
                                {{ product.available }} disponibles
                            </p>
                        </div>
                    </header>
                    <InventoryMatrix
                        :sizes="product.sizes"
                        :colors="product.colors"
                        :cells="product.cells"
                        interactive
                        @select="(cell) => adjustCell(cell, product)"
                    />
                </article>
            </div>
            <div
                v-if="!matrix.data.length"
                class="rounded-xl border border-dashed border-border py-14 text-center text-sm text-muted-foreground"
            >
                Sin productos con inventario.
            </div>
            <Pagination :links="matrix.links" />
        </template>

        <p class="text-xs text-muted-foreground">
            Stock = en almacén · Reservado = apartado por pedidos sin entregar ·
            Vendido = unidades en pedidos pagados. Mínimo de reposición por
            defecto: {{ defaultMinimum }}.
        </p>

        <StockAdjustSheet
            v-model:open="sheetOpen"
            :target="target"
            :reasons="reasons"
        />
    </div>
</template>
