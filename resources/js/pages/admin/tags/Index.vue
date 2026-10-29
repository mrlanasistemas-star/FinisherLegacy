<script setup lang="ts">
/**
 * Legacy → Tags: one row per physical Finisher Legacy Tag. Search accepts
 * a serial, a printed code (any format), a scanned /t/… URL, an order
 * number or the owner's email. Every filter lives in the URL.
 */
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    RotateCcw,
    ScanLine,
    SlidersHorizontal,
    Tag,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Admin/Catalog/LegacyTagController';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import EmptyState from '@/components/admin/catalog/EmptyState.vue';
import TableSkeleton from '@/components/admin/catalog/TableSkeleton.vue';
import TagStatusBadge from '@/components/admin/catalog/TagStatusBadge.vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import type { FancyOption } from '@/components/forms/FancySelect.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { useAdminFilters } from '@/composables/useAdminFilters';
import { shortDate } from '@/lib/datetime';
import { TAG_STATUS_ORDER, tagStatusLabel } from '@/lib/tagStatus';

type Row = {
    uuid: string;
    serial: string;
    display_code: string;
    status: string;
    sku: string | null;
    design: string | null;
    location: string | null;
    channel: string | null;
    ally: string | null;
    order: { uuid: string; number: string } | null;
    owner: { id: number; name: string | null; email: string | null } | null;
    activated_at: string | null;
};

type Filters = {
    q: string | null;
    status: string | null;
    variant: number | null;
    collection: number | null;
    location: number | null;
    lot: number | null;
    channel: string | null;
    ally: number | null;
    activated: string | null;
    date_field: string;
    from: string | null;
    to: string | null;
    sort: string;
    dir: string;
};

const props = defineProps<{
    tags: { data: Row[]; links: PaginationLink[]; total: number };
    filters: Filters;
    counts: Record<string, number>;
    options: {
        statuses: FancyOption[];
        channels: FancyOption[];
        variants: FancyOption[];
        collections: FancyOption[];
        locations: FancyOption[];
        lots: FancyOption[];
        allies: FancyOption[];
    };
}>();

const { filters, loading, apply, applyDebounced, sortBy } = useAdminFilters(
    () => index().url,
    { ...props.filters },
);

const advanced = [
    'variant',
    'collection',
    'location',
    'lot',
    'channel',
    'ally',
    'activated',
    'from',
    'to',
] as const;
const activeAdvanced = computed(
    () => advanced.filter((k) => props.filters[k] !== null).length,
);
const showAdvanced = ref(activeAdvanced.value > 0);

function clearAll() {
    apply({
        q: null,
        status: null,
        variant: null,
        collection: null,
        location: null,
        lot: null,
        channel: null,
        ally: null,
        activated: null,
        from: null,
        to: null,
    });
}

const total = computed(() =>
    Object.values(props.counts).reduce((a, b) => a + b, 0),
);

const withAll = (label: string, list: FancyOption[]) => [
    { value: null, label },
    ...list,
];

const dateFields = [
    { value: 'sold_at', label: 'Fecha de venta' },
    { value: 'activated_at', label: 'Fecha de activación' },
    { value: 'received_at', label: 'Fecha de recepción' },
    { value: 'created_at', label: 'Fecha de creación' },
];

const sortable = [
    { key: 'serial', label: 'Serie' },
    { key: 'status', label: 'Estado' },
    { key: 'activated_at', label: 'Activado' },
];
</script>

<template>
    <Head title="Tags" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <CatalogHeader
            eyebrow="Legacy"
            title="Tags"
            :description="`Registro por pieza · ${total.toLocaleString('es-MX')} piezas`"
            :icon="Tag"
        />

        <div
            class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:flex-wrap md:px-0"
        >
            <button
                type="button"
                class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium transition"
                :class="
                    !filters.status
                        ? 'border-foreground bg-foreground text-background'
                        : 'border-border bg-card hover:border-foreground/40'
                "
                @click="apply({ status: null })"
            >
                Todos
            </button>
            <button
                v-for="s in TAG_STATUS_ORDER"
                :key="s"
                type="button"
                class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium transition"
                :class="
                    filters.status === s
                        ? 'border-foreground bg-foreground text-background'
                        : 'border-border bg-card hover:border-foreground/40'
                "
                @click="apply({ status: s })"
            >
                {{ tagStatusLabel(s) }}
                <span class="legacy-numeric text-xs opacity-70">{{
                    (counts[s] ?? 0).toLocaleString('es-MX')
                }}</span>
            </button>
        </div>

        <div class="mb-3 flex flex-col gap-2 sm:flex-row">
            <div class="relative flex-1">
                <ScanLine
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="filters.q"
                    type="search"
                    placeholder="Serie, código, URL escaneada, pedido o correo del dueño…"
                    aria-label="Buscar tag"
                    class="h-9 w-full rounded-full border border-input bg-card pr-4 pl-10 text-sm outline-none hover:border-foreground/25 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/15"
                    @input="applyDebounced"
                />
            </div>
            <Button
                variant="outline"
                class="h-9 rounded-full"
                :aria-expanded="showAdvanced"
                @click="showAdvanced = !showAdvanced"
            >
                <SlidersHorizontal class="size-4" /> Filtros
                <span
                    v-if="activeAdvanced"
                    class="rounded-full bg-fl-gold px-1.5 text-xs text-white"
                    >{{ activeAdvanced }}</span
                >
                <ChevronDown
                    class="size-4 transition"
                    :class="showAdvanced ? 'rotate-180' : ''"
                />
            </Button>
            <Button
                v-if="activeAdvanced || filters.q || filters.status"
                variant="ghost"
                class="h-9 rounded-full"
                @click="clearAll"
            >
                <RotateCcw class="size-4" /> Limpiar
            </Button>
        </div>

        <div
            v-if="showAdvanced"
            class="mb-5 grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <FancySelect
                :model-value="filters.variant"
                :options="withAll('Todos los SKU', options.variants)"
                aria-label="SKU"
                @update:model-value="
                    (v) => apply({ variant: (v as number) ?? null })
                "
            />
            <FancySelect
                :model-value="filters.collection"
                :options="withAll('Todas las colecciones', options.collections)"
                aria-label="Colección"
                @update:model-value="
                    (v) => apply({ collection: (v as number) ?? null })
                "
            />
            <FancySelect
                :model-value="filters.location"
                :options="withAll('Todas las ubicaciones', options.locations)"
                aria-label="Ubicación"
                @update:model-value="
                    (v) => apply({ location: (v as number) ?? null })
                "
            />
            <FancySelect
                :model-value="filters.lot"
                :options="withAll('Todos los lotes', options.lots)"
                aria-label="Lote"
                @update:model-value="
                    (v) => apply({ lot: (v as number) ?? null })
                "
            />
            <FancySelect
                :model-value="filters.channel"
                :options="withAll('Todos los canales', options.channels)"
                aria-label="Canal"
                @update:model-value="
                    (v) => apply({ channel: (v as string) ?? null })
                "
            />
            <FancySelect
                :model-value="filters.ally"
                :options="withAll('Todos los aliados', options.allies)"
                aria-label="Aliado"
                @update:model-value="
                    (v) => apply({ ally: (v as number) ?? null })
                "
            />
            <FancySelect
                :model-value="filters.activated"
                :options="[
                    { value: null, label: 'Activados y sin activar' },
                    { value: 'si', label: 'Solo activados' },
                    { value: 'no', label: 'Sin activar' },
                ]"
                aria-label="Activación"
                @update:model-value="
                    (v) => apply({ activated: (v as string) ?? null })
                "
            />
            <FancySelect
                :model-value="filters.date_field"
                :options="dateFields"
                aria-label="Campo de fecha"
                @update:model-value="
                    (v) => apply({ date_field: String(v ?? 'sold_at') })
                "
            />
            <DatePicker
                :model-value="filters.from"
                placeholder="Desde"
                :range-with="filters.to"
                @update:model-value="(v) => apply({ from: v })"
            />
            <DatePicker
                :model-value="filters.to"
                placeholder="Hasta"
                :range-with="filters.from"
                @update:model-value="(v) => apply({ to: v })"
            />
        </div>

        <TableSkeleton v-if="loading" :rows="10" />

        <template v-else-if="tags.data.length">
            <p class="mb-2 text-xs text-muted-foreground">
                {{ tags.total.toLocaleString('es-MX') }} resultados
            </p>
            <div
                class="hidden overflow-x-auto rounded-xl border border-border bg-card lg:block"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border bg-muted/40 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                        >
                            <th
                                v-for="col in [
                                    { key: 'serial', label: 'Serie / código' },
                                    { key: null, label: 'Diseño / SKU' },
                                    { key: 'status', label: 'Estado' },
                                    { key: null, label: 'Ubicación' },
                                    { key: null, label: 'Canal' },
                                    { key: null, label: 'Pedido' },
                                    { key: null, label: 'Dueño' },
                                    { key: 'activated_at', label: 'Activado' },
                                ]"
                                :key="col.label"
                                class="px-3 py-2.5 font-semibold first:pl-4"
                                :aria-sort="
                                    col.key && filters.sort === col.key
                                        ? filters.dir === 'asc'
                                            ? 'ascending'
                                            : 'descending'
                                        : undefined
                                "
                            >
                                <button
                                    v-if="
                                        col.key &&
                                        sortable.some((s) => s.key === col.key)
                                    "
                                    type="button"
                                    class="inline-flex items-center gap-1 uppercase hover:text-foreground"
                                    @click="sortBy(col.key)"
                                >
                                    {{ col.label }}
                                    <ArrowUp
                                        v-if="
                                            filters.sort === col.key &&
                                            filters.dir === 'asc'
                                        "
                                        class="size-3"
                                    />
                                    <ArrowDown
                                        v-else-if="filters.sort === col.key"
                                        class="size-3"
                                    />
                                </button>
                                <template v-else>{{ col.label }}</template>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="t in tags.data"
                            :key="t.uuid"
                            class="border-b border-border last:border-0 hover:bg-muted/30"
                        >
                            <td class="py-2.5 pr-3 pl-4">
                                <Link
                                    :href="show(t.uuid).url"
                                    class="font-mono text-xs font-semibold hover:underline"
                                    >{{ t.serial }}</Link
                                >
                                <span
                                    class="block font-mono text-[11px] text-muted-foreground"
                                    >{{ t.display_code }}</span
                                >
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="block max-w-48 truncate">{{
                                    t.design ?? '—'
                                }}</span>
                                <span
                                    class="font-mono text-[11px] text-muted-foreground"
                                    >{{ t.sku }}</span
                                >
                            </td>
                            <td class="px-3 py-2.5">
                                <TagStatusBadge :status="t.status" />
                            </td>
                            <td class="px-3 py-2.5">{{ t.location ?? '—' }}</td>
                            <td class="px-3 py-2.5">
                                {{ t.channel ?? '—' }}
                                <span
                                    v-if="t.ally"
                                    class="block text-xs text-muted-foreground"
                                    >{{ t.ally }}</span
                                >
                            </td>
                            <td class="px-3 py-2.5">
                                <Link
                                    v-if="t.order"
                                    :href="`/admin/orders/${t.order.uuid}`"
                                    class="font-mono text-xs hover:underline"
                                    >{{ t.order.number }}</Link
                                >
                                <template v-else>—</template>
                            </td>
                            <td class="px-3 py-2.5">
                                <Link
                                    v-if="t.owner"
                                    :href="`/admin/athletes/${t.owner.id}`"
                                    class="hover:underline"
                                    >{{ t.owner.name ?? t.owner.email }}</Link
                                >
                                <template v-else>—</template>
                            </td>
                            <td
                                class="px-3 py-2.5 text-xs text-muted-foreground"
                            >
                                {{
                                    t.activated_at
                                        ? shortDate(t.activated_at)
                                        : '—'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="space-y-2 lg:hidden">
                <li v-for="t in tags.data" :key="`m-${t.uuid}`">
                    <Link
                        :href="show(t.uuid).url"
                        class="block rounded-xl border border-border bg-card p-3"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-semibold">
                                    {{ t.serial }}
                                </p>
                                <p
                                    class="font-mono text-xs text-muted-foreground"
                                >
                                    {{ t.display_code }}
                                </p>
                            </div>
                            <TagStatusBadge :status="t.status" />
                        </div>
                        <p class="mt-2 truncate text-xs text-muted-foreground">
                            {{
                                [t.design, t.location, t.channel, t.owner?.name]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </p>
                    </Link>
                </li>
            </ul>
        </template>

        <EmptyState
            v-else
            :icon="Tag"
            title="Ninguna pieza coincide"
            text="Revisa el código o la serie, o quita filtros."
        />

        <div class="mt-6"><Pagination :links="tags.links" /></div>
    </div>
</template>
