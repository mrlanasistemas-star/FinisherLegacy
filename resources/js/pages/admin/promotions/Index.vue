<script setup lang="ts">
/**
 * Administración → Ofertas: automatic sale prices (no code), on all
 * products or a selected set, with an optional date window. Distinct
 * from Cupones (a code the customer types to discount the cart).
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CalendarRange,
    Layers,
    Pause,
    Pencil,
    Percent,
    Plus,
    Tag,
    Trash2,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import ProductPicker from '@/components/admin/ProductPicker.vue';
import type { PickerProduct } from '@/components/admin/ProductPicker.vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { COMMERCE_ORDERS_AREA_NAV } from '@/config/areaNav';
import { shortDate } from '@/lib/datetime';
import { confirmAction } from '@/lib/swal';

type Promo = {
    id: number;
    name: string;
    badge_label: string | null;
    description: string | null;
    type: 'percentage' | 'fixed_amount';
    value: number;
    applies_to: 'all' | 'products';
    product_ids: number[];
    product_names: string[];
    starts_at: string | null;
    ends_at: string | null;
    active: boolean;
    state: 'running' | 'scheduled' | 'ended' | 'paused';
};

const props = defineProps<{ promotions: Promo[]; products: PickerProduct[] }>();

const stateChip: Record<Promo['state'], { label: string; class: string }> = {
    running: {
        label: 'Activa ahora',
        class: 'bg-emerald-100 text-emerald-800',
    },
    scheduled: { label: 'Programada', class: 'bg-sky-100 text-sky-800' },
    ended: { label: 'Terminada', class: 'bg-muted text-muted-foreground' },
    paused: { label: 'Pausada', class: 'bg-amber-100 text-amber-800' },
};

const productById = computed(
    () => new Map(props.products.map((p) => [p.id, p])),
);

const open = ref(false);
const editing = ref<Promo | null>(null);
const form = useForm({
    name: '',
    badge_label: '',
    description: '',
    type: 'percentage' as string | number | null,
    value: 10,
    applies_to: 'all' as string | number | null,
    product_ids: [] as number[],
    starts_at: null as string | null,
    ends_at: null as string | null,
    active: true,
});

function openForm(promo: Promo | null) {
    editing.value = promo;
    form.clearErrors();
    form.name = promo?.name ?? '';
    form.badge_label = promo?.badge_label ?? '';
    form.description = promo?.description ?? '';
    form.type = promo?.type ?? 'percentage';
    form.value = promo
        ? promo.type === 'percentage'
            ? promo.value
            : promo.value / 100
        : 10;
    form.applies_to = promo?.applies_to ?? 'all';
    form.product_ids = promo ? [...promo.product_ids] : [];
    form.starts_at = promo?.starts_at ?? null;
    form.ends_at = promo?.ends_at ?? null;
    form.active = promo?.active ?? true;
    open.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };
    form.transform((data) => ({
        ...data,
        value:
            data.type === 'percentage'
                ? Math.round(Number(data.value))
                : Math.round(Number(data.value) * 100),
    }));

    if (editing.value) {
        form.patch(`/admin/promotions/${editing.value.id}`, options);
    } else {
        form.post('/admin/promotions', options);
    }
}

async function remove(promo: Promo) {
    if (
        await confirmAction({
            title: `¿Eliminar "${promo.name}"?`,
            confirmButtonText: 'Eliminar',
        })
    ) {
        router.delete(`/admin/promotions/${promo.id}`, {
            preserveScroll: true,
        });
    }
}

const money = (minor: number) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        maximumFractionDigits: 0,
    }).format(minor / 100);
const discountLabel = (p: Promo) =>
    p.type === 'percentage' ? `-${p.value}%` : `-${money(p.value)}`;
</script>

<template>
    <Head title="Ofertas" />

    <div class="mx-auto w-full max-w-[1400px] p-4 md:p-8">
        <SecondaryNav :items="COMMERCE_ORDERS_AREA_NAV" />

        <div
            class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-red-50 text-red-700"
                    ><Tag class="size-5"
                /></span>
                <div>
                    <h1 class="text-xl font-semibold">Ofertas</h1>
                    <p class="text-sm text-muted-foreground">
                        Precios rebajados automáticos — sin código, visibles en
                        la tienda.
                    </p>
                </div>
            </div>
            <Button class="rounded-full" @click="openForm(null)"
                ><Plus class="size-4" /> Nueva oferta</Button
            >
        </div>

        <div
            v-if="promotions.length"
            class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="promo in promotions"
                :key="promo.id"
                class="fl-card flex flex-col overflow-hidden"
            >
                <div
                    class="flex items-start justify-between gap-3 bg-gradient-to-br from-red-50 to-fl-cream p-5"
                >
                    <div>
                        <p class="font-serif text-4xl text-red-700">
                            {{ discountLabel(promo) }}
                        </p>
                        <p class="mt-1 font-semibold">{{ promo.name }}</p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                        :class="stateChip[promo.state].class"
                        >{{ stateChip[promo.state].label }}</span
                    >
                </div>
                <div class="flex flex-1 flex-col gap-3 p-5 text-sm">
                    <p
                        v-if="promo.badge_label"
                        class="inline-flex w-fit items-center gap-1.5 rounded-full bg-red-600 px-2.5 py-0.5 text-[11px] font-bold text-white uppercase"
                    >
                        {{ promo.badge_label }}
                    </p>
                    <p class="flex items-center gap-2 text-muted-foreground">
                        <CalendarRange class="size-4" />
                        {{
                            promo.starts_at
                                ? shortDate(promo.starts_at)
                                : 'Desde hoy'
                        }}
                        →
                        {{
                            promo.ends_at ? shortDate(promo.ends_at) : 'sin fin'
                        }}
                    </p>
                    <p class="flex items-center gap-2 text-muted-foreground">
                        <Layers class="size-4" />
                        {{
                            promo.applies_to === 'all'
                                ? 'Todos los productos (excepto Legacy Plate)'
                                : `${promo.product_ids.length} productos seleccionados`
                        }}
                    </p>
                    <div
                        v-if="promo.applies_to === 'products'"
                        class="flex -space-x-2"
                    >
                        <template
                            v-for="id in promo.product_ids.slice(0, 8)"
                            :key="id"
                        >
                            <img
                                v-if="productById.get(id)?.image_url"
                                :src="productById.get(id)?.image_url ?? ''"
                                :alt="productById.get(id)?.name"
                                class="size-9 rounded-full border-2 border-card object-cover"
                            />
                            <span
                                v-else
                                class="flex size-9 items-center justify-center rounded-full border-2 border-card bg-muted text-[10px] font-semibold"
                                >{{
                                    productById.get(id)?.name.slice(0, 2)
                                }}</span
                            >
                        </template>
                    </div>
                    <div class="mt-auto flex gap-2 pt-2">
                        <Button
                            size="sm"
                            variant="outline"
                            class="rounded-full"
                            @click="openForm(promo)"
                            ><Pencil class="size-3.5" /> Editar</Button
                        >
                        <Button
                            size="sm"
                            variant="ghost"
                            class="rounded-full text-red-700"
                            @click="remove(promo)"
                            ><Trash2 class="size-3.5"
                        /></Button>
                    </div>
                </div>
            </article>
        </div>
        <div
            v-else
            class="fl-card flex flex-col items-center gap-3 p-14 text-center"
        >
            <Tag class="size-8 text-red-700" />
            <p class="font-semibold">Aún no hay ofertas</p>
            <p class="text-sm text-muted-foreground">
                Crea una para mostrar precios rebajados en la tienda.
            </p>
        </div>

        <Dialog v-model:open="open">
            <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Editar oferta' : 'Nueva oferta'
                    }}</DialogTitle>
                    <DialogDescription
                        >Se aplica sola en la tienda y en el checkout durante su
                        vigencia.</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="pr-name">Nombre interno</Label>
                            <Input
                                id="pr-name"
                                v-model="form.name"
                                placeholder="Buen Fin 2026"
                                required
                            />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="pr-badge">Etiqueta en tienda</Label>
                            <Input
                                id="pr-badge"
                                v-model="form.badge_label"
                                maxlength="40"
                                placeholder="-20% · Buen Fin"
                            />
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Tipo de descuento</Label>
                            <FancySelect
                                v-model="form.type"
                                :options="[
                                    {
                                        value: 'percentage',
                                        label: 'Porcentaje',
                                        icon: Percent,
                                    },
                                    {
                                        value: 'fixed_amount',
                                        label: 'Monto fijo por producto',
                                        icon: Wallet,
                                    },
                                ]"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="pr-value">{{
                                form.type === 'percentage'
                                    ? 'Porcentaje'
                                    : 'Monto (MXN)'
                            }}</Label>
                            <Input
                                id="pr-value"
                                v-model.number="form.value"
                                type="number"
                                min="1"
                            />
                            <InputError :message="form.errors.value" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Aplica a</Label>
                        <FancySelect
                            v-model="form.applies_to"
                            :options="[
                                {
                                    value: 'all',
                                    label: 'Todos los productos',
                                    description:
                                        'Excepto Legacy Plate y fotos (solo si las seleccionas)',
                                },
                                {
                                    value: 'products',
                                    label: 'Productos seleccionados',
                                },
                            ]"
                        />
                    </div>
                    <div
                        v-if="form.applies_to === 'products'"
                        class="grid gap-1.5"
                    >
                        <ProductPicker
                            v-model="form.product_ids"
                            :products="products"
                        />
                        <InputError :message="form.errors.product_ids" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label>Inicia</Label>
                            <DatePicker
                                v-model="form.starts_at"
                                :range-with="form.ends_at"
                                placeholder="Desde hoy"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Termina</Label>
                            <DatePicker
                                v-model="form.ends_at"
                                :range-with="form.starts_at"
                                placeholder="Sin fecha de fin"
                            />
                            <InputError :message="form.errors.ends_at" />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pr-desc">Notas</Label>
                        <Textarea
                            id="pr-desc"
                            v-model="form.description"
                            rows="2"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :model-value="form.active"
                            @update:model-value="(v) => (form.active = !!v)"
                        />
                        <Pause
                            v-if="!form.active"
                            class="size-3.5 text-amber-700"
                        />
                        Activa
                    </label>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="rounded-full"
                            :disabled="form.processing"
                            >Guardar oferta</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
