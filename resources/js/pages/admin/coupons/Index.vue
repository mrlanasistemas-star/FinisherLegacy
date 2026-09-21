<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Ticket, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AdminTable from '@/components/admin/AdminTable.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

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
};

defineProps<{
    coupons: {
        data: CouponRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
}>();

const columns = [
    { key: 'code', label: 'Código' },
    { key: 'discount', label: 'Descuento' },
    { key: 'vigencia', label: 'Vigencia' },
    { key: 'usage', label: 'Usos' },
    { key: 'active', label: 'Estado' },
    { key: 'actions', label: '' },
];

function emptyForm() {
    return {
        code: '',
        name: '',
        description: '',
        type: 'percentage' as 'percentage' | 'fixed_amount',
        value: 10,
        currency: '',
        starts_at: '',
        ends_at: '',
        usage_limit_total: '' as number | '',
        usage_limit_per_user: '' as number | '',
        minimum_order_minor: '' as number | '',
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
        value: coupon.value,
        currency: coupon.currency ?? '',
        starts_at: coupon.starts_at ?? '',
        ends_at: coupon.ends_at ?? '',
        usage_limit_total: coupon.usage_limit_total ?? '',
        usage_limit_per_user: coupon.usage_limit_per_user ?? '',
        minimum_order_minor: coupon.minimum_order_minor ?? '',
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

    if (editingId.value !== null) {
        form.transform((data) => ({ ...data, _method: 'patch' })).post(
            `/admin/coupons/${editingId.value}`,
            options,
        );
    } else {
        form.post('/admin/coupons', options);
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

    <div class="w-full max-w-[1600px] px-4 py-4 sm:px-6 md:py-8 xl:px-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="flex items-center gap-2 text-xl font-bold text-white">
                <Ticket class="size-5 text-fl-gold" />
                Cupones
            </h1>
            <Button
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                @click="openCreate"
            >
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
                <span class="font-mono text-white">{{ row.code }}</span>
                <div class="text-xs text-white/50">{{ row.name }}</div>
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
                            ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
                            : 'border-white/10 bg-white/5 text-white/50'
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
                        class="size-8 text-white/60 hover:text-white"
                        @click="openEdit(row as unknown as CouponRow)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                    <Button
                        size="icon"
                        variant="ghost"
                        class="size-8 text-white/60 hover:text-red-400"
                        @click="deleteTarget = row as unknown as CouponRow"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </template>
        </AdminTable>

        <Dialog v-model:open="dialogOpen">
            <DialogContent
                class="dark max-h-[90vh] overflow-y-auto border-white/10 bg-fl-graphite text-white sm:max-w-lg"
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
                                class="bg-fl-black font-mono uppercase"
                                placeholder="SPRING10"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Nombre</Label>
                            <Input
                                v-model="form.name"
                                class="bg-fl-black"
                                placeholder="Promo primavera"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label>Descripción (opcional)</Label>
                        <Textarea
                            v-model="form.description"
                            class="bg-fl-black"
                            rows="2"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Tipo</Label>
                            <Select v-model="form.type">
                                <SelectTrigger
                                    class="border-white/10 bg-fl-black text-white"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="percentage"
                                        >Porcentaje</SelectItem
                                    >
                                    <SelectItem value="fixed_amount"
                                        >Monto fijo</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-2">
                            <Label>{{
                                form.type === 'percentage'
                                    ? 'Porcentaje (1-100)'
                                    : 'Monto (centavos)'
                            }}</Label>
                            <Input
                                v-model.number="form.value"
                                type="number"
                                min="1"
                                class="bg-fl-black"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Inicia (opcional)</Label>
                            <Input
                                v-model="form.starts_at"
                                type="date"
                                class="bg-fl-black"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Termina (opcional)</Label>
                            <Input
                                v-model="form.ends_at"
                                type="date"
                                class="bg-fl-black"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label>Límite total de usos</Label>
                            <Input
                                v-model.number="form.usage_limit_total"
                                type="number"
                                min="1"
                                class="bg-fl-black"
                                placeholder="Sin límite"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Límite por usuario</Label>
                            <Input
                                v-model.number="form.usage_limit_per_user"
                                type="number"
                                min="1"
                                class="bg-fl-black"
                                placeholder="Sin límite"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label
                            >Monto mínimo de compra (centavos, opcional)</Label
                        >
                        <Input
                            v-model.number="form.minimum_order_minor"
                            type="number"
                            min="0"
                            class="bg-fl-black"
                        />
                    </div>

                    <label
                        class="flex items-center gap-2 text-sm text-white/80"
                    >
                        <Checkbox v-model="form.active" />
                        Activo
                    </label>

                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
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
            <AlertDialogContent
                class="dark border-white/10 bg-fl-graphite text-white"
            >
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >¿Eliminar el cupón
                        {{ deleteTarget?.code }}?</AlertDialogTitle
                    >
                    <AlertDialogDescription class="text-white/60">
                        Si el cupón ya fue usado, se desactivará en lugar de
                        eliminarse — su historial de pedidos se conserva.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel
                        class="border-white/10 bg-transparent text-white hover:bg-white/10"
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
