<script setup lang="ts">
/**
 * One lot: its data, the codes CSV for the factory and the reception
 * (ReceiveLot: location + defective pieces). Lists every piece of the lot.
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Download, Factory, PackageCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    exportMethod,
    index,
    receive,
    show,
} from '@/actions/App/Http/Controllers/Admin/Catalog/InventoryLotController';
import { show as tagShow } from '@/actions/App/Http/Controllers/Admin/Catalog/LegacyTagController';
import ActionDialog from '@/components/admin/catalog/ActionDialog.vue';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import TagStatusBadge from '@/components/admin/catalog/TagStatusBadge.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import type { FancyOption } from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { hasPermission } from '@/composables/useAdminFilters';
import { shortDate } from '@/lib/datetime';
import { LOT_STATUS, TAG_STATUS_ORDER, tagStatusLabel } from '@/lib/tagStatus';

type LotDetail = {
    uuid: string;
    code: string;
    status: string;
    sku: string | null;
    variant: string | null;
    product: string | null;
    product_id: number | null;
    supplier: string | null;
    location: string | null;
    quantity: number;
    quantity_received: number;
    reference: string | null;
    ordered_at: string | null;
    received_at: string | null;
    notes: string | null;
    issues: string | null;
    created_by: string | null;
    unit_cost_minor: number | null;
    currency: string;
};

const props = defineProps<{
    lot: LotDetail;
    tags: {
        data: {
            uuid: string;
            serial: string;
            display_code: string;
            status: string;
            location: string | null;
        }[];
        links: PaginationLink[];
        total: number;
    };
    counts: Record<string, number>;
    filters: { status: string | null };
    locations: FancyOption[];
}>();

defineOptions({
    layout: (props: { lot: LotDetail }) => ({
        breadcrumbs: [
            { title: 'Administración', href: '/admin' },
            { title: 'Lotes', href: index().url },
            { title: props.lot.code, href: '#' },
        ],
    }),
});

const page = usePage();
const canManage = computed(() =>
    hasPermission(page.props.auth?.permissions, 'tags.manage'),
);

const receiveOpen = ref(false);
const receiveForm = useForm({
    inventory_location_id: null as string | number | null,
    defective: '',
    issues: '',
});
const defectiveCount = computed(
    () => receiveForm.defective.split(/[\s,;]+/).filter(Boolean).length,
);

function submitReceive() {
    receiveForm.post(receive(props.lot.uuid).url, {
        preserveScroll: true,
        onSuccess: () => {
            receiveOpen.value = false;
            receiveForm.reset();
        },
    });
}

function filterStatus(status: string | null) {
    router.get(show(props.lot.uuid).url, status ? { status } : {}, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

const statusChips = computed(() =>
    TAG_STATUS_ORDER.filter((s) => (props.counts[s] ?? 0) > 0),
);
</script>

<template>
    <Head :title="`Lote ${lot.code}`" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <CatalogHeader
            eyebrow="Inventario · Lote"
            :title="lot.code"
            :description="[lot.product, lot.sku].filter(Boolean).join(' · ')"
            :icon="Factory"
        >
            <template v-if="canManage" #actions>
                <Button as-child variant="outline" class="rounded-full">
                    <a :href="exportMethod(lot.uuid).url" download>
                        <Download class="size-4" /> Exportar códigos (CSV)
                    </a>
                </Button>
                <Button
                    v-if="lot.status === 'ordered'"
                    class="rounded-full"
                    @click="receiveOpen = true"
                >
                    <PackageCheck class="size-4" /> Recibir lote
                </Button>
            </template>
        </CatalogHeader>

        <dl class="mb-6 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
            <div class="fl-card p-4">
                <dt class="text-xs text-muted-foreground">Estado</dt>
                <dd class="mt-1">
                    <span
                        class="rounded-full border px-2 py-0.5 text-xs font-medium"
                        :class="LOT_STATUS[lot.status]?.class"
                        >{{ LOT_STATUS[lot.status]?.label ?? lot.status }}</span
                    >
                </dd>
            </div>
            <div class="fl-card p-4">
                <dt class="text-xs text-muted-foreground">Piezas</dt>
                <dd class="legacy-numeric text-xl font-semibold">
                    {{ lot.quantity }}
                </dd>
            </div>
            <div class="fl-card p-4">
                <dt class="text-xs text-muted-foreground">Recibidas</dt>
                <dd class="legacy-numeric text-xl font-semibold">
                    {{ lot.status === 'ordered' ? '—' : lot.quantity_received }}
                </dd>
            </div>
            <div class="fl-card p-4">
                <dt class="text-xs text-muted-foreground">Costo unitario</dt>
                <dd class="text-xl font-semibold">
                    <Money
                        v-if="lot.unit_cost_minor !== null"
                        :minor="lot.unit_cost_minor"
                        :currency="lot.currency"
                    />
                    <template v-else>—</template>
                </dd>
            </div>
            <div class="fl-card p-4">
                <dt class="text-xs text-muted-foreground">Proveedor</dt>
                <dd class="truncate font-medium">{{ lot.supplier ?? '—' }}</dd>
            </div>
            <div class="fl-card p-4">
                <dt class="text-xs text-muted-foreground">Ubicación</dt>
                <dd class="truncate font-medium">{{ lot.location ?? '—' }}</dd>
            </div>
        </dl>

        <div class="mb-6 grid gap-4 text-sm md:grid-cols-2">
            <div class="fl-card space-y-1 p-4">
                <p>
                    <span class="text-muted-foreground">Pedido:</span>
                    {{ shortDate(lot.ordered_at) }}
                </p>
                <p v-if="lot.received_at">
                    <span class="text-muted-foreground">Recibido:</span>
                    {{ shortDate(lot.received_at) }}
                </p>
                <p v-if="lot.reference">
                    <span class="text-muted-foreground">Referencia:</span>
                    {{ lot.reference }}
                </p>
                <p v-if="lot.created_by">
                    <span class="text-muted-foreground">Creado por:</span>
                    {{ lot.created_by }}
                </p>
                <p v-if="lot.product_id">
                    <Link
                        :href="`/admin/products/${lot.product_id}`"
                        class="font-medium text-fl-gold-ink hover:underline"
                        >Ver producto</Link
                    >
                </p>
            </div>
            <div class="fl-card space-y-2 p-4">
                <p v-if="lot.notes" class="whitespace-pre-line">
                    <span class="text-muted-foreground">Notas:</span>
                    {{ lot.notes }}
                </p>
                <p
                    v-if="lot.issues"
                    class="rounded-lg bg-amber-50 p-2 whitespace-pre-line text-amber-900"
                >
                    <strong>Incidencias:</strong> {{ lot.issues }}
                </p>
                <p
                    v-if="!lot.notes && !lot.issues"
                    class="text-muted-foreground"
                >
                    Sin notas ni incidencias.
                </p>
            </div>
        </div>

        <div class="mb-3 flex flex-wrap items-center gap-2">
            <h2
                class="mr-2 text-sm font-semibold text-muted-foreground uppercase"
            >
                Piezas del lote
            </h2>
            <button
                type="button"
                class="inline-flex min-h-9 items-center gap-1 rounded-full border px-3 text-xs font-medium"
                :class="
                    !filters.status
                        ? 'border-foreground bg-foreground text-background'
                        : 'border-border bg-card'
                "
                @click="filterStatus(null)"
            >
                Todas
            </button>
            <button
                v-for="s in statusChips"
                :key="s"
                type="button"
                class="inline-flex min-h-9 items-center gap-1 rounded-full border px-3 text-xs font-medium"
                :class="
                    filters.status === s
                        ? 'border-foreground bg-foreground text-background'
                        : 'border-border bg-card'
                "
                @click="filterStatus(s)"
            >
                {{ tagStatusLabel(s) }}
                <span class="opacity-70">{{ counts[s] }}</span>
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border bg-card">
            <table class="w-full min-w-[520px] text-sm">
                <thead>
                    <tr
                        class="border-b border-border bg-muted/40 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                    >
                        <th class="px-4 py-2.5 font-semibold">Serie</th>
                        <th class="px-3 py-2.5 font-semibold">Código</th>
                        <th class="px-3 py-2.5 font-semibold">Ubicación</th>
                        <th class="px-3 py-2.5 font-semibold">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="t in tags.data"
                        :key="t.uuid"
                        class="border-b border-border last:border-0 hover:bg-muted/30"
                    >
                        <td class="px-4 py-2">
                            <Link
                                :href="tagShow(t.uuid).url"
                                class="font-mono text-xs hover:underline"
                                >{{ t.serial }}</Link
                            >
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">
                            {{ t.display_code }}
                        </td>
                        <td class="px-3 py-2">{{ t.location ?? '—' }}</td>
                        <td class="px-3 py-2">
                            <TagStatusBadge :status="t.status" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="mt-4"><Pagination :links="tags.links" /></div>

        <ActionDialog
            v-model:open="receiveOpen"
            title="Recibir lote"
            :description="`Las ${lot.quantity} piezas pasan a inventario en la ubicación elegida. Las defectuosas se desactivan y no cuentan como stock.`"
            :confirm-label="
                defectiveCount
                    ? `Recibir (${lot.quantity - defectiveCount} buenas)`
                    : 'Recibir lote'
            "
            :processing="receiveForm.processing"
            :disabled="!receiveForm.inventory_location_id"
            @confirm="submitReceive"
        >
            <div class="grid gap-1.5">
                <Label for="rcv-location">Ubicación de recepción *</Label>
                <FancySelect
                    id="rcv-location"
                    v-model="receiveForm.inventory_location_id"
                    :options="locations"
                    placeholder="Elige la ubicación…"
                />
                <InputError
                    :message="receiveForm.errors.inventory_location_id"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="rcv-defective">Piezas defectuosas (opcional)</Label>
                <Textarea
                    id="rcv-defective"
                    v-model="receiveForm.defective"
                    rows="3"
                    placeholder="Lee el NFC o pega códigos o series, uno por línea"
                />
                <p class="text-xs text-muted-foreground">
                    {{ defectiveCount }} marcadas como defectuosas.
                </p>
                <InputError :message="receiveForm.errors.defective" />
            </div>
            <div class="grid gap-1.5">
                <Label for="rcv-issues">Incidencias</Label>
                <Textarea
                    id="rcv-issues"
                    v-model="receiveForm.issues"
                    rows="2"
                    placeholder="Ej. 3 correas con costura floja"
                />
                <InputError :message="receiveForm.errors.issues" />
            </div>
        </ActionDialog>
    </div>
</template>
