<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Ticket, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import ProductPicker from '@/components/admin/ProductPicker.vue';
import type { PickerProduct } from '@/components/admin/ProductPicker.vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import HelpPopover from '@/components/HelpPopover.vue';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { COMMERCE_ORDERS_AREA_NAV } from '@/config/areaNav';

type CouponRow = {
    id: number;
    code: string;
    name: string;
    type: 'percentage' | 'fixed_amount';
    value: number;
    currency: string | null;
    starts_at: string | null;
    ends_at: string | null;
    usage_limit_total: number | null;
    usage_limit_per_user: number | null;
    used_count: number;
    minimum_order_minor: number | null;
    active: boolean;
    applies_to: 'all' | 'products';
    product_ids: number[];
    product_names: string[];
};

defineProps<{
    coupons: {
        data: CouponRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
    products: PickerProduct[];
}>();

const columns = [
    { key: 'code', label: 'Código' },
    { key: 'kind', label: 'Tipo' },
    { key: 'discount', label: 'Descuento' },
    { key: 'scope', label: 'Aplica a' },
    { key: 'vigencia', label: 'Vigencia' },
    { key: 'usage', label: 'Usos' },
    { key: 'active', label: 'Estado' },
    { key: 'actions', label: '' },
];

/**
 * Not a second entity — a Coupon with a usage_limit_total reads as a
 * classic "cupón" (finite unit-limited codes); one with only a date
 * window and no usage limit reads as a "promoción" (time-bound, open
 * usage). Same table, same validation, just two ways to configure it —
 * a rule engine or a second table would be over-scoped for Phase 1.
 */
function couponKind(coupon: CouponRow): 'Cupón' | 'Promoción' {
    return coupon.usage_limit_total ? 'Cupón' : 'Promoción';
}

function emptyForm() {
    return {
        code: '',
        name: '',
        description: '',
        type: 'percentage' as string | number | null,
        value: 10,
        currency: '',
        starts_at: null as string | null,
        ends_at: null as string | null,
        usage_limit_total: '' as number | '',
        usage_limit_per_user: '' as number | '',
        minimum_order_minor: '' as number | '',
        applies_to: 'all' as string | number | null,
        product_ids: [] as number[],
        active: true,
    };
}

const dialogOpen = ref(false);
const editingId = ref<number | null>(null);
const form = useForm(emptyForm());

function money(minor: number, currency = 'MXN') {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency,
    }).format(minor / 100);
}

function openCreate() {
    editingId.value = null;
    form.defaults(emptyForm());
    form.reset();
    dialogOpen.value = true;
}

function openEdit(coupon: CouponRow) {
    editingId.value = coupon.id;
    form.defaults({
        code: coupon.code,
        name: coupon.name,
        description: '',
        type: coupon.type,
        value: coupon.type === 'percentage' ? coupon.value : coupon.value / 100,
        currency: coupon.currency ?? '',
        starts_at: coupon.starts_at,
        ends_at: coupon.ends_at,
        usage_limit_total: coupon.usage_limit_total ?? '',
        usage_limit_per_user: coupon.usage_limit_per_user ?? '',
        minimum_order_minor:
            coupon.minimum_order_minor !== null
                ? coupon.minimum_order_minor / 100
                : '',
        applies_to: coupon.applies_to ?? 'all',
        product_ids: [...(coupon.product_ids ?? [])],
        active: coupon.active,
    });
    form.reset();
    dialogOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
        },
    };

    // The form speaks pesos; the backend stores minor units.
    const toServer = (data: ReturnType<typeof emptyForm>) => ({
        ...data,
        value:
            data.type === 'percentage'
                ? Math.round(Number(data.value))
                : Math.round(Number(data.value) * 100),
        minimum_order_minor:
            data.minimum_order_minor === ''
                ? null
                : Math.round(Number(data.minimum_order_minor) * 100),
    });

    if (editingId.value !== null) {
        form.transform((data) => ({
            ...toServer(data),
            _method: 'patch',
        })).post(`/admin/coupons/${editingId.value}`, options);
    } else {
        form.transform(toServer).post('/admin/coupons', options);
    }
}

const deleteTarget = ref<CouponRow | null>(null);

function confirmDestroy() {
    if (deleteTarget.value === null) {
        return;
    }

    router.delete(`/admin/coupons/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <Head title="Cupones" />

    <div class="w-full px-4 py-4 sm:px-6 md:py-8 xl:px-8">
        <SecondaryNav :items="COMMERCE_ORDERS_AREA_NAV" />

        <div class="mb-6 flex items-center justify-between">
            <h1
                class="flex items-center gap-1.5 text-xl font-bold text-foreground"
            >
                <Ticket class="size-5 text-fl-gold-ink" />
                Cupones
                <HelpPopover
                    title="Cupón vs. promoción"
                    text="Un cupón es un código que el cliente escribe en el carrito. Puede descontar todo el carrito o solo productos seleccionados. Para precios rebajados automáticos (sin código) usa Ofertas."
                />
            </h1>
            <Button class="rounded-full" @click="openCreate">
                <Plus class="size-4" />
                Nuevo cupón
            </Button>
        </div>

        <AdminTable
            :columns="columns"
            :rows="coupons"
            searchable
            :initial-query="filters.q"
        >
            <template #cell-code="{ row }">
                <span class="font-mono text-foreground">{{ row.code }}</span>
                <div class="text-xs text-muted-foreground">{{ row.name }}</div>
            </template>

            <template #cell-kind="{ row }">
                <Badge
                    variant="outline"
                    :class="
                        couponKind(row as unknown as CouponRow) === 'Cupón'
                            ? 'border-fl-gold/30 text-fl-gold-ink'
                            : 'border-sky-500/30 text-sky-700'
                    "
                >
                    {{ couponKind(row as unknown as CouponRow) }}
                </Badge>
            </template>

            <template #cell-discount="{ row }">
                {{
                    row.type === 'percentage'
                        ? `${row.value}%`
                        : money(
                              row.value as number,
                              (row.currency as string | null) ?? 'MXN',
                          )
                }}
            </template>

            <template #cell-scope="{ row }">
                <span v-if="row.applies_to !== 'products'"
                    >Todo el carrito</span
                >
                <span v-else
                    >{{ (row.product_names as unknown as string[]).length }}
                    productos:
                    {{
                        (row.product_names as unknown as string[])
                            .slice(0, 3)
                            .join(', ')
                    }}</span
                >
            </template>

            <template #cell-vigencia="{ row }">
                <span v-if="!row.starts_at && !row.ends_at">Sin límite</span>
                <span v-else
                    >{{ row.starts_at ?? '—' }} → {{ row.ends_at ?? '—' }}</span
                >
            </template>

            <template #cell-usage="{ row }">
                {{ row.used_count
                }}<span v-if="row.usage_limit_total">
                    / {{ row.usage_limit_total }}</span
                >
            </template>

            <template #cell-active="{ row }">
                <Badge
                    :class="
                        row.active
                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700'
                            : 'border-border bg-foreground/[0.03] text-muted-foreground'
                    "
                >
                    {{ row.active ? 'Activo' : 'Inactivo' }}
                </Badge>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-1">
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-8 text-muted-foreground hover:text-foreground"
                        @click="openEdit(row as unknown as CouponRow)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-8 text-muted-foreground hover:text-red-700"
                        @click="deleteTarget = row as unknown as CouponRow"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </template>
        </AdminTable>

        <Dialog v-model:open="dialogOpen">
            <DialogContent
                class="max-h-[90vh] overflow-y-auto border-border bg-card text-foreground sm:max-w-lg"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        editingId ? 'Editar cupón' : 'Nuevo cupón'
                    }}</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Código</Label>
                            <Input
                                v-model="form.code"
                                class="bg-background font-mono uppercase"
                                placeholder="SPRING10"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Nombre</Label>
                            <Input
                                v-model="form.name"
                                class="bg-background"
                                placeholder="Promo primavera"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Descripción (opcional)</Label>
                        <Textarea
                            v-model="form.description"
                            class="bg-background"
                            rows="2"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Tipo</Label>
                            <FancySelect
                                v-model="form.type"
                                :options="[
                                    {
                                        value: 'percentage',
                                        label: 'Porcentaje',
                                    },
                                    {
                                        value: 'fixed_amount',
                                        label: 'Monto fijo',
                                    },
                                ]"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>{{
                                form.type === 'percentage'
                                    ? 'Porcentaje (1-100)'
                                    : 'Monto (MXN)'
                            }}</Label>
                            <Input
                                v-model.number="form.value"
                                type="number"
                                min="1"
                                class="bg-background"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Inicia (opcional)</Label>
                            <DatePicker
                                v-model="form.starts_at"
                                :range-with="form.ends_at"
                                placeholder="Desde hoy"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Termina (opcional)</Label>
                            <DatePicker
                                v-model="form.ends_at"
                                :range-with="form.starts_at"
                                placeholder="Sin fecha de fin"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Aplica a</Label>
                        <FancySelect
                            v-model="form.applies_to"
                            :options="[
                                { value: 'all', label: 'Todo el carrito' },
                                {
                                    value: 'products',
                                    label: 'Solo productos seleccionados',
                                    description:
                                        'El descuento se calcula sobre esas líneas',
                                },
                            ]"
                        />
                    </div>
                    <div
                        v-if="form.applies_to === 'products'"
                        class="grid gap-2"
                    >
                        <ProductPicker
                            v-model="form.product_ids"
                            :products="products"
                        />
                        <p
                            v-if="form.errors.product_ids"
                            class="text-sm text-red-700"
                        >
                            {{ form.errors.product_ids }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Límite total de usos</Label>
                            <Input
                                v-model.number="form.usage_limit_total"
                                type="number"
                                min="1"
                                class="bg-background"
                                placeholder="Sin límite (promoción)"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Límite por usuario</Label>
                            <Input
                                v-model.number="form.usage_limit_per_user"
                                type="number"
                                min="1"
                                class="bg-background"
                                placeholder="Sin límite"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Monto mínimo de compra (MXN, opcional)</Label>
                        <Input
                            v-model.number="form.minimum_order_minor"
                            type="number"
                            min="0"
                            class="bg-background"
                        />
                    </div>

                    <label
                        class="flex items-center gap-2 text-sm text-foreground"
                    >
                        <Checkbox
                            :model-value="form.active"
                            @update:model-value="(v) => (form.active = !!v)"
                        />
                        Activo
                    </label>

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

        <AlertDialog
            :open="deleteTarget !== null"
            @update:open="
                (open) => {
                    if (!open) deleteTarget = null;
                }
            "
        >
            <AlertDialogContent class="border-border bg-card text-foreground">
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >¿Eliminar el cupón
                        {{ deleteTarget?.code }}?</AlertDialogTitle
                    >
                    <AlertDialogDescription class="text-muted-foreground">
                        Si el cupón ya fue usado, se desactivará en lugar de
                        eliminarse — su historial de pedidos se conserva.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel
                        class="border-border bg-transparent text-foreground hover:bg-foreground/5"
                        @click="deleteTarget = null"
                        >Cancelar</AlertDialogCancel
                    >
                    <AlertDialogAction
                        class="bg-red-600 text-white hover:bg-red-500"
                        @click="confirmDestroy"
                        >Eliminar</AlertDialogAction
                    >
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
