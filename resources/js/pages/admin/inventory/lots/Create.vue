<script setup lang="ts">
/**
 * New manufacturing lot (CreateLot): generates one random public code per
 * piece right away so the factory can encode NFC and print QR. Stock does
 * not move until the lot is received.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Factory, Info } from '@lucide/vue';
import {
    index,
    store,
} from '@/actions/App/Http/Controllers/Admin/Catalog/InventoryLotController';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import type { FancyOption } from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';

const props = defineProps<{
    variants: FancyOption[];
    suppliers: FancyOption[];
    maxQuantity: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Administración', href: '/admin' },
            { title: 'Lotes', href: '/admin/inventario/lotes' },
            { title: 'Nuevo lote', href: '#' },
        ],
    },
});

const form = useForm({
    product_variant_id: null as string | number | null,
    quantity: 100 as number | '',
    supplier_id: null as string | number | null,
    unit_cost: '' as number | '',
    ordered_at: new Date().toISOString().slice(0, 10) as string | null,
    reference: '',
    notes: '',
});

function submit() {
    form.transform((data) => ({
        product_variant_id: data.product_variant_id,
        quantity: data.quantity,
        supplier_id: data.supplier_id,
        unit_cost_minor:
            data.unit_cost === ''
                ? null
                : Math.round(Number(data.unit_cost) * 100),
        ordered_at: data.ordered_at,
        reference: data.reference || null,
        notes: data.notes || null,
    })).post(store().url);
}
</script>

<template>
    <Head title="Nuevo lote" />

    <div class="w-full max-w-3xl p-4 md:p-8">
        <CatalogHeader
            eyebrow="Inventario · Lotes"
            title="Nuevo lote"
            :icon="Factory"
        />

        <div
            class="mb-5 flex gap-3 rounded-xl border border-fl-gold/30 bg-fl-cream/60 p-4 text-sm"
        >
            <Info class="mt-0.5 size-4 shrink-0 text-fl-gold-ink" />
            <p>
                Al crear el lote se generan los códigos de cada pieza
                (aleatorios, no consecutivos). Después descarga el CSV para la
                fábrica. El stock se cuenta hasta que recibas el lote.
            </p>
        </div>

        <form
            class="fl-card grid gap-5 p-5 md:grid-cols-2 md:p-6"
            @submit.prevent="submit"
        >
            <div class="grid gap-1.5 md:col-span-2">
                <Label for="lot-variant">SKU (diseño) *</Label>
                <FancySelect
                    id="lot-variant"
                    v-model="form.product_variant_id"
                    :options="props.variants"
                    placeholder="Elige el SKU…"
                />
                <p
                    v-if="!props.variants.length"
                    class="text-xs text-muted-foreground"
                >
                    No hay productos con «Cada pieza es única» activado.
                </p>
                <InputError :message="form.errors.product_variant_id" />
            </div>
            <div class="grid gap-1.5">
                <Label for="lot-qty">Cantidad de piezas *</Label>
                <Input
                    id="lot-qty"
                    v-model.number="form.quantity"
                    type="number"
                    min="1"
                    :max="maxQuantity"
                    required
                />
                <p class="text-xs text-muted-foreground">
                    Máximo {{ maxQuantity.toLocaleString('es-MX') }} por lote.
                </p>
                <InputError :message="form.errors.quantity" />
            </div>
            <div class="grid gap-1.5">
                <Label for="lot-supplier">Proveedor</Label>
                <FancySelect
                    id="lot-supplier"
                    v-model="form.supplier_id"
                    :options="[
                        { value: null, label: 'Sin proveedor' },
                        ...props.suppliers,
                    ]"
                />
                <InputError :message="form.errors.supplier_id" />
            </div>
            <div class="grid gap-1.5">
                <Label for="lot-cost">Costo unitario (MXN)</Label>
                <Input
                    id="lot-cost"
                    v-model.number="form.unit_cost"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="Usa el costo del SKU si lo dejas vacío"
                />
                <InputError
                    :message="
                        (form.errors as Record<string, string | undefined>)
                            .unit_cost_minor
                    "
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="lot-date">Fecha de pedido</Label>
                <DatePicker id="lot-date" v-model="form.ordered_at" />
                <InputError :message="form.errors.ordered_at" />
            </div>
            <div class="grid gap-1.5 md:col-span-2">
                <Label for="lot-ref"
                    >Referencia (orden de compra, factura)</Label
                >
                <Input id="lot-ref" v-model="form.reference" maxlength="255" />
                <InputError :message="form.errors.reference" />
            </div>
            <div class="grid gap-1.5 md:col-span-2">
                <Label for="lot-notes">Notas</Label>
                <Textarea id="lot-notes" v-model="form.notes" rows="3" />
                <InputError :message="form.errors.notes" />
            </div>
            <div class="flex justify-end gap-2 md:col-span-2">
                <Button as-child variant="outline" class="rounded-full">
                    <Link :href="index().url">Cancelar</Link>
                </Button>
                <Button
                    type="submit"
                    class="rounded-full"
                    :disabled="form.processing || !form.product_variant_id"
                >
                    <Spinner v-if="form.processing" class="size-4" />
                    Crear lote y generar códigos
                </Button>
            </div>
        </form>
    </div>
</template>
