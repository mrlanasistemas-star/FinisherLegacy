<script setup lang="ts">
/**
 * One location: what is here by SKU (aggregate stock and in-stock pieces)
 * and its latest movements, including transfers in and out.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeftRight, MapPin, Pencil, PowerOff } from '@lucide/vue';
import { ref } from 'vue';
import {
    destroy,
    index,
} from '@/actions/App/Http/Controllers/Admin/Catalog/InventoryLocationController';
import { index as tagsIndex } from '@/actions/App/Http/Controllers/Admin/Catalog/LegacyTagController';
import { create as transferCreate } from '@/actions/App/Http/Controllers/Admin/Catalog/TagTransferController';
import ActionDialog from '@/components/admin/catalog/ActionDialog.vue';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import EmptyState from '@/components/admin/catalog/EmptyState.vue';
import LocationFormDialog from '@/components/admin/catalog/LocationFormDialog.vue';
import type { LocationData } from '@/components/admin/catalog/LocationFormDialog.vue';
import type { FancyOption } from '@/components/forms/FancySelect.vue';
import { Button } from '@/components/ui/button';
import { timeAgo } from '@/lib/datetime';
import { movementLabel } from '@/lib/tagStatus';

type Location = LocationData & {
    slug: string;
    type_label: string | null;
    ally: string | null;
    event_edition: string | null;
};

const props = defineProps<{
    location: Location;
    stock: {
        variant_id: number;
        product_id: number;
        product: string;
        variant: string;
        sku: string;
        on_hand: number;
        reserved: number;
        units_in_stock: number;
    }[];
    movements: {
        id: number;
        type: string;
        sku: string | null;
        quantity: number;
        reason: string | null;
        notes: string | null;
        actor: string | null;
        created_at: string | null;
    }[];
    types: FancyOption[];
    allies: FancyOption[];
    editions: FancyOption[];
}>();

defineOptions({
    layout: (props: { location: Location }) => ({
        breadcrumbs: [
            { title: 'Administración', href: '/admin' },
            { title: 'Ubicaciones', href: index().url },
            { title: props.location.name, href: '#' },
        ],
    }),
});

const editOpen = ref(false);
const confirmOpen = ref(false);
const removing = ref(false);

function remove() {
    router.delete(destroy(props.location.id).url, {
        preserveScroll: true,
        onStart: () => (removing.value = true),
        onFinish: () => {
            removing.value = false;
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="location.name" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <CatalogHeader
            eyebrow="Inventario · Ubicación"
            :title="location.name"
            :description="
                [
                    location.type_label,
                    location.ally ?? location.event_edition,
                    location.active ? null : 'Inactiva',
                ]
                    .filter(Boolean)
                    .join(' · ')
            "
            :icon="MapPin"
        >
            <template #actions>
                <Button as-child variant="outline" class="rounded-full">
                    <Link :href="transferCreate().url">
                        <ArrowLeftRight class="size-4" /> Transferir
                    </Link>
                </Button>
                <Button
                    variant="outline"
                    class="rounded-full"
                    @click="editOpen = true"
                >
                    <Pencil class="size-4" /> Editar
                </Button>
                <Button
                    v-if="location.active"
                    variant="ghost"
                    class="rounded-full text-red-700 hover:bg-red-50"
                    @click="confirmOpen = true"
                >
                    <PowerOff class="size-4" /> Quitar
                </Button>
            </template>
        </CatalogHeader>

        <p
            v-if="location.address || location.notes"
            class="mb-6 max-w-3xl text-sm text-muted-foreground"
        >
            {{ [location.address, location.notes].filter(Boolean).join(' — ') }}
        </p>

        <div class="grid gap-6 xl:grid-cols-[3fr_2fr]">
            <section>
                <h2
                    class="mb-3 text-sm font-semibold text-muted-foreground uppercase"
                >
                    Existencias por SKU
                </h2>
                <div
                    v-if="stock.length"
                    class="overflow-x-auto rounded-xl border border-border bg-card"
                >
                    <table class="w-full min-w-[520px] text-sm">
                        <thead>
                            <tr
                                class="border-b border-border bg-muted/40 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                            >
                                <th class="px-4 py-2.5 font-semibold">
                                    Producto / SKU
                                </th>
                                <th
                                    class="px-3 py-2.5 text-right font-semibold"
                                >
                                    Stock
                                </th>
                                <th
                                    class="px-3 py-2.5 text-right font-semibold"
                                >
                                    Reservado
                                </th>
                                <th
                                    class="px-3 py-2.5 text-right font-semibold"
                                >
                                    Piezas
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in stock"
                                :key="row.variant_id"
                                class="border-b border-border last:border-0"
                            >
                                <td class="px-4 py-2.5">
                                    <Link
                                        :href="`/admin/products/${row.product_id}`"
                                        class="font-medium hover:underline"
                                        >{{ row.product }}</Link
                                    >
                                    <span
                                        class="block font-mono text-[11px] text-muted-foreground"
                                        >{{ row.sku }} · {{ row.variant }}</span
                                    >
                                </td>
                                <td
                                    class="legacy-numeric px-3 py-2.5 text-right font-semibold"
                                >
                                    {{ row.on_hand }}
                                </td>
                                <td
                                    class="legacy-numeric px-3 py-2.5 text-right text-muted-foreground"
                                >
                                    {{ row.reserved }}
                                </td>
                                <td
                                    class="legacy-numeric px-3 py-2.5 text-right"
                                >
                                    <Link
                                        :href="
                                            tagsIndex({
                                                query: {
                                                    location: location.id,
                                                    variant: row.variant_id,
                                                    status: 'in_stock',
                                                },
                                            }).url
                                        "
                                        class="hover:underline"
                                        >{{ row.units_in_stock }}</Link
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <EmptyState
                    v-else
                    title="Sin existencias aquí"
                    text="Recibe un lote o transfiere piezas a esta ubicación."
                />
            </section>

            <section>
                <h2
                    class="mb-3 text-sm font-semibold text-muted-foreground uppercase"
                >
                    Movimientos recientes
                </h2>
                <ol
                    v-if="movements.length"
                    class="divide-y divide-border rounded-xl border border-border bg-card"
                >
                    <li
                        v-for="m in movements"
                        :key="m.id"
                        class="flex items-start gap-3 px-4 py-2.5 text-sm"
                    >
                        <span
                            class="legacy-numeric w-12 shrink-0 text-right font-semibold"
                            :class="
                                m.quantity < 0
                                    ? 'text-red-700'
                                    : 'text-emerald-700'
                            "
                            >{{ m.quantity > 0 ? '+' : ''
                            }}{{ m.quantity }}</span
                        >
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">
                                {{ movementLabel(m.type) }}
                                <span
                                    v-if="m.sku"
                                    class="font-mono text-[11px] text-muted-foreground"
                                    >{{ m.sku }}</span
                                >
                            </p>
                            <p
                                v-if="m.notes"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ m.notes }}
                            </p>
                        </div>
                        <p
                            class="shrink-0 text-right text-xs text-muted-foreground"
                        >
                            {{ m.actor ?? 'Sistema' }}<br />
                            {{ m.created_at ? timeAgo(m.created_at) : '' }}
                        </p>
                    </li>
                </ol>
                <EmptyState
                    v-else
                    title="Sin movimientos"
                    text="Aquí aparecerán entradas, ventas y transferencias."
                />
            </section>
        </div>

        <LocationFormDialog
            v-model:open="editOpen"
            :location="location"
            :types="types"
            :allies="allies"
            :editions="editions"
        />

        <ActionDialog
            v-model:open="confirmOpen"
            title="Quitar ubicación"
            description="Si tiene piezas, existencias o historial se desactivará (deja de aparecer para recibir y transferir). Si está vacía, se elimina."
            confirm-label="Quitar"
            destructive
            :processing="removing"
            @confirm="remove"
        />
    </div>
</template>
